<?php

declare(strict_types=1);

use Pcurl\Multi;

beforeEach(function (): void {
    $this->multi = curl_multi_init();
});

it('returns CURLM_BAD_SOCKET for a socket libcurl is not tracking', function (): void {
    expect(Multi::curlMultiAssign($this->multi, 12345, 'anything'))->toBe(CURLM_BAD_SOCKET)
        ->and(Multi::curlMultiAssign($this->multi, 12345, null))->toBe(CURLM_BAD_SOCKET);
});

it('hands the assigned value back as $socketp on later callbacks for that socket, by identity', function (): void {
    $tag = new stdClass();
    $easy = easyFor('/assign');
    $seen = [];
    $codes = [];
    drive($this->multi, [$easy], function (?CurlHandle $e, int $s, int $what, mixed $clientp, mixed $socketp) use (&$seen, &$codes, $tag, $easy): void {
        if ($e !== $easy) {
            return;
        }
        $seen[] = $socketp;
        if ($what !== CURL_POLL_REMOVE && $socketp === null) {
            $codes[] = Multi::curlMultiAssign($this->multi, $s, $tag);
        }
    });

    expect($codes)->not->toBeEmpty()->and(array_unique($codes))->toBe([CURLM_OK])
        ->and($seen[0])->toBeNull()
        ->and(array_slice($seen, 1))->not->toBeEmpty();
    foreach (array_slice($seen, 1) as $socketp) {
        expect($socketp)->toBe($tag);
    }
    expect(curl_multi_getcontent($easy))->toBe('hello /assign');
});

it('clears the association when null is assigned', function (): void {
    $easy = easyFor('/unassign');
    $socket = null;
    $seen = [];
    // Follow the easy's first socket by number: libcurl's final CURL_POLL_REMOVE for it comes with $easy null.
    drive($this->multi, [$easy], function (?CurlHandle $e, int $s, int $what, mixed $clientp, mixed $socketp) use (&$seen, &$socket, $easy): void {
        $socket ??= $e === $easy ? $s : null;
        if ($s !== $socket) {
            return;
        }
        $seen[] = [$what, $socketp];
        if ($what === CURL_POLL_REMOVE) {
            return;
        }
        if ($socketp === null) {
            Multi::curlMultiAssign($this->multi, $s, 'first');
        } elseif ($socketp === 'first') {
            Multi::curlMultiAssign($this->multi, $s, null);
        }
    });

    $socketps = array_column($seen, 1);
    $firstAt = array_search('first', $socketps, true);
    expect($firstAt)->not->toBeFalse()
        ->and(array_slice($socketps, $firstAt + 1))->not->toBeEmpty()->toContain(null)
        ->and(end($seen)[0])->toBe(CURL_POLL_REMOVE)
        ->and(curl_multi_getcontent($easy))->toBe('hello /unassign');
});
