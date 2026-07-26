# ADR-0012 — Version-in-key cache invalidation

## Status
Accepted (Milestone 0). Explicitly an MVP trade-off.

## Context
Translations are cached per object and language. Invalidating them precisely
would mean enumerating or prefix-deleting keys. The target stack runs Redis
through Predis — a userland PHP client — where prefix deletion is not cheaply
available and per-key enumeration costs more than the waste it would avoid.

## Decision
Every cache key embeds two counters: a global epoch and a per-language counter.
Invalidation increments a counter, which logically orphans every key in that
namespace in a single option write.

The counters are read live rather than memoized on the instance. They are
autoloaded options, so reading is an array lookup, and reading live means every
holder of a `Cache` agrees on the current namespace — memoizing per instance
would let two service graphs in one process compute different keys, so one could
invalidate an entry the other still reads.

All object-cache access goes through `src/Cache/Cache.php`, so no key can be
built without a language.

## Consequences
- One translation write invalidates every cached entry for that language, not
  just the affected object. Always correct; wasteful on a large site.
- At the current content scale the recomputation cost is negligible.
- The improvement is additive and needs no caller changes: add a per-object
  counter to the same key shape, keeping the language counter for
  configuration-wide invalidation. It should be driven by measurement.
- Nothing containing cart state, nonces, prices, stock or per-user data is ever
  cached — only pure functions of source content, translations and language.
