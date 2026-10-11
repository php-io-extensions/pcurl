<?php

declare(strict_types=1);

use Pcurl\Multi;

beforeEach(function (): void {
    $this->multi = curl_multi_init();
});

it('reports no running handles on an idle multi', function (): void {
    expect(Multi::curlMultiSocketAction($this->multi, CURL_SOCKET_TIMEOUT, 0))->toBe(['res' => CURLM_OK, 'running_handles' => 0]);
});

it('counts the handles a timeout action starts', function (): void {
    curl_multi_add_handle($this->multi, easyFor('/one'));
    curl_multi_add_handle($this->multi, easyFor('/two'));

    expect(Multi::curlMultiSocketAction($this->multi, CURL_SOCKET_TIMEOUT, 0))->toBe(['res' => CURLM_OK, 'running_handles' => 2]);
});

it('ignores an action on a socket libcurl is not watching', function (): void {
    expect(Multi::curlMultiSocketAction($this->multi, 12345, CURL_CSELECT_IN))->toBe(['res' => CURLM_OK, 'running_handles' => 0]);
});

it('returns empty fd sets and max_fd -1 on an idle multi', function (): void {
    expect(Multi::curlMultiFdset($this->multi))->toBe([
        'res' => CURLM_OK,
        'read_fd_set' => [],
        'write_fd_set' => [],
        'exc_fd_set' => [],
        'max_fd' => -1,
    ]);
});

it('lists the sockets of a transfer in progress, matching what the socket callback reported', function (): void {
    $easy = easyFor('/fdset');
    $fdset = null;
    $reported = [];
    drive(
        $this->multi,
        [$easy],
        function (?CurlHandle $e, int $s, int $what) use (&$reported): void {
            if ($what !== CURL_POLL_REMOVE) {
                $reported[] = $s;
            }
        },
        onTick: function () use (&$fdset): void {
            $now = Multi::curlMultiFdset($this->multi);
            if ($fdset === null && $now['max_fd'] >= 0) {
                $fdset = $now;
            }
        },
    );

    expect($fdset)->not->toBeNull()->and($fdset['res'])->toBe(CURLM_OK)->and($fdset['exc_fd_set'])->toBe([]);
    $sockets = array_values(array_unique([...$fdset['read_fd_set'], ...$fdset['write_fd_set']]));
    expect($sockets)->not->toBeEmpty()
        ->and($fdset['max_fd'])->toBe(max($sockets))
        ->and(array_diff($sockets, $reported))->toBe([])
        ->and(curl_multi_getcontent($easy))->toBe('hello /fdset');
});

it('reports timeout -1 on an idle multi and a non-negative one with a transfer pending', function (): void {
    expect(Multi::curlMultiTimeout($this->multi))->toBe(['res' => CURLM_OK, 'timeout' => -1]);

    curl_multi_add_handle($this->multi, easyFor('/timeout'));

    $timeout = Multi::curlMultiTimeout($this->multi);
    expect($timeout['res'])->toBe(CURLM_OK)->and($timeout['timeout'])->toBeGreaterThanOrEqual(0);
});
