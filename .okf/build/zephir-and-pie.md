---
type: Build
title: Zephir + PIE install
description: How ext/ is generated, overlaid, compiled and installed for NTS and ZTS
resource: /install-macos.sh
tags: [pcurl, build, zephir, pie, zts]
status: draft
generated: { by: cursor-agent/claude-opus-5.5, at: "2026-09-22T19:30:00Z" }
sources:
  - id: install
    resource: /install-macos.sh
    title: install-macos.sh
  - id: pre
    resource: /pre-install.sh
    title: pre-install.sh
  - id: m4
    resource: /clang/config.m4
    title: clang/config.m4 overlay
  - id: composer
    resource: /composer.json
    title: composer.json php-ext
---

# Consumers

`pie install php-io-extensions/pcurl` builds from the committed `ext/` (`build-path: ext`, `priority: 40`, `--enable-pcurl`).[^composer]

# Maintainers (macOS)

`bash install-macos.sh`, with `PHP_BIN=/path/to/php` to target a specific build (e.g. `/opt/homebrew/opt/php@8.4-zts/bin/php`).[^install] Order:

1. Preflight. Resolves php-config and phpize next to `PHP_BIN`. Points `PKG_CONFIG_PATH` at the libcurl the PHP binary links (`otool -L`, `@loader_path` resolved; brew `curl` as fallback). Requires ext-curl to be loaded. Skips sudo when the ext and conf.d dirs are writable.
2. `zephir fullclean` → `zephir stubs` → `pre-install.sh` (`zephir generate`, copy `src/` → `ext/src/`, copy `clang/*` over `ext/`).[^pre] Stubs must come **before** pre-install (see traps).
3. `phpize && ./configure --enable-pcurl && make`, copy `pcurl.so`, `codesign --force --sign -`, write `40-pcurl.ini`, verify `Pcurl\Multi` exists.

`clang/config.m4` replaces Zephir's generated one. It adds pkg-config `libcurl >= 7.68.0` (flags plus `PCURL_SHARED_LIBADD`), the `src` build dir, `PHP_ADD_EXTENSION_DEP([pcurl], [curl])` and posi's clang `-Wno-*` CFLAGS.[^m4] `clang/kernel/*.c` are posi's PHP 8.4/clang kernel patches.

Run the installer once per PHP build: each run rebuilds `ext/` for that php-config. Verified on Homebrew PHP 8.4.25 NTS and ZTS.

# Known noise

Zephir 0.19 emits `Z_PARAM_ARRAY(extraFds)` on a `zval` (an `-Wincompatible-pointer-types` warning in `multi.zep.c`). It's benign: `zephir_fetch_params` + `zephir_get_arrval` overwrite it immediately. posi's `array` params do the same.
