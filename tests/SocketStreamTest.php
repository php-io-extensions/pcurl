<?php

declare(strict_types=1);

use Pcurl\Multi;

it('wraps a duplicate of the descriptor in a readable and writable stream', function (): void {
    [$a, $b, $fdA] = socketPairWithFds();

    $stream = Multi::curlSocketStream($fdA);

    expect($stream)->toBeResource()->and(get_resource_type($stream))->toBe('stream');
    fwrite($b, 'ping');
    expect(fread($stream, 4))->toBe('ping');
    fwrite($stream, 'pong');
    expect(fread($b, 4))->toBe('pong');
});

it('is a duplicate of the socket, so closing it leaves the socket open', function (): void {
    [$a, $b, $fdA] = socketPairWithFds();

    $stream = Multi::curlSocketStream($fdA);
    expect(fstat($stream)['ino'])->toBe(fstat($a)['ino']);
    fclose($stream);

    fwrite($b, 'still');
    expect(fread($a, 5))->toBe('still');
});

it('works with stream_select', function (): void {
    [$a, $b, $fdA] = socketPairWithFds();
    $stream = Multi::curlSocketStream($fdA);

    $read = [$stream];
    $write = $except = null;
    expect(stream_select($read, $write, $except, 0, 10_000))->toBe(0);

    fwrite($b, 'x');
    $read = [$stream];
    expect(stream_select($read, $write, $except, 0, 500_000))->toBe(1)->and($read)->toBe([$stream]);
});

it('throws when the descriptor cannot be duplicated', function (): void {
    expect(fn () => Multi::curlSocketStream(9999))->toThrow(Exception::class, 'curl socket 9999 cannot be duplicated')
        ->and(fn () => Multi::curlSocketStream(-1))->toThrow(Exception::class, 'curl socket -1 cannot be duplicated');
});

it('stands in for php://fd on the sockets a transfer uses', function (): void {
    $multi = curl_multi_init();
    $easy = easyFor('/stream');
    $streams = [];
    drive($multi, [$easy], function (?CurlHandle $e, int $s, int $what) use (&$streams): void {
        if ($what !== CURL_POLL_REMOVE && ! isset($streams[$s])) {
            $streams[$s] = Multi::curlSocketStream($s);
        }
    });

    expect($streams)->not->toBeEmpty()->and(curl_multi_getcontent($easy))->toBe('hello /stream');
    foreach ($streams as $stream) {
        expect($stream)->toBeResource();
        fclose($stream);
    }
});
