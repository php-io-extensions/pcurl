<?php

declare(strict_types=1);

use Pcurl\Multi;

beforeEach(function (): void {
    $this->multi = curl_multi_init();
});

it('fires with the same CurlMultiHandle, a timeout in ms and $clientp when a handle is added', function (): void {
    $calls = [];
    Multi::curlMultiSetopt($this->multi, CURLMOPT_TIMERDATA, 'td');
    Multi::curlMultiSetopt($this->multi, CURLMOPT_TIMERFUNCTION, function (CurlMultiHandle $m, int $timeoutMs, mixed $clientp) use (&$calls): int {
        $calls[] = [$m, $timeoutMs, $clientp];

        return 0;
    });

    curl_multi_add_handle($this->multi, easyFor('/timer'));

    expect($calls)->toHaveCount(1)
        ->and($calls[0][0])->toBe($this->multi)
        ->and($calls[0][1])->toBeGreaterThanOrEqual(0)
        ->and($calls[0][2])->toBe('td');
});

it('is asked to cancel with -1 once the transfer completes', function (): void {
    $timeouts = [];
    $easy = easyFor('/cancel');
    drive($this->multi, [$easy], null, function (CurlMultiHandle $m, int $timeoutMs) use (&$timeouts): void {
        $timeouts[] = $timeoutMs;
    });

    expect($timeouts)->toContain(-1)
        ->and(end($timeouts))->toBe(-1)
        ->and(curl_multi_getcontent($easy))->toBe('hello /cancel');
});

/*
 * libcurl calls the timer callback only when the timeout it wants changes. Each test below installs a
 * no-op callback before adding the handle, so libcurl has a recorded timeout (0) that the next call
 * moves, then swaps in the callback under test.
 */
function multiWithPendingTransfer(CurlMultiHandle $multi, string $path): void
{
    Multi::curlMultiSetopt($multi, CURLMOPT_TIMERFUNCTION, fn (): int => 0);
    curl_multi_add_handle($multi, easyFor($path));
}

it('treats a null return as 0, so libcurl carries on', function (): void {
    multiWithPendingTransfer($this->multi, '/null-return');
    $calls = 0;
    Multi::curlMultiSetopt($this->multi, CURLMOPT_TIMERFUNCTION, function () use (&$calls): void {
        $calls++;
    });

    expect(Multi::curlMultiSocketAction($this->multi, CURL_SOCKET_TIMEOUT, 0))->toBe(['res' => CURLM_OK, 'running_handles' => 1])
        ->and($calls)->toBeGreaterThan(0)
        ->and(curl_multi_add_handle($this->multi, easyFor('/null-return-2')))->toBe(CURLM_OK);
});

it('passes a -1 return to libcurl, which aborts the multi', function (): void {
    multiWithPendingTransfer($this->multi, '/minus-one');
    Multi::curlMultiSetopt($this->multi, CURLMOPT_TIMERFUNCTION, fn (): int => -1);

    expect(Multi::curlMultiSocketAction($this->multi, CURL_SOCKET_TIMEOUT, 0))->toBe(['res' => CURLM_ABORTED_BY_CALLBACK, 'running_handles' => 1]);

    Multi::curlMultiSetopt($this->multi, CURLMOPT_TIMERFUNCTION, null);
    expect(curl_multi_add_handle($this->multi, easyFor('/minus-one-2')))->toBe(CURLM_ABORTED_BY_CALLBACK);
})->skip(curl_version()['version_number'] < 0x075100, 'CURLM_ABORTED_BY_CALLBACK arrived in libcurl 7.81');

it('surfaces an exception thrown inside it from the pcurl call that fired it, and libcurl marks the multi aborted', function (): void {
    multiWithPendingTransfer($this->multi, '/abort');
    Multi::curlMultiSetopt($this->multi, CURLMOPT_TIMERFUNCTION, function (): int {
        throw new LogicException('timer boom');
    });

    expect(fn () => Multi::curlMultiSocketAction($this->multi, CURL_SOCKET_TIMEOUT, 0))->toThrow(LogicException::class, 'timer boom');

    // The trampoline returned -1 for the throw; libcurl treats that as "abort" and refuses further work.
    Multi::curlMultiSetopt($this->multi, CURLMOPT_TIMERFUNCTION, null);
    expect(curl_multi_add_handle($this->multi, easyFor('/after-abort')))->toBe(CURLM_ABORTED_BY_CALLBACK);
})->skip(curl_version()['version_number'] < 0x075100, 'CURLM_ABORTED_BY_CALLBACK arrived in libcurl 7.81');

it('surfaces an exception thrown inside it from the ext-curl call that fired it', function (): void {
    multiWithPendingTransfer($this->multi, '/exec-throw');
    Multi::curlMultiSetopt($this->multi, CURLMOPT_TIMERFUNCTION, function (): int {
        throw new LogicException('timer boom');
    });

    expect(fn () => curl_multi_exec($this->multi, $running))->toThrow(LogicException::class, 'timer boom');
});

it('surfaces an exception thrown inside it from curl_multi_add_handle and leaves the multi usable', function (): void {
    Multi::curlMultiSetopt($this->multi, CURLMOPT_TIMERFUNCTION, function (): int {
        throw new LogicException('timer boom');
    });

    expect(fn () => curl_multi_add_handle($this->multi, easyFor('/add-throw')))->toThrow(LogicException::class, 'timer boom');

    Multi::curlMultiSetopt($this->multi, CURLMOPT_TIMERFUNCTION, null);
    expect(Multi::curlMultiSocketAction($this->multi, CURL_SOCKET_TIMEOUT, 0)['res'])->toBeIn([CURLM_OK, CURLM_ABORTED_BY_CALLBACK]);
})->skip(
    curl_version()['version_number'] < 0x081000,
    'libcurl before 8.16 leaves a rejected handle in its timer tree when the timer callback fails inside curl_multi_add_handle; the next call on the multi hangs or crashes',
);
