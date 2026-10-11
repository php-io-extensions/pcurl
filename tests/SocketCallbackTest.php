<?php

declare(strict_types=1);

use Pcurl\Multi;

beforeEach(function (): void {
    $this->multi = curl_multi_init();
});

it('reports the added CurlHandle, its socket and the poll state, then CURL_POLL_REMOVE', function (): void {
    $easy = easyFor('/socket');
    $events = [];
    drive($this->multi, [$easy], function (?CurlHandle $e, int $s, int $what, mixed $clientp, mixed $socketp) use (&$events): void {
        $events[] = ['easy' => $e, 's' => $s, 'what' => $what];
    });

    $forEasy = array_values(array_filter($events, fn (array $ev) => $ev['easy'] === $easy));
    expect($forEasy)->not->toBeEmpty();
    foreach ($events as $event) {
        // libcurl's own internal handles (resolver sockets, connection close) are not in the multi's list: null.
        expect($event['easy'])->toBeIn([null, $easy])
            ->and($event['s'])->toBeGreaterThanOrEqual(0)
            ->and($event['what'])->toBeIn([CURL_POLL_IN, CURL_POLL_OUT, CURL_POLL_INOUT, CURL_POLL_REMOVE]);
    }
    $lastWhat = [];
    foreach ($events as $event) {
        $lastWhat[$event['s']] = $event['what'];
    }
    foreach (array_unique(array_column($forEasy, 's')) as $s) {
        expect($lastWhat[$s])->toBe(CURL_POLL_REMOVE, "socket {$s} was removed");
    }
    expect(curl_multi_getcontent($easy))->toBe('hello /socket')
        ->and(curl_getinfo($easy, CURLINFO_RESPONSE_CODE))->toBe(200);
});

it('completes several transfers on one multi and reports each once', function (): void {
    $easies = [];
    foreach (['/a', '/b', '/c'] as $path) {
        $easies[$path] = easyFor($path);
    }

    drive($this->multi, array_values($easies));

    $done = [];
    while ($info = curl_multi_info_read($this->multi)) {
        expect($info['msg'])->toBe(CURLMSG_DONE)->and($info['result'])->toBe(CURLE_OK);
        $done[] = $info['handle'];
    }
    expect($done)->toHaveCount(3);
    foreach ($easies as $path => $easy) {
        expect($done)->toContain($easy)->and(curl_multi_getcontent($easy))->toBe("hello {$path}");
    }
});

it('returns CURLM_RECURSIVE_API_CALL, not an exception, for a multi call made inside the callback', function (): void {
    $inside = [];
    drive($this->multi, [easyFor('/recursive')], function () use (&$inside): void {
        $inside[] = [Multi::curlMultiFdset($this->multi)['res'], Multi::curlMultiTimeout($this->multi)['res']];
    });

    expect($inside)->not->toBeEmpty()->and(array_unique($inside, SORT_REGULAR))->toBe([[CURLM_RECURSIVE_API_CALL, CURLM_RECURSIVE_API_CALL]]);
});

it('surfaces an exception thrown inside it from the pcurl call that fired it', function (): void {
    curl_multi_add_handle($this->multi, easyFor('/socket-throw'));
    Multi::curlMultiSetopt($this->multi, CURLMOPT_SOCKETFUNCTION, function (): int {
        throw new DomainException('socket boom');
    });

    $threw = false;
    try {
        $result = Multi::curlMultiSocketAction($this->multi, CURL_SOCKET_TIMEOUT, 0);
        // The connect may still be pending: drive on timeouts until the first socket appears.
        for ($end = microtime(true) + 5; $result['running_handles'] > 0 && microtime(true) < $end; usleep(5_000)) {
            $result = Multi::curlMultiSocketAction($this->multi, CURL_SOCKET_TIMEOUT, 0);
        }
    } catch (DomainException $e) {
        $threw = $e->getMessage() === 'socket boom';
    }

    expect($threw)->toBeTrue();
});
