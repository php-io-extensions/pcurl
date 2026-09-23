# pcurl - libcurl's multi socket API for PHP

1:1 bindings for the parts of libcurl's multi interface that ext-curl leaves out, operating on ext-curl's own `CurlMultiHandle` / `CurlHandle` objects. No FFI, no opinions: each method is one `curl_multi_*` call, and libcurl constants (`CURLMOPT_*`, `CURL_POLL_*`, `CURL_CSELECT_*`, `CURL_SOCKET_TIMEOUT`) stay in your code.

## API

All static on `Pcurl\Multi`. Methods with C out-parameters return an array: `res` (the `CURLMcode`) plus the out-parameters under libcurl's own names.

| Method | libcurl | Returns |
|---|---|---|
| `curlMultiSetopt(CurlMultiHandle $m, int $option, mixed $value)` | `curl_multi_setopt` | `int` CURLMcode |
| `curlMultiSocketAction($m, int $s, int $evBitmask)` | `curl_multi_socket_action` | `['res', 'running_handles']` |
| `curlMultiFdset($m)` | `curl_multi_fdset` | `['res', 'read_fd_set', 'write_fd_set', 'exc_fd_set', 'max_fd']` |
| `curlMultiTimeout($m)` | `curl_multi_timeout` | `['res', 'timeout']` |
| `curlMultiPoll($m, array $extraFds, int $timeoutMs)` | `curl_multi_poll` | `['res', 'numfds', 'extra_fds']` |
| `curlMultiWait($m, array $extraFds, int $timeoutMs)` | `curl_multi_wait` | `['res', 'numfds', 'extra_fds']` |
| `curlMultiWakeup($m)` | `curl_multi_wakeup` | `int` CURLMcode |
| `curlMultiAssign($m, int $s, mixed $sockp)` | `curl_multi_assign` | `int` CURLMcode |
| `curlMultiStrerror(int $code)` | `curl_multi_strerror` | `string` |

`$extraFds` mirrors `struct curl_waitfd`: a list of `['fd' => int, 'events' => int]`; each comes back with `revents`.

`curlMultiSetopt` accepts every long option, plus:

* `CURLMOPT_SOCKETFUNCTION` (20001): `fn (?CurlHandle $easy, int $s, int $what, mixed $clientp, mixed $socketp): int`
* `CURLMOPT_TIMERFUNCTION` (20004): `fn (CurlMultiHandle $multi, int $timeoutMs, mixed $clientp): int`
* `CURLMOPT_SOCKETDATA` (10002) / `CURLMOPT_TIMERDATA` (10005): any PHP value, handed back as `$clientp`

`$socketp` is whatever you last passed to `curlMultiAssign` for that socket. A callback that throws returns `-1` to libcurl and the exception surfaces from the call that triggered it. Any other object/function option returns `CURLM_UNKNOWN_OPTION`.

pcurl's per-handle state is dropped when the `CurlMultiHandle` is freed. The cycle collector can't see the callbacks pcurl stores, though, so a callback that captures its own multi (`use ($m)`) keeps it alive until you set that option back to `null` or the request ends.

## Example

```php
$m = curl_multi_init();
Pcurl\Multi::curlMultiSetopt($m, 20004, fn ($multi, int $ms) => 0);               // CURLMOPT_TIMERFUNCTION
Pcurl\Multi::curlMultiSetopt($m, 20001, function ($easy, int $s, int $what) {    // CURLMOPT_SOCKETFUNCTION
    // watch fopen("php://fd/$s", 'r+') for $what, drop it on CURL_POLL_REMOVE (4)
    return 0;
});
curl_multi_add_handle($m, curl_init('https://example.com/'));
$r = Pcurl\Multi::curlMultiSocketAction($m, -1, 0);                               // CURL_SOCKET_TIMEOUT
```

## Requirements

* PHP 8.1+ (NTS or ZTS) with ext-curl loaded
* libcurl >= 7.68 — **the same libcurl ext-curl is linked against**
* Linux or macOS

## Compilation Requirements

* A C language toolchain, `pkg-config`
* Zephir PHP and ext-zephir_parser (manual installation only)

## Installation

```sh
pie install php-io-extensions/pcurl
```

Or from a checkout on macOS (`PHP_BIN` picks the build, e.g. a ZTS one):

```sh
bash install-macos.sh
PHP_BIN=/opt/homebrew/opt/php@8.4-zts/bin/php bash install-macos.sh
```

## License

MIT
