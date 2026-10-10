# Directory Update Log

## 2026-10-09

* `extra.venusian.system` in composer.json: the apt packages `venusian build` installs to compile the extension, the run-time packages a `.deb` carrying it depends on or recommends beyond what `dpkg-shlibdeps` sees, and the Homebrew packages for a dev install.
* **Version**: 0.10.2 in `config.json`, `composer.json`, `PHP_PCURL_VERSION`. No API change; `ide/0.10.1/` stays current. Carries the Linux link fix below, which 0.10.1 shipped without.

## 2026-10-08
* **Version**: 0.10.1 in `config.json`, `composer.json`, `PHP_PCURL_VERSION`; `ide/0.10.1/` replaces `ide/0.10.0/`.
* **Decision reversed (Angel)**: `curlSocketStream(int $s)` added. The 2026-09-22 "no stream helper, userland uses `php://fd/N`" call predates the measurement that PHP serves `php://fd` under the CLI SAPI only: inside a phpmicro binary (`PHP_SAPI` `micro`) the framework's pcurl driver could not wrap curl's sockets, threw from the socket callback, and curl aborted the multi handle (`CURLM_ABORTED_BY_CALLBACK` on every later add). The method is `dup` + `php_stream_fopen_from_fd`, what `php://fd` does without the gate.
* **Linux link**: the shared extension links no libcurl on Linux either; the `darwin*` case in both `config.m4` files is gone. A linked `libcurl.so.4` made pcurl's versioned references bind to the system library inside a static glibc micro runtime (a `venusian build` for Linux) while ext-curl's handles came from the runtime's libcurl; the first socket action segfaulted. Unversioned references bind to whichever libcurl the process carries.
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
