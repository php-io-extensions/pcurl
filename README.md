<div align="center" id="header">

<h1>ext-pcurl</h1>

<h3>libcurl's multi socket API, bound 1:1 on ext-curl's own handles, for PHP 8.1+</h3>


[![CI](https://img.shields.io/github/actions/workflow/status/php-io-extensions/pcurl/ci.yml?style=flat-square&label=CI)](https://github.com/php-io-extensions/pcurl/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/php-io-extensions/pcurl?style=flat-square)](https://packagist.org/packages/php-io-extensions/pcurl)
[![PHP](https://img.shields.io/packagist/dependency-v/php-io-extensions/pcurl/php?style=flat-square&label=PHP)](https://packagist.org/packages/php-io-extensions/pcurl)
[![License](https://img.shields.io/github/license/php-io-extensions/pcurl?style=flat-square)](LICENSE)
[![Platform](https://img.shields.io/badge/platform-linux_%7C_macos-blue?style=flat-square&logo=linux&logoColor=white)](https://curl.se/libcurl/c/libcurl-multi.html)
[![OKF](https://img.shields.io/badge/OKF-0.2-6f42c1?style=flat-square&label=knowledge%20bundle)](.okf/index.md)
[![PIE](https://img.shields.io/badge/PIE-installable-4f5b93?style=flat-square&logo=php&logoColor=white)](https://github.com/php/pie)

</div>

PHP's ext-curl ships libcurl's multi interface, so one process can run many transfers at once. But it ships the half that waits for you: `curl_multi_select()` blocks on curl's sockets and nothing else, and `curl_multi_exec()` has to be called in a loop to find out whether anything moved. The transfers run fine. They just can't share a wait with anything that isn't curl: a listening socket, a serial port, a timer, a pipe from a child process. A program that needs both ends up polling, or sleeping, or writing two loops that take turns.

libcurl solved this years ago with the <b>multi socket API</b>: `curl_multi_socket_action()`. Instead of libcurl waiting, it tells you. Two callbacks, `CURLMOPT_SOCKETFUNCTION` ("watch this socket for reading" / "stop watching it") and `CURLMOPT_TIMERFUNCTION` ("call me back in 50 ms"), and one call to report back: "socket 7 is readable" or "your timer fired". You own the wait. libcurl's sockets go into whatever your program already blocks on, `stream_select()`, epoll, kqueue, and curl is one more thing in the set, not the thing the set revolves around. That's how every serious curl-based server and client in C is written.

<b>The only problem?</b> ext-curl never binds it. The socket API, `curl_multi_poll()` with extra descriptors, `curl_multi_wakeup()` and `curl_multi_assign()` are all absent from PHP. This extension adds them, as static methods on `Pcurl\Multi` that take ext-curl's own `CurlMultiHandle` and `CurlHandle` objects. One method is one `curl_multi_*` call, named after it, returning what C returns. No event loop, no helpers, no constants of its own: you keep writing `curl_init()`, `curl_multi_add_handle()` and `curl_multi_info_read()` exactly as before, and pcurl covers the calls in between.

## Basic usage

Most ways you will interface with pcurl are in two parts -

Install the two callbacks on an ordinary multi handle. The socket callback hands you a socket number and what libcurl wants watched on it; `curlSocketStream()` turns that number into a stream so `stream_select()` can wait on it. The timer callback hands you a deadline. Then add handles the ext-curl way.

```php
use Pcurl\Multi;

// libcurl's values, from <curl/multi.h>. pcurl defines none of them; keep them in your own code.
const CURLMOPT_SOCKETFUNCTION = 20001;
const CURLMOPT_TIMERFUNCTION  = 20004;
const CURL_POLL_IN     = 1;
const CURL_POLL_OUT    = 2;
const CURL_POLL_REMOVE = 4;
const CURL_CSELECT_IN  = 1;
const CURL_CSELECT_OUT = 2;
const CURL_SOCKET_TIMEOUT = -1;

$multi = curl_multi_init();
$watched = [];       // curl socket number => ['stream' => resource, 'what' => CURL_POLL_*]
$timerDue = null;    // when libcurl next wants CURL_SOCKET_TIMEOUT, as microtime(true); null = never

// libcurl tells you which socket to watch, and for what. Wrap it in a stream so stream_select() can wait on it.
Multi::curlMultiSetopt($multi, CURLMOPT_SOCKETFUNCTION, function (?CurlHandle $easy, int $s, int $what, mixed $clientp, mixed $socketp) use (&$watched): int {
    if ($what === CURL_POLL_REMOVE) {
        fclose($watched[$s]['stream']);   // closes the duplicate; curl still owns the socket
        unset($watched[$s]);
        return 0;
    }
    $watched[$s] ??= ['stream' => Multi::curlSocketStream($s)];
    $watched[$s]['what'] = $what;
    return 0;
});

// libcurl tells you how long it is willing to wait before you must call it back with CURL_SOCKET_TIMEOUT.
Multi::curlMultiSetopt($multi, CURLMOPT_TIMERFUNCTION, function (CurlMultiHandle $m, int $timeoutMs, mixed $clientp) use (&$timerDue): int {
    $timerDue = $timeoutMs < 0 ? null : microtime(true) + $timeoutMs / 1000;
    return 0;
});

foreach (['https://example.com/', 'https://example.org/'] as $url) {
    $easy = curl_init($url);
    curl_setopt($easy, CURLOPT_RETURNTRANSFER, true);
    curl_multi_add_handle($multi, $easy);
}
```

Kick libcurl once with `CURL_SOCKET_TIMEOUT`, then loop: wait on the watched streams, report what happened on each socket with `curlMultiSocketAction()`, report the timer when it is due, and collect finished transfers with `curl_multi_info_read()` as usual. The loop ends when `running_handles` reaches zero. Anything else your program waits on goes into the same `stream_select()`.

```php
// Kick libcurl once; from here on it drives you through the two callbacks.
$r = Multi::curlMultiSocketAction($multi, CURL_SOCKET_TIMEOUT, 0);

while ($r['running_handles'] > 0) {
    $read = $write = [];
    foreach ($watched as $s => ['stream' => $stream, 'what' => $what]) {
        if ($what & CURL_POLL_IN)  { $read[$s]  = $stream; }
        if ($what & CURL_POLL_OUT) { $write[$s] = $stream; }
    }

    $except = null;
    if ($read || $write) {
        stream_select($read, $write, $except, 0, 20_000);   // keys survive: $s comes back with each ready stream
    } else {
        usleep(20_000);
    }

    // Tell libcurl what happened on which socket. It runs the transfers forward and calls back as needed.
    foreach ($read as $s => $stream)  { $r = Multi::curlMultiSocketAction($multi, $s, CURL_CSELECT_IN); }
    foreach ($write as $s => $stream) { $r = Multi::curlMultiSocketAction($multi, $s, CURL_CSELECT_OUT); }
    if ($timerDue !== null && microtime(true) >= $timerDue) {
        $timerDue = null;
        $r = Multi::curlMultiSocketAction($multi, CURL_SOCKET_TIMEOUT, 0);
    }

    // Finished transfers are read the ext-curl way.
    while ($msg = curl_multi_info_read($multi)) {
        $easy = $msg['handle'];
        echo curl_getinfo($easy, CURLINFO_EFFECTIVE_URL), ': ', curl_getinfo($easy, CURLINFO_RESPONSE_CODE),
            ', ', strlen(curl_multi_getcontent($easy)), " bytes\n";
        curl_multi_remove_handle($multi, $easy);
    }
}
```

```console
$ php example.php
https://example.com/: 200, 577 bytes
https://example.org/: 200, 577 bytes
```

The 20 ms cap on the wait is only there because `stream_select()` takes seconds and microseconds while libcurl's timer is a deadline; a loop built on [ext-epoll](https://github.com/php-io-extensions/epoll) or [ext-kqueue](https://github.com/php-io-extensions/kqueue) registers the socket number `$s` directly and uses the timer as its wait timeout, no stream and no cap.

## Requirements

| | |
|---|---|
| OS | Linux or macOS. Windows is excluded in `composer.json`. |
| PHP | 8.1 or newer, NTS or ZTS. CI builds and tests 8.4 and 8.5. |
| Extensions | ext/curl, which ships with PHP. pcurl requires it and loads after it; every handle it takes is one ext-curl made. |
| Libraries | libcurl headers, 7.69.0 or newer, at build time. At run time the module links no libcurl of its own: its `curl_multi_*` calls resolve to the libcurl already in the process, the one ext-curl's handles came from, so the two can never disagree. |

To build it you need a C compiler, `pkg-config`, the libcurl headers and the development files of the PHP you intend to load it into. Homebrew's `curl` is keg-only, so point `pkg-config` at it:

```bash
# Debian, Ubuntu, Raspberry Pi OS
sudo apt install build-essential pkg-config libcurl4-openssl-dev php8.4-dev

# macOS: Xcode Command Line Tools, Homebrew's PHP ships phpize and php-config
xcode-select --install
brew install php@8.4 curl pkgconf
export PKG_CONFIG_PATH="$(brew --prefix curl)/lib/pkgconfig"
```

## Installation

### With PHP PIE (recommended)

[PIE](https://github.com/php/pie) is the PHP Foundation's installer for extensions. It downloads the tagged source from Packagist, builds the committed `ext/` tree against the PHP on your `PATH`, installs the module and enables it. No Zephir is needed: the generated C is in the repository. `sudo` where your PHP's extension directory is root-owned, as on Debian; not on a Homebrew Mac:

```bash
pie install php-io-extensions/pcurl
```

### Manually (install script)

The repository ships `install-macos.sh`, the maintainer's build. It regenerates `ext/` from the Zephir source with [Zephir](https://zephir-lang.com/) (`composer global require phalcon/zephir`), resolves `php-config` and the libcurl headers from the PHP it targets (`otool -L` on the binary, Homebrew's `curl` as the fallback), builds, installs `pcurl.so` into that PHP's `extension_dir`, ad-hoc signs it so macOS loads it on first use, writes `40-pcurl.ini` into its `conf.d` scan directory, and checks that `Pcurl\Multi` exists. `PHP_BIN` picks the build; run it once per PHP:

```bash
git clone https://github.com/php-io-extensions/pcurl.git
cd pcurl
bash install-macos.sh                                            # the php on PATH
PHP_BIN=/opt/homebrew/opt/php@8.4-zts/bin/php bash install-macos.sh
```

### Manually (phpize)

The committed `ext/` is a complete phpize tree and needs no Zephir. Build it in a copy so the checkout stays what PIE installs:

```bash
git clone https://github.com/php-io-extensions/pcurl.git
cp -R pcurl/ext pcurl-build
cd pcurl-build
phpize
./configure --enable-pcurl
make -j"$(nproc 2>/dev/null || sysctl -n hw.ncpu)"
sudo make install
```

Then enable it: add `extension=pcurl.so` to your `php.ini`, or drop that line into a file in the scan directory that `php --ini` reports. Where ext/curl is a shared extension, as on Debian packaging, that file must sort after the one that loads `curl`; `40-pcurl.ini` next to `20-curl.ini` does.

### Verify

```console
$ php --ri pcurl

pcurl


libcurl's multi socket API bound 1:1 into PHP on top of ext-curl handles
pcurl => enabled
Author => Project Saturn Studios, LLC
Version => 0.11.x
Build Date => Oct 12 2026 13:41:34
Powered by Zephir => Version 1.6.1-$Id$
```

## API

Every method is static on `Pcurl\Multi`, named after the libcurl call it binds, and takes ext-curl's `CurlMultiHandle` as `$multi`. A call with C out-parameters returns an array: `res` (the `CURLMcode`) plus the out-parameters under libcurl's own names. The rest return the `CURLMcode` itself, or the string. A code other than `CURLM_OK` is returned, never thrown; the only exceptions are for a `$multi` that is not exactly a `CurlMultiHandle` (a `CurlHandle` included) and for a malformed `$extraFds` entry.

| Method | libcurl | Returns |
|---|---|---|
| `curlMultiSetopt($multi, int $option, mixed $value): int` | [`curl_multi_setopt`](https://curl.se/libcurl/c/curl_multi_setopt.html) | `CURLMcode`. See [setopt](#setopt) below for the options pcurl adds to ext-curl's. |
| `curlMultiSocketAction($multi, int $s, int $evBitmask): array` | [`curl_multi_socket_action`](https://curl.se/libcurl/c/curl_multi_socket_action.html) | `['res', 'running_handles']`. `$s` is a socket number from the socket callback with a `CURL_CSELECT_*` mask, or `CURL_SOCKET_TIMEOUT` with `0` when the timer is due. |
| `curlMultiFdset($multi): array` | [`curl_multi_fdset`](https://curl.se/libcurl/c/curl_multi_fdset.html) | `['res', 'read_fd_set', 'write_fd_set', 'exc_fd_set', 'max_fd']`. The three sets are lists of socket numbers, `max_fd` is `-1` when libcurl has nothing to watch. Bounded by `FD_SETSIZE`, as the C call is. |
| `curlMultiTimeout($multi): array` | [`curl_multi_timeout`](https://curl.se/libcurl/c/curl_multi_timeout.html) | `['res', 'timeout']`, milliseconds; `-1` means no timeout is set. |
| `curlMultiPoll($multi, array $extraFds, int $timeoutMs): array` | [`curl_multi_poll`](https://curl.se/libcurl/c/curl_multi_poll.html) | `['res', 'numfds', 'extra_fds']`. Waits on curl's sockets and on yours; with nothing to wait on it sleeps the full timeout, and `curlMultiWakeup()` ends the wait early. |
| `curlMultiWait($multi, array $extraFds, int $timeoutMs): array` | [`curl_multi_wait`](https://curl.se/libcurl/c/curl_multi_wait.html) | As `curlMultiPoll()`, but with nothing to wait on it returns at once. |
| `curlMultiWakeup($multi): int` | [`curl_multi_wakeup`](https://curl.se/libcurl/c/curl_multi_wakeup.html) | `CURLMcode`. Ends a `curlMultiPoll()` in progress, from another thread or a signal handler. |
| `curlMultiAssign($multi, int $s, mixed $sockp): int` | [`curl_multi_assign`](https://curl.se/libcurl/c/curl_multi_assign.html) | `CURLMcode`. Attaches any PHP value to socket `$s`; the socket callback receives it as `$socketp` from then on. |
| `curlMultiStrerror(int $code): string` | [`curl_multi_strerror`](https://curl.se/libcurl/c/curl_multi_strerror.html) | libcurl's text for a `CURLMcode`. |
| `curlSocketStream(int $s)` | `php_stream_fopen_from_fd(dup($s), "r+")` | A stream resource on a duplicate of socket `$s`. The one method that is not a libcurl call. |

`$extraFds` mirrors `struct curl_waitfd`: a list of `['fd' => int, 'events' => int]` where `events` is a `CURL_WAIT_POLL*` mask (a missing `events` counts as `0`; a missing `fd` throws). Each entry comes back in `extra_fds` with `revents` added.

`curlSocketStream()` is `php://fd/$s` without its CLI-only gate: PHP serves `php://fd` under the CLI SAPI alone, so inside a phpmicro binary a loop built on `fopen("php://fd/$s")` cannot wrap curl's sockets at all. The stream is on a `dup(2)` of the socket. curl keeps the original; closing the stream closes the duplicate. Open it when the socket callback first reports a socket and close it on `CURL_POLL_REMOVE`, as the example does. A number that cannot be duplicated throws.

### setopt

`curlMultiSetopt()` passes every long option (`CURLMOPT_MAXCONNECTS`, `CURLMOPT_MAX_HOST_CONNECTIONS`, ...) straight to libcurl, and adds the four the socket API needs, which ext-curl's `curl_multi_setopt()` refuses:

| Option | Value | Description |
|---|---|---|
| `CURLMOPT_SOCKETFUNCTION` (`20001`) | `fn (?CurlHandle $easy, int $s, int $what, mixed $clientp, mixed $socketp): int` | Called whenever libcurl wants socket `$s` watched differently: `$what` is `CURL_POLL_IN`, `CURL_POLL_OUT`, `CURL_POLL_INOUT` or `CURL_POLL_REMOVE`. `$easy` is the ext-curl handle the socket belongs to, found in the multi's own handle list, or `null`. Return `0`. |
| `CURLMOPT_TIMERFUNCTION` (`20004`) | `fn (CurlMultiHandle $multi, int $timeoutMs, mixed $clientp): int` | Called whenever the time libcurl wants to wait changes: call `curlMultiSocketAction($multi, CURL_SOCKET_TIMEOUT, 0)` after `$timeoutMs` milliseconds, at once for `0`, never for `-1`. It is not repeated while the wanted timeout stays the same. Return `0`. |
| `CURLMOPT_SOCKETDATA` (`10002`), `CURLMOPT_TIMERDATA` (`10005`) | any PHP value | Handed to the matching callback as `$clientp`. libcurl's own data pointer always points at pcurl's state, never at the PHP value. |

`null` uninstalls a callback. A value that is not callable returns `CURLM_BAD_FUNCTION_ARGUMENT` (`10`) and leaves the old one in place. Any other pointer or function option, `CURLMOPT_PUSHFUNCTION` included, returns `CURLM_UNKNOWN_OPTION` (`6`): that one stays with ext-curl's `curl_multi_setopt()`.

A callback that returns `null` counts as `0`. One that throws returns `-1` to libcurl and the exception surfaces from the pcurl or ext-curl call that fired it; libcurl then answers `CURLM_ABORTED_BY_CALLBACK` (`11`) to every later add and socket action on that multi, as its documentation says. A multi call made from inside a callback returns `CURLM_RECURSIVE_API_CALL` (`8`). On libcurl older than 8.16.0, a timer callback that fails inside `curl_multi_add_handle()` leaves the rejected handle in libcurl's timer tree and the next call on that multi hangs or crashes; 8.16.0 fixed it upstream, and the suite skips that test below it.

pcurl's per-multi state (the callbacks, the two data values, the `curlMultiAssign()` values) is released when ext-curl frees the `CurlMultiHandle`, and at request shutdown. The cycle collector cannot see into that state, so a callback that captures its own multi (`use ($multi)`) keeps the multi alive until you set that option back to `null` or the request ends.

### Constants

pcurl defines no constants. libcurl's belong to your code, as in the example; these are the ones the socket API uses, with their values from `<curl/multi.h>`. ext-curl already defines the `CURLM_*` codes.

| Constant group | Names | Description |
|---|---|---|
| Options | `CURLMOPT_SOCKETDATA` `10002`, `CURLMOPT_TIMERDATA` `10005`, `CURLMOPT_SOCKETFUNCTION` `20001`, `CURLMOPT_TIMERFUNCTION` `20004` | The `$option` of `curlMultiSetopt()`. |
| Socket callback `$what` | `CURL_POLL_IN` `1`, `CURL_POLL_OUT` `2`, `CURL_POLL_INOUT` `3`, `CURL_POLL_REMOVE` `4` | What libcurl wants watched on the socket. |
| Socket action `$evBitmask` | `CURL_CSELECT_IN` `1`, `CURL_CSELECT_OUT` `2`, `CURL_CSELECT_ERR` `4` | What you saw happen on it. |
| Timer | `CURL_SOCKET_TIMEOUT` `-1` | The `$s` to pass with a bitmask of `0` when the timer is due. |
| `$extraFds` events | `CURL_WAIT_POLLIN` `1`, `CURL_WAIT_POLLPRI` `2`, `CURL_WAIT_POLLOUT` `4` | `events` and `revents` of a `curl_waitfd` entry. |
| Codes | `CURLM_OK` `0`, `CURLM_BAD_HANDLE` `1`, `CURLM_BAD_SOCKET` `5`, `CURLM_UNKNOWN_OPTION` `6`, `CURLM_RECURSIVE_API_CALL` `8`, `CURLM_BAD_FUNCTION_ARGUMENT` `10`, `CURLM_ABORTED_BY_CALLBACK` `11` | The `res` values the methods return. ext-curl defines these. |

## Extensions you might use with this one

* [ext/curl](https://www.php.net/manual/en/book.curl.php): ships with PHP and is required. Every handle, option and result stays there; pcurl only adds the multi calls it left out.
* [ext-epoll](https://github.com/php-io-extensions/epoll): Linux. Register the socket number the socket callback hands you with `epoll_ctl()`, tagged with `$s`, and use libcurl's timer as the `epoll_wait()` timeout. No `curlSocketStream()` needed.
* [ext-kqueue](https://github.com/php-io-extensions/kqueue): macOS. The same with `EV_SET()` and `kevent()`; an `EVFILT_TIMER` can carry libcurl's deadline.
* [ext-posi](https://github.com/php-io-extensions/posi): POSIX calls as PHP functions. The raw descriptor `posix_open()` returns goes into `$extraFds` as it is, so `curlMultiPoll()` waits on curl's transfers and a serial port or device node in one call.

## AI-assisted development

This codebase was built with LLM assistance under human direction. Prompts, reviews, and architectural decisions were owned by a professional software engineer — the model accelerates drafting and exploration; it does not replace judgment on APIs, dependency direction, or what ships.

## Agentic Documentation

This repository carries an [Open Knowledge Format](https://github.com/GoogleCloudPlatform/knowledge-catalog/blob/main/okf/SPEC.md) bundle at [`.okf/`](.okf/): Markdown with YAML frontmatter, readable by you and loadable by a coding agent. It records what the README doesn't: how the Zephir class reaches ext-curl's `CURLM*` through the object handlers, how the callback registry is attached to a multi's `free_obj` and released, why the module links no libcurl, how `ext/` is generated, overlaid and built for NTS and ZTS, the traps around rebuilding it, and a log of every change that altered behaviour.

Start at [`.okf/index.md`](.okf/index.md). [`AGENTS.md`](AGENTS.md) is the short rule sheet an agent reads before touching the Zephir, the C, or packaging; each rule points into the bundle for the reason behind it.

Point your agent at the repo and it has the same context the maintainer has. Point yourself at it when the README's answer is "it does X" and you want "because".

## Resources

* [libcurl multi interface overview](https://curl.se/libcurl/c/libcurl-multi.html) and [`curl_multi_socket_action(3)`](https://curl.se/libcurl/c/curl_multi_socket_action.html): the API this extension binds. Each method in the table links its page. The man pages are the manual; this README only adds what PHP changes.
* [`CURLMOPT_SOCKETFUNCTION(3)`](https://curl.se/libcurl/c/CURLMOPT_SOCKETFUNCTION.html) and [`CURLMOPT_TIMERFUNCTION(3)`](https://curl.se/libcurl/c/CURLMOPT_TIMERFUNCTION.html): the two callbacks, and the contract a loop has to keep.
* [ext/curl](https://www.php.net/manual/en/book.curl.php): the multi functions PHP already has, which pcurl's complete.
* [PIE](https://github.com/php/pie): the PHP Foundation's extension installer.
* [Zephir](https://zephir-lang.com/): the language [`pcurl/multi.zep`](pcurl/multi.zep) is written in; `ext/` is its generated C, committed. [`src/multi-api.c`](src/multi-api.c) is the hand-written C behind each method.
* [Open Knowledge Format](https://github.com/GoogleCloudPlatform/knowledge-catalog/blob/main/okf/SPEC.md): the spec the [`.okf/`](.okf/) bundle follows.

## Running the test suite

The suite is Pest v4 and needs no network: it starts PHP's built-in web server on a free local port and drives real transfers to it through the socket API, `curlSocketStream()` and `stream_select()`. It runs on Linux and macOS. Build the module from a copy of `ext/`, then point `php` at it:

```bash
git clone https://github.com/php-io-extensions/pcurl.git
cd pcurl
cp -R ext ../pcurl-build
(cd ../pcurl-build && phpize && ./configure --enable-pcurl && make -j"$(nproc 2>/dev/null || sysctl -n hw.ncpu)")
composer install
php -n -d extension="$PWD/../pcurl-build/modules/pcurl.so" vendor/bin/pest
```

```console
Tests:    73 passed (382 assertions)
Duration: 0.65s
```

`-n` skips your `php.ini`, so the suite tests the build and not an installed `pcurl.so`; without it, a PHP that already loads pcurl warns `Module "pcurl" is already loaded` and keeps the installed one. Where ext/curl is a shared extension rather than built in, as on Debian packaging, add `-d extension=curl` before the pcurl one, since `-n` dropped the ini that loads it; the same goes for the extensions Pest needs (`mbstring`, `dom`, `tokenizer`) where they are shared. A module only loads in the PHP whose `phpize` built it: for Homebrew's ZTS build, run `phpize` and pass `--with-php-config` from `/opt/homebrew/opt/php@8.4-zts/bin`. One test skips on libcurl older than 8.16.0, and the `CURLM_ABORTED_BY_CALLBACK` assertions skip below 7.81.0.

## Contributing

Open an issue first for anything beyond a typo. Pull requests change `pcurl/multi.zep` and `src/multi-api.c` together, regenerate `ext/` with `install-macos.sh` (never by building in `ext/` itself: copy, build, sync back), and add a Pest test for the behaviour. CI must be green on every cell of the matrix, NTS and ZTS alike. Read [`AGENTS.md`](AGENTS.md) for the rules the code follows.

## Security

pcurl only forwards calls to the libcurl already in the process, on handles ext-curl created; it opens no connections and reads no transfer data of its own. See [SECURITY.md](SECURITY.md) for the model and how to report a vulnerability.

## License

MIT. See [LICENSE](LICENSE).
