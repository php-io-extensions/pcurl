# Directory Update Log

## 2026-10-28

* **Version**: 0.11.0 in `config.json`, `composer.json`, `PHP_PCURL_VERSION`; `ide/0.11.0/` replaces `ide/0.10.5/` (identical stubs). Promoted with the Venusian 0.11 ecosystem. No API or behaviour change; built and loaded on Homebrew PHP 8.4 NTS and ZTS.

## 2026-10-12

* **Version**: 0.10.5 in `config.json`, `composer.json`, `PHP_PCURL_VERSION`. No API change; `ide/0.10.5/` generated with `zephir stubs`, identical to `ide/0.10.1/`. Steps past the 0.10.3 and 0.10.4 numbers to line up with the sibling extensions.
* **README**: rewritten on the php-io-extensions template (header badges, two-part basic usage, requirements table, PIE / install script / phpize install paths, API, constants the caller keeps, OKF section, suite run). The usage example and the suite count (73 passed, 382 assertions) were run against a build from a copy of `ext/` on Homebrew PHP 8.4 NTS, libcurl 8.22.0.
* **SECURITY.md** added: pre-1.0 support table, private reporting, and the security model (handle class check, callback state lifetime, `dup` in `curlSocketStream`, `extra_fds` validation, no linked libcurl).

## 2026-10-11

* **Tests**: Pest v4 suite in `tests/` (`phpunit.xml`, `require-dev` `pestphp/pest`), 73 tests over every `Pcurl\Multi` method. Transfers run against a PHP built-in server on `tests/fixtures`, driven through `CURLMOPT_SOCKETFUNCTION`/`TIMERFUNCTION` + `curlSocketStream` + `stream_select` (`drive()` in `tests/Pest.php`, which installs its callbacks before adding handles). CI runs it after the load check. Green on Homebrew PHP 8.4 NTS and ZTS (libcurl 8.22.0) and the Pi 5 (PHP 8.4.20 ZTS, libcurl 8.14.1, one test skipped, see below), each against 0.10.2 built from a copy of `ext/`.
* **libcurl < 8.16 add_handle bug**: `curl_multi_add_handle` inserts the easy into the multi's timer tree, then calls the timer callback; when that callback fails, 8.15 and older drop the easy from the transfer table but leave its timer node, and the next call on the multi hangs or segfaults (reproduced on the Pi, libcurl 8.14.1). 8.16.0 replaced the insert with dirty marking. A pcurl timer callback that throws or returns -1 inside `curl_multi_add_handle` hits it. The suite's test for that path skips below 8.16.
* **libcurl floor 7.69.0** (was 7.68.0) in `clang/config.m4`, `ext/config.m4`, README: `src/multi-api.c` returns `CURLM_BAD_FUNCTION_ARGUMENT`, which libcurl added in 7.69.0, so 7.68 passed configure and failed to compile. The suite skips its `CURLM_ABORTED_BY_CALLBACK` (libcurl 7.81) assertions below 7.81.
* **libcurl behaviour the suite pins**: the timer callback fires only when the timeout libcurl wants changes (8.14 reports -1 while a connect waits on its socket, so an unchanged -1 is never repeated); `curl_multi_wait` with no descriptor returns at once while `curl_multi_poll` sleeps the timeout; a multi call made inside a pcurl callback returns `CURLM_RECURSIVE_API_CALL` (8); a callback returning `-1` or throwing leaves libcurl answering `CURLM_ABORTED_BY_CALLBACK` (11) to every later add and socket action on that multi.

## 2026-10-09

* **Version**: 0.10.2 in `config.json`, `composer.json`, `PHP_PCURL_VERSION`. No API change; `ide/0.10.1/` stays current. Carries the Linux link fix below, which 0.10.1 shipped without.

## 2026-10-08
* **Version**: 0.10.1 in `config.json`, `composer.json`, `PHP_PCURL_VERSION`; `ide/0.10.1/` replaces `ide/0.10.0/`.
* **Decision reversed (Angel)**: `curlSocketStream(int $s)` added. The 2026-09-22 "no stream helper, userland uses `php://fd/N`" call predates the measurement that PHP serves `php://fd` under the CLI SAPI only: inside a phpmicro binary (`PHP_SAPI` `micro`) the framework's pcurl driver could not wrap curl's sockets, threw from the socket callback, and curl aborted the multi handle (`CURLM_ABORTED_BY_CALLBACK` on every later add). The method is `dup` + `php_stream_fopen_from_fd`, what `php://fd` does without the gate.
* **Linux link**: the shared extension links no libcurl on Linux either; the `darwin*` case in both `config.m4` files is gone. A linked `libcurl.so.4` made pcurl's versioned references bind to the system library inside a static glibc micro runtime while ext-curl's handles came from the runtime's libcurl; the first socket action segfaulted. Unversioned references bind to whichever libcurl the process carries.
* **macOS link**: the shared extension links no libcurl on Darwin (`clang/config.m4`, `ext/config.m4`); `curl_multi_*` resolve from the host at load time, so pcurl always calls the libcurl ext-curl was built with (Homebrew's dylib, or the static one inside a micro binary) and never a second copy.

## 2026-09-29
* **Version**: 0.10.0 in `config.json`, `composer.json`, and `PHP_PCURL_VERSION` (`ext/php_pcurl.h`), for Venusian 0.10's Http driver. Regenerating `ext/` changed no tracked source. `ide/0.10.0/` replaces `ide/0.9.0/`.
* **Build**: Homebrew PHP 8.4 NTS (`php84`) and ZTS (`zhp`) from a disposable copy; the Pi 5 (PHP 8.4.20 ZTS, libcurl 8.14.1) with `phpize` from the committed `ext/`, installed with `conf.d/40-pcurl.ini`. The framework's Http suite passes on the Pi under epoll, pcurl driver included.

## 2026-09-23
* **Version**: Package version set to 0.9.0 in `config.json`, `composer.json`, and `PHP_PCURL_VERSION` (`ext/php_pcurl.h`). phpize/make products removed from `ext/`; installs for Homebrew PHP 8.4 NTS (`php84`) and ZTS (`zhp`) run from a disposable copy so the ship tree stays source-only.
* **Stubs**: `zephir stubs` for 0.9.0 wrote `ide/0.9.0/` and reverted the clang patches in `ext/kernel/{file,main,require}.c` (`zval_ptr_dtor` / `!= NULL`). Those three files were restored from `clang/kernel/`. `ide/0.1.0/` removed.

## 2026-09-22
* **Initialization**: Created `php-io-extensions/pcurl` 0.1.0 from posi's scaffold (LICENSE, installers, `clang/` kernel overlays, `.gitattributes`) and the OKF bundle (index, api/multi, architecture/c-abi, build/zephir-and-pie, five traps). All `status: draft`.
* **Decision (Angel)**: 1:1 bindings only, methods named after the C call (`curlMultiSocketAction`), callbacks set through `curlMultiSetopt(CURLMOPT_*FUNCTION)`, no `streamFor` helper (userland uses `php://fd/N`), bound set = socket_action, setopt, fdset, poll + timeout, wait, wakeup, assign, strerror.
* **Fix (output-validator review)**: registry entries were only freed at RSHUTDOWN, so a freed multi's `*DATA`/`assign` state could leak into a new multi at the same `CURLM*`. Replaced the "hold a strong ref while a `*FUNCTION` is set" scheme with a `free_obj` hook on a copied handler table (unhook → ext-curl free → drop state). Also: `curl_ce` check on `easyh` entries, `FD_SETSIZE` cap in fdset, `;;` in RSHUTDOWN, unused header in the strerror optimizer. Trap `callbacks-hold-the-handle` became `callback-cycles`. Rebuilt in a copy per the playbook: 26/26 smoke on NTS and ZTS, including 200 address-reuse rounds, and the ZTS 4-thread stress still passes.
* **Build**: first `ext/` generated in place (the tree did not exist yet, by Angel's instruction); installed and smoke-tested on Homebrew PHP 8.4.25 NTS and ZTS (24/24 checks each; ZTS also 4 `parallel\Runtime` threads × 25 transfers).
