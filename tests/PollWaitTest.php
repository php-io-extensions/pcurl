<?php

declare(strict_types=1);

use Pcurl\Multi;

beforeEach(function (): void {
    $this->multi = curl_multi_init();
});

it('sleeps in curlMultiPoll for the timeout when nothing is ready', function (): void {
    $start = microtime(true);
    $result = Multi::curlMultiPoll($this->multi, [], 50);

    expect($result)->toBe(['res' => CURLM_OK, 'numfds' => 0, 'extra_fds' => []])
        ->and(microtime(true) - $start)->toBeGreaterThanOrEqual(0.04)->toBeLessThan(1.0);
});

it('returns from curlMultiWait at once when it has no descriptor to wait on', function (): void {
    $start = microtime(true);
    $result = Multi::curlMultiWait($this->multi, [], 2000);

    expect($result)->toBe(['res' => CURLM_OK, 'numfds' => 0, 'extra_fds' => []])
        ->and(microtime(true) - $start)->toBeLessThan(0.5);
});

it('waits the timeout on an idle extra fd', function (string $method): void {
    [$a, $b, $fdA] = socketPairWithFds();
    $start = microtime(true);
    $result = Multi::$method($this->multi, [['fd' => $fdA, 'events' => CURL_WAIT_POLLIN]], 50);

    expect($result)->toBe(['res' => CURLM_OK, 'numfds' => 0, 'extra_fds' => [['fd' => $fdA, 'events' => CURL_WAIT_POLLIN, 'revents' => 0]]])
        ->and(microtime(true) - $start)->toBeGreaterThanOrEqual(0.04)->toBeLessThan(1.0);
})->with(['curlMultiPoll', 'curlMultiWait']);

it('reports a readable extra fd with its revents, echoing fd and events', function (string $method): void {
    [$a, $b, $fdA] = socketPairWithFds();
    fwrite($b, 'x');

    $result = Multi::$method($this->multi, [['fd' => $fdA, 'events' => CURL_WAIT_POLLIN]], 1000);

    expect($result['res'])->toBe(CURLM_OK)
        ->and($result['numfds'])->toBe(1)
        ->and($result['extra_fds'])->toBe([['fd' => $fdA, 'events' => CURL_WAIT_POLLIN, 'revents' => CURL_WAIT_POLLIN]]);
})->with(['curlMultiPoll', 'curlMultiWait']);

it('reports a writable extra fd', function (): void {
    [$a, $b, $fdA] = socketPairWithFds();

    $result = Multi::curlMultiPoll($this->multi, [['fd' => $fdA, 'events' => CURL_WAIT_POLLOUT]], 1000);

    expect($result['numfds'])->toBe(1)
        ->and($result['extra_fds'][0]['revents'] & CURL_WAIT_POLLOUT)->toBe(CURL_WAIT_POLLOUT);
});

it('defaults a missing events key to 0 and reports no readiness for it', function (): void {
    [$a, $b, $fdA] = socketPairWithFds();
    fwrite($b, 'x');

    $result = Multi::curlMultiWait($this->multi, [['fd' => $fdA]], 10);

    expect($result)->toBe(['res' => CURLM_OK, 'numfds' => 0, 'extra_fds' => [['fd' => $fdA, 'events' => 0, 'revents' => 0]]]);
});

it('keeps extra fds in order and reports only the ready ones', function (): void {
    [$a, $b, $fdA, $fdB] = socketPairWithFds();
    [$c, $d, $fdC] = socketPairWithFds();
    fwrite($d, 'x');

    $result = Multi::curlMultiPoll($this->multi, [
        ['fd' => $fdA, 'events' => CURL_WAIT_POLLIN],
        ['fd' => $fdC, 'events' => CURL_WAIT_POLLIN],
    ], 100);

    expect($result['numfds'])->toBe(1)
        ->and(array_column($result['extra_fds'], 'fd'))->toBe([$fdA, $fdC])
        ->and(array_column($result['extra_fds'], 'revents'))->toBe([0, CURL_WAIT_POLLIN]);
});

it('throws for an extra_fds entry that is not an array or has no fd', function (string $method): void {
    expect(fn () => Multi::$method($this->multi, [5], 0))->toThrow(Exception::class, "each extra_fds entry needs an 'fd' key")
        ->and(fn () => Multi::$method($this->multi, [['events' => CURL_WAIT_POLLIN]], 0))->toThrow(Exception::class, "each extra_fds entry needs an 'fd' key")
        ->and(fn () => Multi::$method($this->multi, [['fd' => 0], 'nope'], 0))->toThrow(Exception::class, "each extra_fds entry needs an 'fd' key");
})->with(['curlMultiPoll', 'curlMultiWait']);

it('returns from curlMultiPoll at once when curlMultiWakeup was called first', function (): void {
    expect(Multi::curlMultiWakeup($this->multi))->toBe(CURLM_OK);

    $start = microtime(true);
    $result = Multi::curlMultiPoll($this->multi, [], 2000);

    expect($result['res'])->toBe(CURLM_OK)
        ->and($result['numfds'])->toBe(0)
        ->and(microtime(true) - $start)->toBeLessThan(0.5);
});

it('wakes a blocking curlMultiPoll from another process', function (): void {
    // curl_multi_wakeup is thread-safe; the next best cross-context trigger without ext-parallel is
    // an extra fd written by a child process, which proves the poll returns on external input.
    [$a, $b, $fdA] = socketPairWithFds();
    $child = proc_open(
        [PHP_BINARY, '-n', '-r', 'usleep(100000); fwrite(STDOUT, "go");'],
        [1 => $b],
        $pipes,
    ) ?: throw new RuntimeException('no child');

    $start = microtime(true);
    $result = Multi::curlMultiPoll($this->multi, [['fd' => $fdA, 'events' => CURL_WAIT_POLLIN]], 5000);
    proc_close($child);

    expect($result['numfds'])->toBe(1)
        ->and($result['extra_fds'][0]['revents'])->toBe(CURL_WAIT_POLLIN)
        ->and(microtime(true) - $start)->toBeLessThan(4.0)
        ->and(fread($a, 2))->toBe('go');
});

it('drives a transfer to completion with curlMultiPoll plus curl_multi_exec', function (): void {
    $easy = easyFor('/poll');
    curl_multi_add_handle($this->multi, $easy);

    $running = 0;
    for ($end = microtime(true) + 5; microtime(true) < $end;) {
        curl_multi_exec($this->multi, $running);
        if ($running === 0) {
            break;
        }
        expect(Multi::curlMultiPoll($this->multi, [], 100)['res'])->toBe(CURLM_OK);
    }

    expect($running)->toBe(0)->and(curl_multi_getcontent($easy))->toBe('hello /poll');
});
