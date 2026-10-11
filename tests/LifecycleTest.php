<?php

declare(strict_types=1);

use Pcurl\Multi;

it('drops its state with the CurlMultiHandle: a new multi starts with no data or callbacks', function (): void {
    // Address reuse: a freed CURLM* can come back for the next curl_multi_init().
    for ($round = 0; $round < 50; $round++) {
        $old = curl_multi_init();
        Multi::curlMultiSetopt($old, CURLMOPT_TIMERDATA, "round {$round}");
        Multi::curlMultiSetopt($old, CURLMOPT_TIMERFUNCTION, fn (): int => 0);
        Multi::curlMultiSetopt($old, CURLMOPT_SOCKETFUNCTION, fn (): int => 0);
        unset($old);

        $new = curl_multi_init();
        $seen = [];
        Multi::curlMultiSetopt($new, CURLMOPT_TIMERFUNCTION, function (CurlMultiHandle $m, int $ms, mixed $clientp) use (&$seen): int {
            $seen[] = $clientp;

            return 0;
        });
        curl_multi_add_handle($new, easyFor('/reuse'));

        expect($seen)->not->toBeEmpty()->and(array_unique($seen))->toBe([null]);
    }
});

it('frees a multi with callbacks installed mid-transfer without crashing', function (): void {
    $multi = curl_multi_init();
    Multi::curlMultiSetopt($multi, CURLMOPT_TIMERFUNCTION, fn (): int => 0);
    Multi::curlMultiSetopt($multi, CURLMOPT_SOCKETFUNCTION, fn (): int => 0);
    $easy = easyFor('/mid');
    curl_multi_add_handle($multi, $easy);
    Multi::curlMultiSocketAction($multi, CURL_SOCKET_TIMEOUT, 0);

    unset($multi);
    gc_collect_cycles();

    expect(curl_getinfo($easy, CURLINFO_RESPONSE_CODE))->toBe(0);
});

it('keeps a multi alive while a callback captures it, until that option is set back to null', function (): void {
    $multi = curl_multi_init();
    $weak = WeakReference::create($multi);
    Multi::curlMultiSetopt($multi, CURLMOPT_TIMERFUNCTION, function () use ($multi): int {
        return 0;
    });

    unset($multi);
    gc_collect_cycles();
    expect($weak->get())->toBeInstanceOf(CurlMultiHandle::class);

    Multi::curlMultiSetopt($weak->get(), CURLMOPT_TIMERFUNCTION, null);
    gc_collect_cycles();
    expect($weak->get())->toBeNull();
});

it('lets a multi go once a capturing callback is replaced by one that does not capture it', function (): void {
    $multi = curl_multi_init();
    $weak = WeakReference::create($multi);
    Multi::curlMultiSetopt($multi, CURLMOPT_SOCKETFUNCTION, function () use ($multi): int {
        return 0;
    });
    Multi::curlMultiSetopt($multi, CURLMOPT_SOCKETFUNCTION, fn (): int => 0);

    unset($multi);
    gc_collect_cycles();

    expect($weak->get())->toBeNull();
});

it('survives a handle that is removed and re-added across transfers', function (): void {
    $multi = curl_multi_init();
    $easy = easyFor('/again');
    drive($multi, [$easy]);
    expect(curl_multi_getcontent($easy))->toBe('hello /again');

    curl_multi_remove_handle($multi, $easy);
    curl_setopt($easy, CURLOPT_URL, localServer() . '/again2');
    drive($multi, [$easy]);

    expect(curl_multi_getcontent($easy))->toBe('hello /again2');
});
