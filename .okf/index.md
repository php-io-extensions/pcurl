---
okf_version: "0.2"
---

# php-io-extensions/pcurl

PHP extension (Linux + macOS, NTS + ZTS): one Zephir static class `Pcurl\Multi` whose methods are 1:1 bindings for the libcurl multi calls ext-curl does not expose — `curl_multi_socket_action`, the `CURLMOPT_SOCKETFUNCTION`/`TIMERFUNCTION` callbacks, `curl_multi_fdset`, `curl_multi_timeout`, `curl_multi_poll`, `curl_multi_wait`, `curl_multi_wakeup`, `curl_multi_assign`, `curl_multi_strerror`. It works on ext-curl's own `CurlMultiHandle`/`CurlHandle` objects through a thin C ABI in `src/multi-api.{c,h}`. No FFI. Version **0.9.0**.

All concepts are `status: draft` pending Angel's verification.

# API

* [Pcurl\\Multi](api/multi.md) - Methods, return shapes, callback signatures

# Architecture

* [C ABI](architecture/c-abi.md) - `pcurl_multi_*`, handle access via `handlers->offset`, callback registry and lifetimes

# Build & packaging

* [Zephir + PIE install](build/zephir-and-pie.md) - installer, `clang/` overlays, NTS and ZTS

# Traps

* [Needs ext-curl first](traps/needs-ext-curl-first.md) - `ZEND_MOD_REQUIRED("curl")`, load order
* [Same libcurl as ext-curl](traps/same-libcurl.md) - a second libcurl in-process corrupts handles
* [Callback cycles](traps/callback-cycles.md) - a callback capturing its multi is invisible to GC; null it to break the cycle
* [zephir stubs regenerates ext/](traps/stubs-regenerates-ext.md) - run it before overlays are copied
* [Do not rebuild ext/ in place](traps/do-not-rebuild-in-place.md) - copy → build → sync → delete copy
