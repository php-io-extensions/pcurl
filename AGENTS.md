# Agent guidance — php-io-extensions/pcurl

1. **Read [`.okf/index.md`](.okf/index.md) first** before changing architecture, API, or packaging.
2. Open only the concept files you need; prefer `status: stable` when present (all are currently `draft`).
3. This package is **libcurl multi-interface bindings, 1:1**. One public method = one `curl_multi_*` call, named after it (`curl_multi_socket_action` → `Pcurl\Multi::curlMultiSocketAction`). Out-parameters come back as `['res' => CURLMcode, <libcurl param name> => …]`. Linux + macOS; Windows excluded. No FFI. C lives in `src/multi-api.{c,h}` (`pcurl_multi_*`).
4. **Not opinionated.** No event loop, no helpers that aren't a libcurl call (e.g. fd → PHP stream is userland: `fopen("php://fd/$fd")`), no throwing on non-`CURLM_OK` codes. The only exceptions thrown are for a non-`CurlMultiHandle` argument or malformed `extra_fds`.
5. **Constants** (`CURLMOPT_*`, `CURL_POLL_*`, `CURL_CSELECT_*`, `CURL_SOCKET_TIMEOUT`) live **outside** the extension — callers keep them in their own int-backed enums. C may use libcurl's macros internally.
6. **Handles are ext-curl objects.** `CURLM*` / `CURL*` are read through `zend_object.handlers->offset` after an exact `curl_multi_ce` class check — see [`.okf/architecture/c-abi.md`](.okf/architecture/c-abi.md). Never widen that check.
7. **Must link the libcurl ext-curl uses** (pkg-config `libcurl`, resolved from the PHP binary by `install-macos.sh`). See [`.okf/traps/same-libcurl.md`](.okf/traps/same-libcurl.md).
8. **Never rebuild ship-ready `ext/` in place.** Now that `ext/` is committed it is PIE input: copy the tree → build in the copy → sync intentional artifacts back → delete the copy. See [`.okf/traps/do-not-rebuild-in-place.md`](.okf/traps/do-not-rebuild-in-place.md).
9. Every change must build and pass on **both NTS and ZTS** PHP (`PHP_BIN=… bash install-macos.sh`). Per-request state is `ZEND_TLS`.
10. When you learn a durable package fact, **update the matching `.okf` concept**, bump `generated.at`, and append `.okf/log.md`.
11. Keep the OKF bundle at package root only — never nest `.okf` under `pcurl/` or `src/`.
