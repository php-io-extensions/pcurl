<?php

declare(strict_types=1);

use Pcurl\Multi;

it('returns libcurl\'s message for a CURLMcode', function (int $code, string $message): void {
    expect(Multi::curlMultiStrerror($code))->toBe($message);
})->with([
    [CURLM_OK, 'No error'],
    [CURLM_BAD_HANDLE, 'Invalid multi handle'],
    [CURLM_BAD_EASY_HANDLE, 'Invalid easy handle'],
    [CURLM_BAD_SOCKET, 'Invalid socket argument'],
    [CURLM_UNKNOWN_OPTION, 'Unknown option'],
    [CURLM_RECURSIVE_API_CALL, 'API function called from within callback'],
    [CURLM_BAD_FUNCTION_ARGUMENT, 'A libcurl function was given a bad argument'],
    [99, 'Unknown error'],
]);

it('names CURLM_ABORTED_BY_CALLBACK', function (): void {
    expect(Multi::curlMultiStrerror(CURLM_ABORTED_BY_CALLBACK))->toBe('Operation was aborted by an application callback');
})->skip(curl_version()['version_number'] < 0x075100, 'CURLM_ABORTED_BY_CALLBACK arrived in libcurl 7.81');
