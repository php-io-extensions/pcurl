<?php

declare(strict_types=1);

use Pcurl\Multi;

it('rejects anything but a CurlMultiHandle as $multi', function (callable $call): void {
    expect(fn () => $call(curl_init()))->toThrow(Exception::class, 'expected a CurlMultiHandle')
        ->and(fn () => $call(new stdClass()))->toThrow(Exception::class, 'expected a CurlMultiHandle')
        ->and(fn () => $call('multi'))->toThrow(Exception::class, 'expected a CurlMultiHandle')
        ->and(fn () => $call(null))->toThrow(Exception::class, 'expected a CurlMultiHandle');
})->with([
    'curlMultiSetopt' => fn (mixed $m) => Multi::curlMultiSetopt($m, CURLMOPT_MAXCONNECTS, 1),
    'curlMultiSocketAction' => fn (mixed $m) => Multi::curlMultiSocketAction($m, CURL_SOCKET_TIMEOUT, 0),
    'curlMultiFdset' => fn (mixed $m) => Multi::curlMultiFdset($m),
    'curlMultiTimeout' => fn (mixed $m) => Multi::curlMultiTimeout($m),
    'curlMultiPoll' => fn (mixed $m) => Multi::curlMultiPoll($m, [], 0),
    'curlMultiWait' => fn (mixed $m) => Multi::curlMultiWait($m, [], 0),
    'curlMultiWakeup' => fn (mixed $m) => Multi::curlMultiWakeup($m),
    'curlMultiAssign' => fn (mixed $m) => Multi::curlMultiAssign($m, 0, null),
]);

it('exposes exactly the ten bound methods, all static', function (): void {
    $methods = array_map(fn (ReflectionMethod $m) => $m->getName(), (new ReflectionClass(Multi::class))->getMethods());
    sort($methods);

    expect($methods)->toBe([
        'curlMultiAssign', 'curlMultiFdset', 'curlMultiPoll', 'curlMultiSetopt', 'curlMultiSocketAction',
        'curlMultiStrerror', 'curlMultiTimeout', 'curlMultiWait', 'curlMultiWakeup', 'curlSocketStream',
    ]);
    foreach ((new ReflectionClass(Multi::class))->getMethods() as $method) {
        expect($method->isStatic())->toBeTrue($method->getName() . ' is static');
    }
});
