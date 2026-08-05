# F14 Implementation Summary — Supported Gutenberg Block Expansion

**Status:** Implementation complete on `feature/f14-block-expansion`  
**Baseline:** `main` @ F13 merge (`14e0a38f3` / `strategy-f-f13-general-availability-merged`)  
**Date:** 2026-08-05  
**Merge/tag:** Not performed (per F14 execution rules)

---

## What shipped

Four leaf Gutenberg adapters admitted via the frozen F14 admission process:

| Order | Block | Adapter | Wrapper tag |
|---|---|---|---|
| 1 | `core/list-item` | `ListItemAdapter` | `li` |
| 2 | `core/preformatted` | `PreformattedAdapter` | `pre` |
| 3 | `core/verse` | `VerseAdapter` | `pre` |
| 4 | `core/code` | `CodeAdapter` | `code` |

Each adapter completed: implement → register → unit → integration → browser smoke (FP=0) → docs → validation log → `SUPPORTED_BLOCKS`.

## Architecture reuse (unchanged)

- `AbstractBlockAdapter` + `InnerHtmlReplacer`
- `AdapterRegistry` / `BlockRegistry`
- Existing render gate, rollout policy, cohort providers, Store, TM, Workspace, REST, security

No duplicate render path, storage, or translation pipeline.

## Out of scope (deferred)

- Container/nested identity (`core/list`, `core/quote`, `core/columns`, `core/group`)
- Fields beyond `content`
- Elementor segment identity
- Rollout/GA redesign

## Validation

See [F14_BLOCK_EXPANSION_VALIDATION_LOG.md](F14_BLOCK_EXPANSION_VALIDATION_LOG.md) and [F14_TIER0_EVIDENCE.json](F14_TIER0_EVIDENCE.json).

## Work packages

| WP | Commit (subject) |
|---|---|
| F14.0 | `docs(f14): scaffold block expansion plan and admission framework` |
| F14.1 | `feat(f14): admit core/list-item adapter` |
| F14.2 | `feat(f14): admit core/preformatted adapter` |
| F14.3 | `feat(f14): admit core/verse adapter` |
| F14.4 | `feat(f14): admit core/code adapter` |
| F14.5 | `test(f14): complete milestone validation and closure docs` |

## Recommended next

**Container/nested-block identity spike** (architecture extension for `core/list` / `core/quote` / `core/group` / `core/columns`) — or production observation of the expanded leaf allowlist under existing F13 GA controls, depending on product priority. Elementor remains a separate ADR/spike.
