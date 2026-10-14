---
type: API
title: Pcurl\Multi
description: Static 1:1 bindings for libcurl multi calls ext-curl leaves out
resource: /pcurl/multi.zep
tags: [pcurl, api, libcurl]
status: draft
generated: { by: cursor-agent/grok-4.6, at: "2026-10-14T13:30:00Z" }
sources:
  - id: zep
    resource: /pcurl/multi.zep
    title: multi.zep
  - id: api-c
    resource: /src/multi-api.c
    title: multi-api.c
  - id: stub
    resource: /ide/0.10.0/Pcurl/Multi.php
    title: IDE stub
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
* `CURLMOPT_TIMERFUNCTION` (20004): `callable(CurlMultiHandle $multi, int $timeoutMs, mixed $clientp): int`. libcurl invokes it when the timeout it wants changes, not on every `curl_multi_socket_action(CURL_SOCKET_TIMEOUT)`. On 8.5 that TIMEOUT action clears `timer_lastcall` then skips the callback if the new timeout is `-1`; 8.11+ keeps `last_timeout_ms` and still reports `-1`. The suite's timer tests set `CURLOPT_TIMEOUT_MS` so both trees have an expire to report.
* `CURLMOPT_SOCKETDATA` (10002) / `CURLMOPT_TIMERDATA` (10005): any value, passed back as `$clientp`. libcurl's own data pointer is always pcurl's state, never the PHP value.
* `null` uninstalls a function; a non-callable returns `CURLM_BAD_FUNCTION_ARGUMENT` (10) and leaves the old one in place.
* Any other object/function option → `CURLM_UNKNOWN_OPTION` (6). `CURLMOPT_PUSHFUNCTION` stays with ext-curl's `curl_multi_setopt`.

A callback return of `null` counts as 0. A throw returns `-1` to libcurl, and the exception propagates out of the pcurl or ext-curl call that fired it. On libcurl older than 8.16, a timer callback that fails (throw or `-1`) inside `curl_multi_add_handle` leaves the rejected easy in libcurl's timer tree; the next call on that multi hangs or crashes. Fixed upstream in 8.16.0.

# Errors

`Exception("expected a CurlMultiHandle")` for any `$multi` whose class is not exactly `CurlMultiHandle` (including a `CurlHandle`). `Exception` for malformed `extra_fds`.

# Userland conventions

libcurl constants are the caller's (int-backed enums), e.g. `CURL_SOCKET_TIMEOUT = -1`, `CURL_POLL_IN/OUT/INOUT/REMOVE = 1/2/3/4`, `CURL_CSELECT_IN/OUT/ERR = 1/2/4`. To watch a socket in a PHP loop, `curlSocketStream($s)` gives a dup'd stream usable with `stream_select` in any SAPI (`php://fd/$s` does the same under CLI only); close it on `CURL_POLL_REMOVE`.
