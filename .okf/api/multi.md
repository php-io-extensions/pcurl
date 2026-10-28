---
type: API
title: Pcurl\Multi
description: Static 1:1 bindings for libcurl multi calls ext-curl leaves out
resource: /pcurl/multi.zep
tags: [pcurl, api, libcurl]
status: draft
generated: { by: claude-code/claude-opus-5.5, at: "2026-10-28T19:30:00Z" }
sources:
  - id: zep
    resource: /pcurl/multi.zep
    title: multi.zep
  - id: api-c
    resource: /src/multi-api.c
    title: multi-api.c
  - id: stub
    resource: /ide/0.11.0/Pcurl/Multi.php
    title: IDE stub
  - id: curl-8-5
    resource: https://github.com/curl/curl/blob/curl-8_5_0/lib/multi.c
    title: libcurl 8.5.0 lib/multi.c (multi_socket clears timer_lastcall; Curl_update_timer)
  - id: curl-14561
    resource: https://github.com/curl/curl/commit/a58b50fca6
    title: curl a58b50f, PR 14561, first released in 8.10.0
  - id: pest
    resource: /tests/Pest.php
    title: easyFor() in the Pest suite
---

# Shape

Every method is named after the C call it binds and takes ext-curl's `CurlMultiHandle` as `$multi`.[^zep] Calls with C out-parameters return `['res' => CURLMcode, <out-param names as in libcurl>]`; the rest return the `CURLMcode` (or string). Non-`CURLM_OK` codes are **returned, never thrown**.[^api-c]

| Method | libcurl | Returns |
|---|---|---|
| `curlMultiSetopt($m, int $option, mixed $value)` | `curl_multi_setopt` | `int` |
| `curlMultiSocketAction($m, int $s, int $evBitmask)` | `curl_multi_socket_action` | `res`, `running_handles` |
| `curlMultiFdset($m)` | `curl_multi_fdset` | `res`, `read_fd_set`, `write_fd_set`, `exc_fd_set` (int lists), `max_fd` |
| `curlMultiTimeout($m)` | `curl_multi_timeout` | `res`, `timeout` |
| `curlMultiPoll($m, array $extraFds, int $timeoutMs)` | `curl_multi_poll` | `res`, `numfds`, `extra_fds` |
| `curlMultiWait($m, array $extraFds, int $timeoutMs)` | `curl_multi_wait` | `res`, `numfds`, `extra_fds` |
| `curlMultiWakeup($m)` | `curl_multi_wakeup` | `int` |
| `curlMultiAssign($m, int $s, mixed $sockp)` | `curl_multi_assign` | `int` |
| `curlMultiStrerror(int $code)` | `curl_multi_strerror` | `string` |
| `curlSocketStream(int $s)` | `php_stream_fopen_from_fd(dup($s), "r+")` | `resource` |

`extra_fds` in and out mirrors `struct curl_waitfd`: `[['fd' => int, 'events' => int], …]` → each entry gains `revents`. A missing `events` key means 0; a missing `fd` throws.

# setopt

* Long options (`< CURLOPTTYPE_OBJECTPOINT`) go straight to `curl_multi_setopt`.
* `CURLMOPT_SOCKETFUNCTION` (20001): `callable(?CurlHandle $easy, int $s, int $what, mixed $clientp, mixed $socketp): int`. `$easy` is the ext-curl object found in the multi's handle list, or `null`.
* `CURLMOPT_TIMERFUNCTION` (20004): `callable(CurlMultiHandle $multi, int $timeoutMs, mixed $clientp): int`.
* `CURLMOPT_SOCKETDATA` (10002) / `CURLMOPT_TIMERDATA` (10005): any value, passed back as `$clientp`. libcurl's own data pointer is always pcurl's state, never the PHP value.
* `null` uninstalls a function; a non-callable returns `CURLM_BAD_FUNCTION_ARGUMENT` (10) and leaves the old one in place.
* Any other object/function option → `CURLM_UNKNOWN_OPTION` (6). `CURLMOPT_PUSHFUNCTION` stays with ext-curl's `curl_multi_setopt`.

A callback return of `null` counts as 0. A throw returns `-1` to libcurl, and the exception propagates out of the pcurl or ext-curl call that fired it. On libcurl older than 8.16, a timer callback that fails (throw or `-1`) inside `curl_multi_add_handle` leaves the rejected easy in libcurl's timer tree; the next call on that multi hangs or crashes. Fixed upstream in 8.16.0.

On libcurl older than 8.10.0, `curlMultiSocketAction($m, CURL_SOCKET_TIMEOUT, 0)` clears libcurl's record of the last timeout it reported, and when no expiry is left pending afterwards the timer callback is not called at all: no `-1`, and no chance for the callback to return `-1` or throw.[^curl-8-5] 8.10.0 keeps the last reported timeout across that action and sends the `-1`.[^curl-14561] A loop that drops its deadline when the timer fires is unaffected. A transfer timeout (`CURLOPT_TIMEOUT_MS`) keeps an expiry pending until the transfer finishes, so the callback fires the same way on every version; the suite's handles set one for that reason.[^pest]

# Errors

`Exception("expected a CurlMultiHandle")` for any `$multi` whose class is not exactly `CurlMultiHandle` (including a `CurlHandle`). `Exception` for malformed `extra_fds`.

# Userland conventions

libcurl constants are the caller's (int-backed enums), e.g. `CURL_SOCKET_TIMEOUT = -1`, `CURL_POLL_IN/OUT/INOUT/REMOVE = 1/2/3/4`, `CURL_CSELECT_IN/OUT/ERR = 1/2/4`. To watch a socket in a PHP loop, `curlSocketStream($s)` gives a dup'd stream usable with `stream_select` in any SAPI (`php://fd/$s` does the same under CLI only); close it on `CURL_POLL_REMOVE`.
