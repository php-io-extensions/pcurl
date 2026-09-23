---
type: Trap
title: Same libcurl as ext-curl
description: pcurl must call the exact libcurl that created the CURLM*/CURL* it is handed
resource: /install-macos.sh
tags: [pcurl, trap, linking, macos]
status: draft
generated: { by: cursor-agent/claude-opus-5.5, at: "2026-09-22T19:30:00Z" }
sources:
  - id: install
    resource: /install-macos.sh
    title: libcurl resolution
---

# Trap

Handles come from ext-curl. If pcurl links a different libcurl, e.g. macOS `/usr/lib/libcurl` (8.7.1, which `curl-config` on PATH reports) while Homebrew PHP uses `/opt/homebrew/opt/curl` (8.22), both copies end up in the process and pcurl calls one library's functions on the other's structs. That means silent corruption or crashes.

# Do

Build with `pkg-config libcurl` pointed at PHP's libcurl. `install-macos.sh` derives it from `otool -L $PHP_BIN`.[^install] After building, check that `otool -L ext/modules/pcurl.so` and `otool -L $(php -r 'echo PHP_BINARY;')` name the same `libcurl.4.dylib`. Don't use `curl-config`. A PHP with libcurl linked statically into the binary (some Herd/static-php builds) isn't supported.
