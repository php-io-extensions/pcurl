---
type: Trap
title: Do not rebuild ext/ in place
description: Committed ext/ is ship-ready — regenerate only on a disposable copy
resource: /ext
tags: [pcurl, trap, packaging, zephir]
status: draft
generated: { by: cursor-agent/claude-opus-5.5, at: "2026-09-22T19:30:00Z" }
sources:
  - id: composer
    resource: /composer.json
    title: composer.json build-path
  - id: install
    resource: /install-macos.sh
    title: install-macos.sh
---

# Trap

The committed `ext/` tree is the ship-ready PIE/`phpize` input (`build-path: ext`).[^composer] The installer runs `zephir fullclean`/`generate` and `make` in place, which rewrites or dirties that tree.[^install] (The first `ext/` was generated in place only because the package did not exist yet.)

# Do

1. Copy the package tree to a disposable directory.
2. Run the installer in the copy, once per PHP build (NTS and ZTS).
3. Sync back only intentional artifacts: `ext/` sources and `ide/`.
4. Delete the copy.
