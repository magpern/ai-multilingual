# TSC.5 — Elementor Coverage Expansion Implementation Plan

**Status:** **Architecture Frozen** on `main` — production implementation **NOT STARTED**
**Milestone:** TSC.5 Elementor Coverage Expansion
**Parent:** [TSC_PARENT_IMPLEMENTATION_PLAN.md](TSC_PARENT_IMPLEMENTATION_PLAN.md) (Architecture Frozen on `main`) §20
**External review:** **FREEZE** (four amendments A1–A4 incorporated) · **STATE A** · **TARGET 7**
**Independent planning review:** **PASS** — [TSC5_ELEMENTOR_COVERAGE_EXPANSION_PLANNING_VALIDATION_LOG.md](TSC5_ELEMENTOR_COVERAGE_EXPANSION_PLANNING_VALIDATION_LOG.md)
**ADR:** **None new** — existing [0016-elementor-identity-and-ownership.md](../adr/0016-elementor-identity-and-ownership.md) remains authoritative for Elementor identity
**Planning baseline:** `main` @ `592d97b72e63efb96dd3d1a6a33717a52ef8f39d`
**Depends on:** AI Multilingual **v1.3.0**; TIQ Complete; OTL Complete; TSC Parent Frozen; **TSC.0–TSC.4 COMPLETE**; `Migrator::TARGET` **7**; A.2/A.3 Elementor foundation complete
**Related:** [TSC4_GUTENBERG_COVERAGE_EXPANSION_IMPLEMENTATION_PLAN.md](TSC4_GUTENBERG_COVERAGE_EXPANSION_IMPLEMENTATION_PLAN.md); [A2_ELEMENTOR_FOUNDATION_IMPLEMENTATION_PLAN.md](A2_ELEMENTOR_FOUNDATION_IMPLEMENTATION_PLAN.md); [A3_ELEMENTOR_WIDGET_COVERAGE_IMPLEMENTATION_PLAN.md](A3_ELEMENTOR_WIDGET_COVERAGE_IMPLEMENTATION_PLAN.md); ADR-0016; ADR-0013; ADR-0001; ADR-0011; ADR-0015; ADR-0020
**Schema:** **STATE A** / TARGET **7** — no migration

**This document is the authoritative implementation specification for TSC.5.** Work packages TSC5.0–TSC5.6 are **NOT STARTED**.

**Production implementation status:** **NOT STARTED.**
**TSC.6 implementation status:** **NOT STARTED.**

**Exact next step:** Implement TSC.5 from frozen `main` via branch `feature/tsc5-elementor-coverage-expansion` only when an implementation task is opened. Do **not** start TSC.6+. Do **not** bump version/TARGET, tag, release, or deploy as part of this planning freeze.

**Prior review history:** Initial proposal → external review **AMEND** → four refinements (A1–A4) → revalidation **PASS** → **TSC.5 PLAN REVIEW: FREEZE**

---

## Amendment response summary (external review)

| # | Topic | Decision |
|---|---|---|
| A1 | Widget scope | **No optional expansion.** Committed Supported set = existing A.2/A.3 eight families only. No TSC5.x widget wave. |
| A2 | Invalidation | **Frozen event contract.** `elementor/document/after_save` authoritative mark-dirty; `save_post` redundant fallback; **no** `_elementor_data` meta hook; **no** `before_save`. |
| A3 | Structural guard | **Surface-neutral** `StructuralAttributeGuard` in `src/Translation/Safety/`; Block + Elementor consumers; TSC.4 delegate with zero regression. |
| A4 | Render/cache | **Frozen context matrix** + cache/language invariant; integration tests required; editor/preview canonical only. |

---

## 1. Baseline audit

| Field | Value |
|---|---|
| Planning baseline main HEAD | `592d97b72e63efb96dd3d1a6a33717a52ef8f39d` |
| Version / TARGET | **1.3.0** / **7** |
| TSC.0–TSC.4 | **COMPLETE** |
| TSC.5–TSC.6 | **NOT STARTED** |
| Elementor subsystem | A.R1 (CONDITIONAL GO) + A.2 Foundation + A.3 Widget Coverage — **complete/merged** under `src/Elementor/` |
| TSC parent gap | **S5/TS7 stale = PARTIAL** — `save_post` only; `_elementor_data` may mutate after `save_post` |
| Feature flags | `elementor_extraction_enabled` + `elementor_frontend_rendering_enabled` — **both default OFF**, no admin UI |

### Repository spine (unchanged facts)

| Concern | Reality |
|---|---|
| Segment identity | `e:d:<owner_post_id>:<element_id>:<control_key>[:<nested_item_id>]` (ADR-0016 / A.2 / A.3) |
| Store `field_key` | `_elementor` (`Contract::FIELD_KEY`) |
| `segment_kind` | `field` |
| `surface` | `elementor` |
| Source ownership | `source_type=post`, `source_id=document post ID` — **no SOURCE_ELEMENTOR** |
| Render seam | `elementor/frontend/builder_content_data` @ priority 20 |
| Canonical data | `_elementor_data` never written for translation storage |
| Invalidation | `save_post` @ 20 → mark dirty → `shutdown` @ 20 → `Store::sync_source` |
| Gutenberg coexistence | `Extractor::body_status()` → `BODY_ELEMENTOR`; `BlockRenderGate` denies block overlay |
| Activation | Both Elementor flags default **OFF** |

### Existing production module (`src/Elementor/`)

| File | Role |
|---|---|
| `Contract.php` | Frozen constants, segment prefix, meta keys, version family |
| `ElementorIdentity.php` | Hybrid-D key build/parse/validate |
| `ElementorDocumentDetector.php` | Detect Elementor posts; decode `_elementor_data` |
| `ElementorControlRegistry.php` | **Sole admission surface** — eight-widget allowlist |
| `ElementorExtractor.php` | Read-only tree walk → translation units |
| `ElementorOverlayResolver.php` | Store lookup + TI.7 eligibility |
| `ElementorOverlayApplier.php` | In-memory settings overlay |
| `ElementorFrontendBridge.php` | Request-time `builder_content_data` filter |
| `ElementorCacheInvalidation.php` | Language-safe cache busting |
| `ElementorCompatibility.php` | Elementor 4.2.x family gate |

---

## 2. Milestone objective

TSC.5 is a **hardening/completion milestone only**. It makes the **existing eight-widget A.2/A.3 allowlist** fully trustworthy within the TSC program by:

1. Closing the authoritative Elementor save/invalidation seam (frozen A2 event contract).
2. Hardening overlay safety (surface-neutral structural guard; frozen render-context gates).
3. Proving OTL/Jobs/TI.7/concurrency/PluginGuard for `e:d:` segments.

**TSC.5 does not admit new widgets, document types, or discretionary product scope.**

---

## 3. Coverage disposition matrix

### Document types

| Type | Disposition |
|---|---|
| Normal page/post/product (Elementor body, admitted post type) | **SUPPORTED** (flag-gated) |
| Elementor landing page | **SUPPORTED** (post-owned) |
| Saved template (`elementor_library`) | **DEFERRED** |
| Theme Builder header/footer/single/archive | **DEFERRED** |
| Popup | **DEFERRED** |
| Global widget | **DEFERRED** |
| Loop item / loop grid template | **DEFERRED** |

### Committed supported widget set (frozen — A1)

| Widget | Controls | Origin |
|---|---|---|
| `heading` | `title` | A.2 |
| `text-editor` | `editor` (HTML) | A.2 |
| `button` | `text` | A.2 |
| `accordion` | `tab_title`, `tab_content` | A.3 |
| `toggle` | `tab_title`, `tab_content` | A.3 |
| `image` | `caption` (custom source only) | A.3 |
| `icon-list` | `text` (repeater `_id`) | A.3 |
| `call-to-action` | `title`, `description`, `button` | A.3 Pro |

**No optional widget expansion work package exists.**

### Deferred widget families (TSC.5)

testimonial, icon-box, image-box, alert, progress, counter, tabs, forms, nav-menu, posts, portfolio, loop-grid, third-party widgets.

### Unsupported

| Family | Reason |
|---|---|
| `core/html`, `core/shortcode` (Elementor widgets) | Permanent deny-list |
| Dynamic tags (`__dynamic__`) | Config, not literal content |
| WooCommerce Elementor widgets | Deny-list; entity ownership elsewhere |
| URL/link-control objects | Machine configuration |
| CSS/code | Deny-list |
| Generic JSON translation | Architectural non-goal |
| Public registration API | TSC.6 |
| Direct meta-only `_elementor_data` writes | Unsupported source-mutation path (A2) |

---

## 4. Identity contract (frozen — unchanged)

**Flat (A.2):** `e:d:<owner_post_id>:<element_id>:<control_key>`

**Nested repeater (A.3):** `e:d:<owner_post_id>:<element_id>:<control_key>:<nested_item_id>`

- `nested_item_id` = Elementor repeater `_id` (mandatory; missing → skip/source)
- **No** path indices, text hashes, or AIML injection into `_elementor_data`
- Reorder/move: element IDs stable (A.R1 proven); segment keys stable
- Duplicate widget: new element ID → new keys
- Duplicate page: same element IDs scoped by distinct `owner_post_id`
- Widget type conversion: orphan-and-new semantics

---

## 5. Extraction architecture (unchanged)

```
Post (admitted type) → ElementorDocumentDetector → ElementorExtractor
  → ElementorControlRegistry allowlist → strategies → Store::sync_source
```

- **No** TSC.2 registered-meta walk of `_elementor_data`
- **No** full postmeta scan
- Registry is sole admission authority — no wildcard traversal

---

## 6. Invalidation — frozen A2 event contract

### Architecture (exact)

```
Elementor/normal post mutation
  → mark_dirty(SOURCE_POST, post_id)   [one or both hooks below]
  → RequestLocalInvalidationCoordinator coalesces by source_type:source_id
  → shutdown @ 20: sole Store::sync_source authority
  → extract reads final persisted _elementor_data from DB
```

**Rules:**
- **No** `sync_source`, provider calls, or extraction in Elementor hook callbacks.
- **No** `elementor/document/before_save` hook.
- **No** `updated_post_meta` / `added_post_meta` for `_elementor_data`.
- Existing autosave/revision `clear_dirty` behavior retained.

### Event matrix

| # | Path | Hook/event | Timing vs `_elementor_data` | Post ID | mark_dirty? | Role | Disposition |
|---|---|---|---|---|---|---|---|
| 1 | Normal Elementor editor save | `save_post` @ 20 | **Before** final Elementor persistence may complete | Yes | Yes | Redundant early mark; coalesced | Supported (existing) |
| 1b | Same request, authoritative | `elementor/document/after_save` @ 20 | **After** Elementor document persistence (incl. `_elementor_data`) | Yes (`$document->get_main_id()`) | Yes | **Authoritative** Elementor mark | Supported (**TSC5.1 add**) |
| 2 | Elementor REST/editor API save | `elementor/document/after_save` | After persistence | Yes | Yes | Authoritative | Supported |
| 3 | WordPress-only save (no Elementor tree change) | `save_post` @ 20 | May precede or omit Elementor meta write | Yes | Yes | Fallback mark; shutdown reads DB as-is | Supported |
| 4 | `_elementor_data` write timing | Inside Elementor `Document::save()` | Persisted before `after_save` fires | — | — | Evidence: A.R1 ER4 (10 listeners on after_save) | Supported (observed) |
| 5 | Direct `update_post_meta('_elementor_data')` only | None in AIML | No guaranteed hook pairing | Yes if meta set | **No** | Not merchant-normal; stale not guaranteed | **Unsupported** |
| 6 | Autosave / revision | `save_post` → `clear_dirty` | N/A | Yes | Cleared | Prevent sync of non-canonical copies | Supported (existing) |
| 7 | Shutdown flush | `shutdown` @ 20 | After all saves in request | — | Sync once per dirty ID | Sole sync authority | Supported (existing) |

### TSC5.1 implementation (exact)

Register on `elementor/document/after_save` @ priority 20:

- Resolve `$document->get_main_id()` → post ID
- Guard: admitted post type, not revision/autosave, valid ID > 0
- Call `RequestLocalInvalidationCoordinator::mark_dirty(Store::SOURCE_POST, post_id)`
- Location: `PostSurfaceAdapter::register_invalidation_events()` or dedicated `ElementorInvalidation` registrar

**Characterization test (AC29):** Elementor editor save mutates widget text → shutdown sync extracts **post-save** `_elementor_data` → correct segment stale/new/extract.

---

## 7. Stale/orphan semantics

Reuse `Store::sync_source()` per-segment-key granularity (proven TSC.4 A1) — **no Elementor-specific stale engine**.

---

## 8. Frontend render architecture (unchanged seam)

- Seam: **`elementor/frontend/builder_content_data`** via `ElementorFrontendBridge`
- Flow: extract units → Store resolve → in-memory apply → Elementor renders
- **Never** write translated values to `_elementor_data`

### Frozen render-context matrix (A4)

Gate evaluation order — all must pass before overlay:

1. `elementor_frontend_rendering_enabled` + `ElementorCompatibility::overlays_allowed()`
2. **Not** excluded context (table below)
3. `language_id > 0` and **not** source/default language
4. Valid Elementor document post ID
5. TI.7 eligibility via `ElementorOverlayResolver`

| Context | Overlay | Mechanism |
|---|---|---|
| **Normal visitor frontend** | **Allowed** | Non-source language; flags + TI.7 |
| **Source language** | **Denied** | `LanguageContext::is_default()` |
| **WP-ADMIN (non-AJAX)** | **Denied** | `is_admin() && !wp_doing_ajax()` |
| **Elementor editor** | **Denied** | `Plugin::$instance->editor->is_edit_mode()` |
| **Elementor preview / editor iframe** | **Denied** | `preview->is_preview_mode()` OR edit mode; **canonical source only** |
| **REST / JSON API** | **Denied** | `wp_is_json_request()` or `REST_REQUEST` |
| **Cron / WP-CLI** | **Denied** | `wp_doing_cron()` or `WP_CLI` |
| **Elementor frontend AJAX** | **Conditional** | **Allowed** only when edit=false, preview=false, non-source language; **Denied** for editor/internal AJAX |
| **Internal document serialization / save** | **Denied** | edit mode OR preview mode OR admin context |

**Product decision (frozen):** No authorized translation preview inside Elementor editor. Editor always shows canonical `_elementor_data` source.

---

## 9. Structural HTML safety (A3)

### Surface-neutral utility

**New:** `src/Translation/Safety/StructuralAttributeGuard.php`

Namespace: `AIMultilingual\Translation\Safety\StructuralAttributeGuard`

**Responsibilities only:**
- Compare `source_html` vs `candidate_html` protected structural attributes
- Protected: `href`, `class`, `id`, `target`, `rel`, `data-*` (frozen TSC.4 set)
- Return bool safe/unsafe; fail closed when equivalence cannot be proven

**Non-responsibilities:** sanitization, translation policy, provider admission, block/Elementor policy, HTML rewriting, DOM translation engine.

### Consumers

| Consumer | Change |
|---|---|
| `BlockStructuralAttributeGuard` | Thin delegate — **zero TSC.4 behavior regression** |
| Elementor HTML controls | After `ElementorSanitize`, before apply: `text-editor`/`editor`, accordion/toggle `tab_content` |

Plain-string controls: **unaffected**.

---

## 10. Cache / language invariant (A4)

**Invariant:** Rendered Elementor output for language L must never be reused for language L′ (L ≠ L′).

| Mechanism | Role |
|---|---|
| Element cache TTL = disable | Prevents cross-language HTML cache when overlays ON |
| Pre-render cache bust | `before_get_builder_content` → delete `_elementor_element_cache` |
| Language-scoped unique_id | `elementor/element_cache/unique_id` + `\|aiml:{code}` |
| Translation save bust | `aiml_translation_saved` → delete document cache + files clear |

**Verdict:** Existing mechanisms **fully satisfy** the invariant for the eight-widget committed scope. TSC.5 adds integration tests (AC20); no general page-cache subsystem.

**External page cache (Cloudflare, etc.):** Operator responsibility (same as TSC.4).

---

## 11. Gutenberg/body ownership (frozen)

- `Extractor::body_status()`: `_elementor_data` OR `_elementor_edit_mode` → `BODY_ELEMENTOR`
- Block extraction/render/UUID injection **inactive** on Elementor bodies
- Elementor extraction **inactive** on pure Gutenberg bodies
- **No double source rows**

---

## 12. OTL / Jobs / TI.7 / concurrency

| System | TSC.5 role |
|---|---|
| OTL | Prove `e:d:` rows list/edit/review/publish/stale; widget meta from extraction — no list-time `_elementor_data` reparse |
| Jobs (TI.6) | Prove materialization/snapshots/retry/conflict; no second engine |
| TI.7 | Unchanged; `ElementorOverlayResolver` publication gate unchanged |
| Concurrency | Reuse source_hash/translation_hash; test widget delete during job |

---

## 13. Activation (frozen)

- Both Elementor flags remain default **OFF**
- TSC.5 must **not** flip defaults or add admin UI that silently enables flags
- Frontend requires extraction (existing sanitize rule)

---

## 14. Provider security

Only registry-allowlisted visitor-facing strings enter provider payloads. Never send full `_elementor_data`, link objects, dynamic tags, form config, secrets.

---

## 15. EL requirement matrix (EL1–EL31)

| ID | Requirement | Disposition |
|---|---|---|
| EL1 | Current coverage inventory | Supported |
| EL2 | Element identity (Hybrid-D) | Supported |
| EL3 | Reorder/move stability | Supported |
| EL4 | Duplicate semantics | Supported |
| EL5 | Document types (page/post/product owned) | Partial |
| EL6 | Eight-widget committed scope only | Supported |
| EL7 | Registry authority | Supported |
| EL8 | Repeater `_id` | Supported |
| EL9 | Dynamic tags excluded | Supported |
| EL10 | Templates/globals | Deferred |
| EL11 | Gutenberg exclusivity | Supported |
| EL12 | Extraction | Supported |
| EL13 | Invalidation (frozen A2 contract) | Partial → Supported in TSC.5 |
| EL14 | Stale per-segment | Supported |
| EL15 | Render seam (`builder_content_data`) | Supported |
| EL16 | Render-context matrix (frozen A4) | Partial → Supported in TSC.5 |
| EL17 | Structural HTML safety (A3) | Partial → Supported in TSC.5 |
| EL18 | URL/link safety | Supported |
| EL19 | Forms | Deferred |
| EL20 | Dynamic entity ownership | Supported |
| EL21 | OTL | Partial → Supported in TSC.5 |
| EL22 | Jobs | Partial → Supported in TSC.5 |
| EL23 | TI.7 | Supported |
| EL24 | Concurrency | Partial → Supported in TSC.5 |
| EL25 | Activation defaults OFF | Supported |
| EL26 | Elementor absent safe | Supported |
| EL27 | Cache/language separation (frozen A4) | Partial → Supported in TSC.5 |
| EL28 | Provider security | Partial → Supported in TSC.5 |
| EL29 | Performance bounded | Partial → Supported in TSC.5 |
| EL30 | Backward compatibility | Supported |
| EL31 | PluginGuard + TSC.6 exclusion | Partial → Supported in TSC.5 |

---

## 16. Work package ladder (TSC5.0–TSC5.6)

| WP | Scope |
|---|---|
| **TSC5.0** | Inventory freeze; invalidation ordering characterization; identity fixtures; EL/AC freeze |
| **TSC5.1** | `elementor/document/after_save` mark_dirty per frozen A2 contract |
| **TSC5.2** | Surface-neutral `StructuralAttributeGuard`; Block delegate refactor; Elementor HTML wiring |
| **TSC5.3** | Frozen render-context gate in `ElementorFrontendBridge`; Resolver/Bridge integration tests |
| **TSC5.4** | OTL/Jobs/TI.7/concurrency regression for `e:d:` |
| **TSC5.5** | `assert_tsc5_invariants()`; cache/language integration tests; 100-element performance |
| **TSC5.6** | Evidence, validation log, CHANGELOG, bounded browser smoke (`acceptance/tsc5-elementor/`, non-CI) |

---

## 17. Acceptance criteria (AC1–AC30)

| ID | Criterion |
|---|---|
| AC1 | Eight-widget allowlist extracts exactly once per admitted control |
| AC2 | Unsupported settings never extract |
| AC3 | Stable identity under text edit |
| AC4 | Stable identity under reorder/move |
| AC5 | Duplicate widget/page semantics correct |
| AC6 | One field change stales only that row |
| AC7 | Widget delete orphans correct rows |
| AC8 | No canonical `_elementor_data` translated write |
| AC9 | Visitor frontend receives overlay when flags ON + eligible |
| AC10 | Frozen context matrix: editor/preview/admin/REST/cron/source = canonical source |
| AC11 | URLs/link config unchanged |
| AC12 | Structural HTML guard fails closed (Elementor HTML controls) |
| AC13 | TSC.4 Block structural guard behavior unchanged after refactor |
| AC14 | Dynamic entity text not duplicated |
| AC15 | Gutenberg/Elementor body exclusivity |
| AC16 | OTL works for Elementor segments |
| AC17 | Jobs snapshots/conflicts work |
| AC18 | TI.7 unchanged |
| AC19 | Both Elementor flags default OFF |
| AC20 | Cache/language invariant proven by integration tests |
| AC21 | Elementor absent → no fatal |
| AC22 | Provider payload registry-allowlisted only |
| AC23 | Performance bounded (100-element fixture) |
| AC24 | Existing `e:d:` rows remain valid |
| AC25 | No public registration API |
| AC26 | No SOURCE_ELEMENTOR |
| AC27 | TARGET 7 / no migration |
| AC28 | PluginGuard TSC.5 invariants pass |
| AC29 | `elementor/document/after_save` + shutdown reads final `_elementor_data` |
| AC30 | Elementor frontend AJAX: overlay only on visitor render; editor AJAX canonical |

---

## 18. Test strategy

- **Unit:** identity, registry deny, repeater `_id`, `StructuralAttributeGuard`, Block delegate regression, dynamic-tag exclusion
- **Integration:** A2 invalidation contract; shutdown-final-data proof; render-context matrix; frontend overlay; stale granularity; OTL; Jobs; publication; Elementor absent
- **Security:** provider payload audit; link object exclusion
- **Architecture:** PluginGuard TSC.5; no canonical writes; no wildcard traversal
- **Performance:** 100-element document
- **Browser (local/non-CI, 7 scenarios):** Heading, Text Editor, Button, nested container, source edit→stale, editor shows source, link URL unchanged

---

## 19. PluginGuard locks (TSC5.5)

- `elementor/document/after_save` mark_dirty present
- No `_elementor_data` meta invalidation hook
- No `before_save` mark_dirty
- `StructuralAttributeGuard` in `Translation/Safety`
- Frozen render-context checks in `ElementorFrontendBridge`
- No SOURCE_ELEMENTOR; no canonical writes; flags default OFF; TARGET 7
- No TSC.6 public registration API leakage

---

## 20. Production value

When flags are deliberately enabled, merchants can translate **eight widget families / ~15 control paths** on **document-owned** Elementor pages already in Store/OTL. TSC.5 completes TSC-program trust — **not** "full Elementor support."

---

## 21. Explicit non-goals

- Widget expansion beyond A.2/A.3 eight families
- Navigation/query/reusable/FSE/table/search/custom blocks
- Elementor forms (Deferred)
- Generic JSON/HTML translation engine
- URL translation
- Canonical `_elementor_data` translated writes
- Public widget registration API (TSC.6)
- Duplicate post/term/Woo ownership
- Optional/discretionary implementation scope

---

## 22. STOP audit

| Trigger | Result |
|---|---|
| Unstable path-index identity | Not used |
| AIML ID in `_elementor_data` | Not planned |
| New table / TARGET bump | Not required |
| SOURCE_ELEMENTOR | Rejected |
| Generic HTML postprocessor | Rejected |
| Canonical translated writes | Rejected |
| Optional widget expansion | Removed (A1) |
| Unsafe cross-language cache | Mitigated + tested (A4) |

**Result: STATE A holds. No ARCHITECTURE DECISION REQUIRED.**

---

## 23. Exact next step

Implement TSC.5 from this frozen plan via `feature/tsc5-elementor-coverage-expansion` when authorized. Do **not** start TSC.6+. Do **not** bump version/TARGET, tag, release, or deploy as part of planning freeze.
