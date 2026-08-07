# A.R2 — Nested Gutenberg Identity Research Log

**Status:** In progress  
**Charter:** [A4_NESTED_GUTENBERG_IDENTITY_PLAN.md](A4_NESTED_GUTENBERG_IDENTITY_PLAN.md)  
**Research branch:** `feature/ar2-nested-gutenberg-identity`  
**Planning merge:** `42b306d462672e6ddc9b2a43e32cb5067376d136`  
**Recommendation:** _pending A48_  

This log is the source of truth for A.R2 findings. Confidence labels:

| Label | Meaning |
|---|---|
| **Proven by experiment** | Direct fixture / WP-CLI / unit evidence |
| **Supported by evidence** | Strong indirect evidence |
| **Inferred** | Reasonable but not measured |
| **Remaining assumption** | Explicitly not yet proven |

---

## Environment

| Fact | Value |
|---|---|
| Site | https://dev.biopentra.eu |
| Plugin mount | `/opt/biopentra/dev/ai-multilingual` → WP plugins bind-mount |
| Research path | `research/ar2-nested-gutenberg-identity/` |
| Execution | `wp eval-file` via `apps/wordpress` wpcli |

---

## A40 — Baseline nested-block inventory + fixture corpus

**Status:** Complete  
**Evidence:** [`a40-environment.json`](../../research/ar2-nested-gutenberg-identity/evidence/a40-environment.json), [`a40-inventory.json`](../../research/ar2-nested-gutenberg-identity/evidence/a40-inventory.json), [`a40-summary.json`](../../research/ar2-nested-gutenberg-identity/evidence/a40-summary.json)

### Production contracts verified

| Contract | Observation | Confidence |
|---|---|---|
| Grammar `b:<uuid>:<field>` | `Contract::SEGMENT_KEY_GRAMMAR` | Proven by experiment |
| UUID attr `aimlBlockId` | `Contract::ATTR_NAME` | Proven by experiment |
| Supported leaves | paragraph, heading, button, list-item, preformatted, verse, code | Proven by experiment |
| Adapter registry | Same seven; no container adapters | Proven by experiment |
| Eligibility | Requires supported + non-dynamic + **empty `innerBlocks`** + non-empty innerHTML | Proven by experiment |
| Dynamic deny | Includes `core/navigation`, `core/query`, `core/block`, … | Proven by experiment |
| Walker | `BlockTreeWalker` DFS-recurses `innerBlocks` | Proven by experiment (source + unit test) |

### Fixture corpus

Research fixtures (sanitized):

- `structural-group-columns.html`
- `list-nested-with-uuids.html`
- `textual-quote-pullquote-details.html`
- `media-cover-mediatext-gallery.html`
- `shared-dynamic-navigation-query-reusable.html`
- `deep-nested-performance.html`

Authored corpus reused read-only:

- `tests/fixtures/blocks/authored/list-nested.html`
- `tests/fixtures/blocks/authored/nested-group-columns.html`
- `tests/fixtures/blocks/authored/quote-with-citation.html`
- `tests/fixtures/blocks/authored/reusable-block.html`
- `tests/fixtures/blocks/authored/synced-pattern.html`
- `tests/fixtures/blocks/authored/dynamic-block.html`

### Family presence

All charter families appear in research and/or authored fixtures: group, columns, column, list, list-item, quote, pullquote, details, cover, media-text, gallery, navigation, query, post-template, reusable/`core/block`.

### A40 conclusions

1. Baseline identity/admission contracts match the planning hypothesis. — **Proven by experiment**
2. No nested production adapters exist yet. — **Proven by experiment**
3. Research may proceed to stability / traversal experiments without production code changes. — **Supported by evidence**

---
