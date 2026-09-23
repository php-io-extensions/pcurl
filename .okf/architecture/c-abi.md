---
type: Architecture
title: C ABI
description: src/multi-api entry points, ext-curl handle access, callback state and lifetimes
resource: /src/multi-api.c
tags: [pcurl, abi, c, zts]
status: draft
generated: { by: cursor-agent/claude-opus-5.5, at: "2026-09-22T20:10:00Z" }
sources:
  - id: h
    resource: /src/multi-api.h
    title: multi-api.h
  - id: c
    resource: /src/multi-api.c
    title: multi-api.c
  - id: opt
    resource: /optimizers
    title: Zephir optimizers
  - id: config
    resource: /config.json
    title: config.json destructors / requires
---

# Layout

`multi.zep` → Zephir pseudo-functions `<call>_multi` (`socket_action_multi`, `setopt_multi`, …) → one optimizer each in `optimizers/` → `pcurl_multi_<call>()` in `src/multi-api.c`.[^opt][^h] Scalar returns use the `PpollFileOptimizer` shape; array/string returns write into the symbol variable (`LstatPathOptimizer` shape).

# Reaching libcurl handles

ext-curl's structs are private, but both start with the libcurl handle and end with `zend_object std`:[^c]

* `php_curlm { CURLM *multi; zend_llist easyh; …; zend_object std; }`
* `php_curl  { CURL *cp; …; zend_object std; }`

So `(char *) obj - obj->handlers->offset` is the struct head. This runs only after `Z_OBJCE_P(mh) == curl_multi_ce` (exact match). The socket trampoline maps `CURL *` back to its `CurlHandle` by walking `easyh` (the zvals ext-curl adds on `curl_multi_add_handle`).

# Callback state

Registry `ZEND_TLS HashTable *` keyed by `CURLM *` → `pcurl_multi_state { multi, socket_cb, socket_data, timer_cb, timer_data, sockp }`. `ZEND_TLS` makes it per-thread under ZTS. The registry's dtor (`pcurl_state_dtor`) owns every zval in a state.

* libcurl's `SOCKETDATA`/`TIMERDATA` always point at the state; the PHP `*DATA` values live in the state.
* `sockp` owns the zvals given to `curl_multi_assign` (fd → `zval *`); reassigning frees the old one after libcurl accepts the new pointer.
* **free_obj hook.** When a state is created, its multi's `handlers` are swapped for a process-global copy of ext-curl's handlers (copied once, under a pthread mutex, from the first tracked object) whose `free_obj` is `pcurl_multi_free_obj`. That hook unhooks both `*FUNCTION` options, calls ext-curl's original `free_obj` (`curl_multi_cleanup`), then deletes the state. So every registry entry belongs to a live multi. No stale state survives address reuse, and there's no strong ref. `handlers->offset` is copied too, so the offset trick still works.
* `pcurl_multi_api_rshutdown()` runs from RSHUTDOWN (config.json `destructors.request`).[^config] It detaches the registry (sets it to NULL first, so nested frees see none), unhooks every still-live multi, then destroys the states. Multis freed after RSHUTDOWN go straight to ext-curl's `free_obj`.
* `pcurl_easy_zval` only dereferences `easyh` entries whose class is exactly `curl_ce`. `curl_multi_fdset` output is read only below `FD_SETSIZE`.
