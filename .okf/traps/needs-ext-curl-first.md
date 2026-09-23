---
type: Trap
title: Needs ext-curl first
description: pcurl links against ext-curl's curl_multi_ce and cannot load without it
resource: /config.json
tags: [pcurl, trap, load-order]
status: draft
generated: { by: cursor-agent/claude-opus-5.5, at: "2026-09-22T19:30:00Z" }
sources:
  - id: config
    resource: /config.json
    title: requires.extensions
  - id: m4
    resource: /clang/config.m4
    title: PHP_ADD_EXTENSION_DEP
---

# Trap

`multi-api.c` references ext-curl's exported `curl_multi_ce` and walks its private structs. `config.json` `requires.extensions: ["curl"]` generates `ZEND_MOD_REQUIRED("curl")`, so PHP refuses to start pcurl without ext-curl.[^config] Where curl is a shared module (Debian `20-curl.ini`), it must load before `40-pcurl.ini`. `PHP_ADD_EXTENSION_DEP` covers static builds.[^m4]

# Do

Keep ext-curl enabled in every SAPI that loads pcurl. Keep pcurl's ini priority above curl's.
