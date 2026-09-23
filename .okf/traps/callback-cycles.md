---
type: Trap
title: Callback cycles
description: A callback that captures its own CurlMultiHandle is a cycle the GC cannot see
resource: /src/multi-api.c
tags: [pcurl, trap, lifetime, memory, gc]
status: draft
generated: { by: cursor-agent/claude-opus-5.5, at: "2026-09-22T20:10:00Z" }
sources:
  - id: c
    resource: /src/multi-api.c
    title: pcurl_multi_free_obj / pcurl_state_dtor
---

# Trap

pcurl stores callbacks and `*DATA`/`assign` values in a per-thread registry, not in the handle's own GC table. When a `CurlMultiHandle` is freed, its state is dropped through pcurl's `free_obj` hook.[^c] But a callback that captures the multi (`function () use ($m)`) forms `$m → state → closure → $m`, and the cycle collector cannot see the state half of it. That multi lives until the option is set back to `null` or the request ends (RSHUTDOWN cleans up safely).

Dropping a multi whose callbacks don't reference it frees it immediately, callbacks and all.

# Do

In long-running loops, don't capture the multi in its own callbacks. The timer callback already receives it as its first argument. If you must capture it, `curlMultiSetopt($m, 20001, null)` / `(…, 20004, null)` before dropping it.
