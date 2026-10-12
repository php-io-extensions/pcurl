# Security Policy

## Supported versions

ext-pcurl is pre-1.0. No 0.x release receives security fixes or advisories; fixes land in the
next release line. Security support starts with 1.0.

| Version | Security fixes |
|---------|----------------|
| < 1.0   | No             |

## Reporting a vulnerability

Please don't open a public issue for a security problem.

Report it privately through GitHub: the **Report a vulnerability** button on the
[php-io-extensions/pcurl](https://github.com/php-io-extensions/pcurl) repository's **Security** tab.
If that isn't available, email **info@projectsaturnstudios.com**.

Include what you found, the affected version, your platform, PHP build (NTS or ZTS) and libcurl
version, and steps to reproduce. Reports are read and weighed for the release line in development;
before 1.0 there is no response-time commitment.

## Security model

ext-pcurl binds libcurl's multi interface one to one. Each method performs the `curl_multi_*` call
with the privileges of the PHP process, on handles ext-curl created, the same as C code linked into
that process. It opens no connections of its own and reads no transfer data; everything a transfer
does is ext-curl's and libcurl's behaviour.

- **Handles.** Every method checks that `$multi` is exactly a `CurlMultiHandle` before reading the
  `CURLM*` behind it, and `CurlHandle` objects handed to a socket callback are looked up in that
  multi's own handle list. Any other object throws before libcurl is called.
- **Callbacks.** `CURLMOPT_SOCKETFUNCTION` and `CURLMOPT_TIMERFUNCTION` run PHP code from inside
  libcurl. libcurl's own data pointer always points at pcurl's per-multi state, never at a PHP
  value; the PHP values set through `CURLMOPT_SOCKETDATA`, `CURLMOPT_TIMERDATA` and
  `curlMultiAssign()` are held as zvals in that state and handed back as arguments. A throw inside a
  callback returns `-1` to libcurl and surfaces from the call that fired it. The state is released
  when ext-curl frees the multi, and at request shutdown.
- **Sockets.** `curlSocketStream()` wraps a duplicate (`dup(2)`) of the socket number libcurl
  reported; closing the stream closes the duplicate and never curl's socket. The number is passed
  to `dup` as given: a script can duplicate any descriptor the process holds, as it can with
  `php://fd` under the CLI.
- **Descriptor sets.** `curlMultiFdset()` reads sets libcurl filled, bounded by `FD_SETSIZE`.
  `curlMultiPoll()` and `curlMultiWait()` build their `curl_waitfd` array from `$extraFds` after
  checking each entry's shape and copy the results back; a malformed entry throws before libcurl
  is called.
- **Library identity.** The module links no libcurl of its own; its `curl_multi_*` references
  resolve at load time to the libcurl already in the process, the one ext-curl's handles came from.

A report is in scope when ext-pcurl itself reads or writes memory it should not: a handle read
past the class check, a callback state used after its multi was freed, an `extra_fds` entry that
reaches libcurl unchecked, a crash reachable from well-formed PHP arguments. Effects that follow
from a valid libcurl call the script asked for, such as a transfer to a URL it chose, are the
documented behaviour.
