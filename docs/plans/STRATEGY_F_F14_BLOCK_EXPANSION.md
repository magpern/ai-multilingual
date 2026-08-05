# F14 — Supported Gutenberg Block Expansion

**Status:** In progress on `feature/f14-block-expansion`  
**Baseline:** `main` @ F13 merge (`strategy-f-f13-general-availability-merged`)  
**Canonical ownership:** Expand Strategy F allowlist one leaf adapter at a time.  
**Does not change:** UUID, Store, TM, AI, Workspace, rollout/policy/cohort, render pipeline, cache, metrics, REST, security.

---

## Purpose

Admit additional **leaf** Gutenberg block adapters after F13 proved general availability on `{core/paragraph, core/heading, core/button}`.

## Planned adapters (committed F14 set)

| Order | Block | Wrapper tag | Work package |
|---|---|---|---|
| 1 | `core/list-item` | `li` | F14.1 |
| 2 | `core/preformatted` | `pre` | F14.2 |
| 3 | `core/verse` | `pre` | F14.3 |
| 4 | `core/code` | `code` (inside `pre`) | F14.4 |

Explicitly **out of scope:** container/nested identity (`core/list`, `core/quote`, `core/columns`, `core/group`); new fields beyond `content`; Elementor.

---

## Admission process (frozen — per adapter)

No adapter enters `BlockRegistry::SUPPORTED_BLOCKS` until **all** complete:

1. Render-safety proof (`rendered_false_positive == 0` on fixture corpus)
2. PHPUnit (unit and/or integration) green
3. Targeted browser validation PASS
4. Documentation updated
5. Validation log updated with admission evidence

Then register in `AdapterRegistry` + `SUPPORTED_BLOCKS`.

---

## Work packages

| WP | Scope |
|---|---|
| F14.0 | Plan, validation log scaffold, ROADMAP pointer |
| F14.1 | Admit `core/list-item` |
| F14.2 | Admit `core/preformatted` |
| F14.3 | Admit `core/verse` |
| F14.4 | Admit `core/code` |
| F14.5 | Full milestone validation + closure docs (no merge/tag) |

---

## Validation log

[F14_BLOCK_EXPANSION_VALIDATION_LOG.md](F14_BLOCK_EXPANSION_VALIDATION_LOG.md)

## Related

- F13 GA plan §12: [STRATEGY_F_F13_GENERAL_ROLLOUT.md](STRATEGY_F_F13_GENERAL_ROLLOUT.md)
- Master plan: [STRATEGY_F_PRODUCTION_IMPLEMENTATION.md](STRATEGY_F_PRODUCTION_IMPLEMENTATION.md) §19
