<?php

declare(strict_types=1);

use Pcurl\Multi;

beforeEach(function (): void {
    $this->multi = curl_multi_init();
});

it('passes long options through to curl_multi_setopt', function (): void {
    expect(Multi::curlMultiSetopt($this->multi, CURLMOPT_MAXCONNECTS, 4))->toBe(CURLM_OK)
        ->and(Multi::curlMultiSetopt($this->multi, CURLMOPT_MAX_HOST_CONNECTIONS, 2))->toBe(CURLM_OK)
        ->and(Multi::curlMultiSetopt($this->multi, CURLMOPT_PIPELINING, 0))->toBe(CURLM_OK);
});

it('returns libcurl\'s CURLM_UNKNOWN_OPTION for a long option libcurl does not know', function (): void {
    expect(Multi::curlMultiSetopt($this->multi, 999, 1))->toBe(CURLM_UNKNOWN_OPTION);
});

it('returns CURLM_UNKNOWN_OPTION for object options other than the four it binds', function (): void {
    expect(Multi::curlMultiSetopt($this->multi, CURLMOPT_PUSHFUNCTION, fn () => 0))->toBe(CURLM_UNKNOWN_OPTION);
});

it('accepts a callable or null for the function options', function (int $option): void {
    expect(Multi::curlMultiSetopt($this->multi, $option, fn () => 0))->toBe(CURLM_OK)
        ->and(Multi::curlMultiSetopt($this->multi, $option, 'strlen'))->toBe(CURLM_OK)
        ->and(Multi::curlMultiSetopt($this->multi, $option, null))->toBe(CURLM_OK);
})->with(['CURLMOPT_SOCKETFUNCTION' => CURLMOPT_SOCKETFUNCTION, 'CURLMOPT_TIMERFUNCTION' => CURLMOPT_TIMERFUNCTION]);

it('returns CURLM_BAD_FUNCTION_ARGUMENT for a non-callable function option', function (int $option): void {
    expect(Multi::curlMultiSetopt($this->multi, $option, 'no_such_function'))->toBe(CURLM_BAD_FUNCTION_ARGUMENT)
        ->and(Multi::curlMultiSetopt($this->multi, $option, 42))->toBe(CURLM_BAD_FUNCTION_ARGUMENT)
        ->and(Multi::curlMultiSetopt($this->multi, $option, new stdClass()))->toBe(CURLM_BAD_FUNCTION_ARGUMENT);
})->with(['CURLMOPT_SOCKETFUNCTION' => CURLMOPT_SOCKETFUNCTION, 'CURLMOPT_TIMERFUNCTION' => CURLMOPT_TIMERFUNCTION]);

it('keeps the installed callback when a non-callable is rejected', function (): void {
    $fired = 0;
    Multi::curlMultiSetopt($this->multi, CURLMOPT_TIMERFUNCTION, function () use (&$fired): int {
        $fired++;

        return 0;
    });
    expect(Multi::curlMultiSetopt($this->multi, CURLMOPT_TIMERFUNCTION, 'no_such_function'))->toBe(CURLM_BAD_FUNCTION_ARGUMENT);

    // libcurl asks for a timer as soon as a handle is added.
    curl_multi_add_handle($this->multi, easyFor('/kept'));

    expect($fired)->toBeGreaterThan(0);
});

it('uninstalls a callback with null', function (): void {
    $fired = 0;
    Multi::curlMultiSetopt($this->multi, CURLMOPT_TIMERFUNCTION, function () use (&$fired): int {
        $fired++;

        return 0;
    });
    expect(Multi::curlMultiSetopt($this->multi, CURLMOPT_TIMERFUNCTION, null))->toBe(CURLM_OK);

    curl_multi_add_handle($this->multi, easyFor('/uninstalled'));

    expect($fired)->toBe(0);
});

it('accepts any value for the data options and hands it back as $clientp by identity', function (): void {
    $socketData = new stdClass();
    $timerData = ['timer' => true];
    expect(Multi::curlMultiSetopt($this->multi, CURLMOPT_SOCKETDATA, $socketData))->toBe(CURLM_OK)
        ->and(Multi::curlMultiSetopt($this->multi, CURLMOPT_TIMERDATA, $timerData))->toBe(CURLM_OK);

    $seen = ['socket' => [], 'timer' => []];
    drive(
        $this->multi,
        [easyFor('/data')],
        function (?CurlHandle $easy, int $s, int $what, mixed $clientp) use (&$seen): void {
            $seen['socket'][] = $clientp;
        },
        function (CurlMultiHandle $m, int $ms, mixed $clientp) use (&$seen): void {
            $seen['timer'][] = $clientp;
        },
    );

    expect($seen['socket'])->not->toBeEmpty()->and($seen['timer'])->not->toBeEmpty();
    foreach ($seen['socket'] as $clientp) {
        expect($clientp)->toBe($socketData);
    }
    foreach ($seen['timer'] as $clientp) {
        expect($clientp)->toBe($timerData);
    }
});

it('hands null as $clientp when no data option was set', function (): void {
    $seen = [];
    drive($this->multi, [easyFor('/nodata')], function (?CurlHandle $easy, int $s, int $what, mixed $clientp, mixed $socketp) use (&$seen): void {
        $seen[] = [$clientp, $socketp];
    });

    expect($seen)->not->toBeEmpty()->and(array_unique($seen, SORT_REGULAR))->toBe([[null, null]]);
});
