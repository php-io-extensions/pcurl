---
type: Trap
title: zephir stubs regenerates ext/
description: Running zephir stubs after pre-install silently discards the clang/ overlays
resource: /install-macos.sh
tags: [pcurl, trap, zephir, build]
status: draft
generated: { by: cursor-agent/claude-opus-5.5, at: "2026-09-22T19:30:00Z" }
sources:
  - id: install
    resource: /install-macos.sh
    title: step order
---

# Trap

Zephir 0.19's `zephir stubs` re-runs generation ("Cleaning old kernel files… Copying new kernel files…"), rewriting `ext/config.m4` and `ext/kernel/*`. posi's installer runs it after `pre-install.sh`, so the overlays are lost. For pcurl that dropped the libcurl link and the `src` build dir: the `.so` still loaded, but only through macOS `dynamic_lookup`.

# Do

Run `zephir stubs` **before** `pre-install.sh` (as `install-macos.sh` does now).[^install] After building, check `diff -q clang/config.m4 ext/config.m4` and `grep "checking for libcurl" build.log`.
