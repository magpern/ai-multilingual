# TSC.4 — Gutenberg Coverage Expansion Implementation Plan

**Status:** **COMPLETE** on `main` — production implementation merged @ `c4a1e465f1d49a9c59f18083816e3f4ca92dc397`
**Milestone:** TSC.4 Gutenberg Coverage Expansion
**Parent:** [TSC_PARENT_IMPLEMENTATION_PLAN.md](TSC_PARENT_IMPLEMENTATION_PLAN.md) (Architecture Frozen on `main`) §20
**External review:** **FREEZE** (four amendments incorporated; revalidation **PASS**) · **STATE A** · **TARGET 7**
**Independent planning review:** **PASS** — [TSC4_GUTENBERG_COVERAGE_EXPANSION_PLANNING_VALIDATION_LOG.md](TSC4_GUTENBERG_COVERAGE_EXPANSION_PLANNING_VALIDATION_LOG.md)
**ADR:** **None** — existing [0013-gutenberg-segment-identity.md](../adr/0013-gutenberg-segment-identity.md) remains authoritative for Gutenberg identity
**Planning baseline:** `main` @ `65daa01545136968cfebd84466f52fbc9ad79035`
**Depends on:** AI Multilingual **v1.3.0**; TIQ Complete; OTL Complete; TSC Parent Frozen; **TSC.0–TSC.3 COMPLETE**; `Migrator::TARGET` **7**
**Related:** [TSC0_INTERNAL_SURFACE_CAPABILITY_FOUNDATION_IMPLEMENTATION_PLAN.md](TSC0_INTERNAL_SURFACE_CAPABILITY_FOUNDATION_IMPLEMENTATION_PLAN.md); [TSC2_REGISTERED_META_SURFACES_IMPLEMENTATION_PLAN.md](TSC2_REGISTERED_META_SURFACES_IMPLEMENTATION_PLAN.md); [TSC3_WOOCOMMERCE_EXTENDED_TRANSLATION_SURFACES_IMPLEMENTATION_PLAN.md](TSC3_WOOCOMMERCE_EXTENDED_TRANSLATION_SURFACES_IMPLEMENTATION_PLAN.md); ADR-0013; ADR-0001; ADR-0006; ADR-0007; ADR-0011; ADR-0015; ADR-0020
**Schema:** **STATE A** / TARGET **7** — no migration

**This document is the authoritative implementation specification for TSC.4.** Work packages TSC4.0–TSC4.4 are **COMPLETE** (see [TSC4_IMPLEMENTATION_EVIDENCE.md](TSC4_IMPLEMENTATION_EVIDENCE.md)).

**Production implementation status:** **COMPLETE.**
**TSC.5–TSC.6 implementation status:** **NOT STARTED.**

**Exact next step:** TSC.4 is complete. Do **not** start TSC.5+ until separately authorized. Do **not** bump version/TARGET, tag, release, or deploy as part of TSC.4 closure.

**Prior review history:** External review round 1 → four amendments (A1–A4) → **TSC.4 PLAN REVIEW: FREEZE**

---

## Amendment response summary (external review round 2)

| # | Topic | Decision |
|---|---|---|
| A1 | Stale granularity | **Confirmed by evidence** — `Store::sync_source()` mutates rows per `segment_key`; unchanged sibling rows receive zero DB writes; no new stale mechanism |
| A2 | Canonical-content invariant | **Wording corrected** — distinguish ADR-0013 UUID save-time mutation from TSC.4 render path; render never persists translated content or triggers save |
| A3 | Structural attribute preservation | **Real gap found** — narrow fail-closed structural-attribute-equality guard in TSC4.1 for existing 14 adapters; not a generic HTML engine |
| A4 | Block/field pair authority | **Invariant holds by construction** — `BlockRenderer` + adapter `get_supported_fields()` authoritative; lookup widening is grammar-only; malformed-pair tests required |

---

## 1. Baseline audit

| Field | Value |
|---|---|
| Planning baseline main HEAD | `65daa01545136968cfebd84466f52fbc9ad79035` |
| Version / TARGET | **1.3.0** / **7** |
| TSC.0–TSC.3 | **COMPLETE** |
| TSC.4–TSC.6 | **NOT STARTED** |
| Gutenberg subsystem | Pre-TSC (Spike S5 → Strategy F → v1.0.0 → A.0 / A.4 / F14); fully wired in `Plugin.php`; **default OFF** |
| Critical defect | `BlockTranslationLookup` accepts only `content` field — five A.0 field types extracted but never render |

### Repository spine (unchanged facts)

| Concern | Reality |
|---|---|
| Segment identity | `b:<uuid>:<field>` under ADR-0013; attribute `aimlBlockId` |
| Physical row key | `(source_type, source_id, segment_hash, language_id)` where `segment_hash = sha1(field_key + "\x1f" + segment_key)` |
| Block `field_key` | Literal `post_content` (`Extractor::FIELD_CONTENT`) for all block segments |
| Render seam | `the_content` @ priority **1** — before core `do_blocks` @ 9 |
| Invalidation | `save_post` mark dirty → `shutdown` flush → `RequestLocalInvalidationCoordinator` → `Store::sync_source` |
| Activation | Four flags default **false** (`block_attr_registration_enabled`, `block_uuid_injection_enabled`, `block_extraction_enabled`, `block_frontend_rendering_enabled`) |
| Admitted post types | `post`, `page`, `product` (`AdmittedPostTypes::FRONTEND_OVERLAY_TYPES`) |

---

## 2. Current Gutenberg coverage inventory

**14 production adapters** (`src/Block/AdapterRegistry.php`): `core/paragraph`, `core/heading`, `core/button`, `core/list-item`, `core/preformatted`, `core/verse`, `core/code`, `core/quote` (citation), `core/details` (summary), `core/pullquote` (content+citation), `core/image` (caption), `core/audio` (caption), `core/video` (caption), `core/file` (fileName, downloadButtonText).

**Six field types** in `Contract::SUPPORTED_FIELDS`: `content`, `citation`, `summary`, `caption`, `fileName`, `downloadButtonText`.

**Render pipeline:** `BlockFrontendRenderer` → `BlockRenderGate` → `BlockTranslationLookup` → `BlockTranslationSanitizer` → `BlockRenderer` → `serialize_blocks`. No `render_block` / `pre_render_block` hooks.

---

## 3. Coverage disposition matrix

| Family | Disposition |
|---|---|
| 14 leaf adapters (§2) | **SUPPORTED** — extraction/apply exist; non-`content` fields **PARTIAL** until TSC4.1 lookup fix |
| `core/gallery`, `core/media-text`, `core/cover`, `core/buttons`, structural containers | **SUPPORTED via recursion** — characterization tests in TSC4.0 |
| `core/navigation`, `core/query`, `core/post-template`, `core/block`, `wp_block` | **DEFERRED** |
| `wp_template`, `wp_template_part`, `core/table`, `core/search` attributes | **DEFERRED** |
| Custom/third-party/ACF blocks | **DEFERRED** (TSC.6) |
| `core/html`, `core/shortcode`, `core/embed` | **UNSUPPORTED** |
| Dynamic/entity-owned blocks (`core/post-title`, site-title, Woo blocks, etc.) | **NOT A TRANSLATABLE SOURCE** |

---

## 4. Milestone objective

Expand *effective* Gutenberg translation coverage within admitted post sources by:

1. Fixing the frontend render-lookup defect blocking five already-extracted field types.
2. Characterizing/hardening nested-block recursion coverage (gallery/media-text/cover/buttons).
3. Shipping a narrow structural-attribute-equality guard for tag-bypass-capable adapters.

Explicitly **not:** generic HTML engine, block reserializer, second document model, public registration API, Elementor, arbitrary JSON traversal, silent activation ON.

---

## 5. Identity contract (frozen)

- Grammar: **`b:<uuid>:<field>`** unchanged (ADR-0013).
- Attribute: **`aimlBlockId`** unchanged; injected on save via `SavePipeline` / `UuidInjector`.
- No new identity family, path component, or durable ID scheme.
- Reorder/move/nest/duplicate: UUID stable when registered attribute preserved (S5/A.R2 evidence).
- Type conversion: attribute dropped → new UUID → old segment orphans (intentional).

---

## 6. Field-definition model

Two extraction methods only (no new methods in TSC.4):

1. **Tag-scoped innerHTML** — `InnerHtmlReplacer` for paragraph/heading/list-item/button/preformatted/verse/code/quote/pullquote/details/image/audio/video.
2. **Named attributes + HTML fallback** — `FileAdapter` for `fileName`, `downloadButtonText` (`Store::FORMAT_PLAIN`).

**Block/field pair authority (A4):** `Contract::SUPPORTED_FIELDS` is grammar vocabulary only. **`BlockRenderer::render()`** resolves adapter by `block_name` and queries only fields from that adapter's `get_supported_fields()`. `BlockTranslationLookup` widening pre-filters vocabulary; it must **not** duplicate the block-ownership matrix.

---

## 7. Serialization safety (A2)

**A. Existing identity save mutation (ADR-0013, unchanged).** `UuidInjector` via `SavePipeline` `wp_insert_post_data` @ 8 writes `aimlBlockId` into canonical `post_content`. TSC.4 does not change this.

**B. TSC.4 translation/render mutation: none.** Render path never calls `wp_update_post`, never writes `post_content`, never mutates `aimlBlockId`, never triggers save. TSC4.1 changes are read-only additions inside the render path.

---

## 8. Invalidation (A1)

Trigger path unchanged: `save_post` @ 20 → mark dirty → `shutdown` @ 20 → full re-extract → `Store::sync_source()`.

**Proven segment-key granularity** (`Store::sync_source` lines 1585–1666):

- Unchanged `source_hash` for a row → bare `continue` — **no DB write**.
- Changed hash → only that row gets `is_stale=1`.
- Missing `segment_key` → only that row orphaned.
- Multi-field blocks: each field is a distinct `segment_key` (`b:<uuid>:<field>`).

No new Gutenberg-specific stale mechanism.

---

## 9. Frontend render architecture

Seam: **`the_content` @ priority 1** (frozen).

TSC4.1 code changes:

1. `BlockTranslationLookup`: accept `in_array($field, Contract::SUPPORTED_FIELDS, true)` instead of `content` only.
2. Structural-attribute-equality guard after `apply_translation()` for tag-bypass-capable adapters.

Pair authority remains in `BlockRenderer`, not lookup.

---

## 10. Rich-text / provider / URL safety (A3)

**Three distinct properties:**

| Property | Status |
|---|---|
| A. Translatable semantic field | URLs/href/id/class/data-*/config never independently extracted — **Supported** |
| B. Structural HTML context | FORMAT_HTML fragments may carry markup context (e.g. button `<a>` wrapper) — **Supported, unchanged scope** |
| C. Round-trip authority | Named-attribute fields safe; tag-bypass paths (`replace_tag_content`, `replace_button_label`) can replace attributes — **Partial → Supported after TSC4.1 guard** |

TSC4.1 guard: after apply, compare `href`/`class`/`id`/`target`/`rel`/`data-*` on wrapper against source; reject (fallback to source) on any difference. Fail-closed like `wp_kses_post` equality. **Not a generic HTML engine.**

---

## 11. OTL / Jobs / TI.7 / concurrency

| System | TSC.4 role |
|---|---|
| OTL | Verify newly-renderable fields behave like `content` rows; no new UI |
| Jobs (TI.6) | Verify materialization/retry/conflict for five field types; no new engine |
| TI.7 | Unchanged; `BlockTranslationLookup` publication gate unchanged in policy |
| Concurrency | Reuse existing `source_hash` / `translation_hash`; no block-specific mechanism |

---

## 12. Activation (frozen)

All four block flags remain default **OFF**. TSC.4 must not flip defaults. PluginGuard-testable (GB21, AC17).

---

## 13. Deferred / unsupported / non-goals

**Deferred:** navigation, query, post-template, reusable/synced `core/block`/`wp_block`, FSE templates, `core/table`, `core/search`, custom blocks, Woo Gutenberg blocks with ambiguous ownership.

**Unsupported:** `core/html`, `core/shortcode`, `core/embed`.

**Explicit non-goals:** Elementor (TSC.5), public block API (TSC.6), generic HTML translation, URL translation, canonical translation writes, duplicate post/term/Woo ownership.

**Carry-forward:** `core/code` remains Supported (pre-TSC v1.0.0 behavior); translation-quality risk documented.

---

## 14. Schema / TARGET / ADR

| Verdict | Value |
|---|---|
| STATE | **A** |
| TARGET | **7** |
| Migration | **None** |
| New ADR | **None** — ADR-0013 governs |

---

## 15. GB requirement matrix (GB1–GB25)

| ID | Requirement | Disposition |
|---|---|---|
| GB1 | Current coverage inventory | Supported |
| GB2 | Stable identity under edit/reorder/move/nest/duplicate | Supported |
| GB3 | Identity under type-conversion (orphan-and-new) | Supported |
| GB4 | Supported block set frozen | Supported |
| GB5 | Field-definition catalog + pair-ownership invariant | Supported (test-covered by GB25) |
| GB6 | Rich-text `wp_kses_post` equality | Partial |
| GB7 | Nested-block uniqueness | Supported |
| GB8 | Reusable/synced block ownership | Deferred |
| GB9 | Patterns/FSE ownership | Deferred |
| GB10 | Navigation boundary | Deferred / Unsupported |
| GB11 | External-entity ownership non-overlap | Supported |
| GB12 | Invalidation stale-granularity (A1 proven) | Supported |
| GB13 | Orphan/move semantics | Supported |
| GB14 | OTL for all six field types | Partial → Supported after TSC4.1 |
| GB15 | Jobs compatibility | Partial → Supported after TSC4.1 |
| GB16 | TI.7 publication gating unaffected | Supported |
| GB17 | Concurrency reuse | Supported |
| GB18 | Provider payload safety (A/B supported; C → GB24) | Supported / Partial |
| GB19 | Code/HTML/shortcode disposition | Supported (code) / Unsupported (html, shortcode) |
| GB20 | URL safety | Partial → Supported after TSC4.1 guard |
| GB21 | Activation defaults unchanged | Supported |
| GB22 | Backward compatibility | Supported |
| GB23 | Performance / PluginGuard / schema / TSC.5-6 exclusion | Supported |
| GB24 | Structural HTML attribute round-trip equality (A3) | Partial → Supported after TSC4.1 guard |
| GB25 | Block/field pair fail-closed admission (A4) | Supported by construction; test-covered TSC4.0/4.1 |

---

## 16. Work package ladder (TSC4.0–TSC4.4)

| WP | Scope |
|---|---|
| **TSC4.0** | Coverage/identity characterization; gallery/media-text/cover/buttons recursion tests; malformed-pair fixtures (GB25); freeze GB matrix |
| **TSC4.1** | `BlockTranslationLookup` field allowlist fix; structural-attribute guard (GB24); render tests for five field types |
| **TSC4.2** | OTL/Jobs/TI.7/concurrency regression; per-segment-key stale verification (A1) |
| **TSC4.3** | PluginGuard invariants; performance regression |
| **TSC4.4** | Docs/evidence/closure; CHANGELOG; bounded browser smoke (7 scenarios) |

---

## 17. Acceptance criteria (AC1–AC22)

| ID | Criterion |
|---|---|
| AC1 | Five non-`content` fields render when flags ON + eligible; forged `(block_name, field)` pairs never applied |
| AC2 | `content`-field behavior unchanged |
| AC3 | One segment per field per save (no duplicates) |
| AC4 | Identity stable under reorder/move/nest/duplicate |
| AC5 | Type-conversion orphans old segment correctly |
| AC6 | One field change marks only that row stale; siblings untouched (A1) |
| AC7 | Block delete orphans correct rows only |
| AC8 | Nested children not duplicated on structural parents |
| AC9 | Structural attributes preserved via guard; reject on change (A3) |
| AC10 | HTML round-trip; rejections fall back to source |
| AC11 | html/shortcode/embed never sent to provider |
| AC12 | OTL works for five newly-renderable fields |
| AC13 | Jobs works for five newly-renderable fields |
| AC14 | TI.7 unchanged |
| AC15 | Visitor-only rendering preserved |
| AC16 | Canonical data unchanged by render; UUID save mutation acknowledged (A2) |
| AC17 | Four activation flags default OFF |
| AC18 | No new `post_content` write in TSC.4 diff (A2) |
| AC19 | Existing translations remain valid |
| AC20 | No Elementor/public API; TARGET 7; no migration |
| AC21 | Structural-attribute guard rejects forged attribute changes (A3) |
| AC22 | Malformed pairs rejected; valid pairs accepted (A4) |

---

## 18. Test strategy

- **Unit:** lookup/sanitizer extensions; nested recursion; `Store::sync_source` granularity (AC6); malformed pairs (AC22); structural-attribute bypass (AC21).
- **Integration:** frontend rendering for five fields; extraction/regression; OTL/Jobs parameterization.
- **Security:** provider A/B/C assertions; render path never touches `posts` table (A2).
- **Architecture:** `PluginGuardTest` extensions (§31 equivalents in parent TSC docs).
- **Performance:** 100-block / deep-nesting fixtures; no duplicate parsing.
- **Browser:** seven bounded scenarios (§38 in planning audit).

---

## 19. PluginGuard locks (TSC4.3)

- Block flags default OFF.
- No new `SOURCE_*` for blocks.
- Lookup accepts exactly `Contract::SUPPORTED_FIELDS`.
- No `render_block`/`pre_render_block` in `src/`.
- No `wp_update_post` in render-path `Block*.php` files.
- html/shortcode/embed absent from `BlockRegistry::SUPPORTED_BLOCKS`.
- `wp_block` absent from `AdmittedPostTypes`.
- No TSC.5/TSC.6 leakage.
- Structural-attribute guard enforced (GB24).
- Pair authority via `BlockRenderer` only (GB25).

---

## 20. Production value

TSC.4 adds **zero new block families**. Value: unlock five field types across seven adapters that extract/store/translate today but never reach the frontend; confirm recursion coverage for gallery/media-text/cover/buttons.

---

## 21. STOP audit

No STATE B triggers: no new table; TARGET 7; no second Store; no global HTML translator; no arbitrary third-party traversal; no translation write to canonical content; no duplicate ownership; no public API; no Elementor.

**Result:** STATE A holds.

---

## 22. Exact next step

Implement TSC.4 from this frozen plan via `feature/tsc4-gutenberg-coverage-expansion` when authorized. Do **not** start TSC.5+ until separately authorized. Do **not** bump version/TARGET, tag, release, or deploy as part of TSC.4 closure.
