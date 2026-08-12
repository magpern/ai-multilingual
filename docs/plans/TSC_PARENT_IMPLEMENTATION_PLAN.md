# Translation Surface Coverage (TSC) — Parent Program Architecture Plan

**Status:** **Architecture Frozen** on `main` — **TSC.0–TSC.3 COMPLETE**; TSC.4–TSC.6 **NOT STARTED**
**Program:** Translation Surface Coverage (TSC)
**Plan freeze:** Canonical program architecture for milestones **TSC.0–TSC.6**; surface discovery/extraction/persistence/resolution contracts around the existing Store; public/SaaS site neutrality; Deferred/Unsupported boundaries
**External review:** **FREEZE** · **STATE A** · **TARGET 7**
**Independent planning review:** **PASS** ([TSC_PARENT_PLANNING_VALIDATION_LOG.md](TSC_PARENT_PLANNING_VALIDATION_LOG.md))
**ADR assessment:** **No ADR created during this parent freeze.** TSC.1 planning **must** create/review an ADR covering `SOURCE_TERM` / TERM_ID / subtype / lazy adoption / read-alias / single-writer / lifecycle-axis preservation / term visibility facts / visitor-only overlay policy (see §15).
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — Program A coverage themes and historical Deferred surfaces remain catalogued; **generic post-v1.3.0 surface-coverage work is governed by this TSC parent**. Do not resurrect superseded Program C lifecycle items (OTL owns those).
**Implementation priority:** [PRODUCT_PRIORITIES.md](../PRODUCT_PRIORITIES.md)
**Planning branch:** `docs/tsc-parent-planning-freeze` (merged)
**Freeze merge:** `main` @ `8c93d505a2afc7d9ebc14a29a44d9d3ceb98e41b` (`merge: freeze Translation Surface Coverage parent architecture`)
**Depends on:** AI Multilingual **v1.3.0** released; **TIQ Complete** (TQ.0–TI.7); **OTL Complete** (OTL.0–OTL.6); `Migrator::TARGET` **7**; Integration API v1 unchanged
**Related:** [adr/0001-translation-overlay-not-duplication.md](../adr/0001-translation-overlay-not-duplication.md); [adr/0005-segment-centric-storage.md](../adr/0005-segment-centric-storage.md); [adr/0007-hash-semantics.md](../adr/0007-hash-semantics.md); [adr/0013-gutenberg-segment-identity.md](../adr/0013-gutenberg-segment-identity.md); [adr/0016-elementor-identity-and-ownership.md](../adr/0016-elementor-identity-and-ownership.md); [adr/0017-plugin-integration-framework-ownership-and-identity.md](../adr/0017-plugin-integration-framework-ownership-and-identity.md); [adr/0015-review-workflow-and-tm-approval-policy.md](../adr/0015-review-workflow-and-tm-approval-policy.md); [adr/0020-controlled-auto-publication-and-frontend-gate.md](../adr/0020-controlled-auto-publication-and-frontend-gate.md); [TIQ_PARENT_IMPLEMENTATION_PLAN.md](TIQ_PARENT_IMPLEMENTATION_PLAN.md); [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md); [INTEGRATION_API_V1.md](../INTEGRATION_API_V1.md)

**Operational success:** More legitimate WordPress/WooCommerce visitor-facing content can enter the existing find → risk/attention → edit/review → publish → verify → Jobs/bulk lifecycle **without** redesigning OTL, duplicating TIQ/Jobs/publication policy, inventing a second Store, or becoming site-specific.

**This plan is the program architecture contract for TSC (TSC.0–TSC.6).** Do not implement production code under TSC until the relevant milestone plan is Architecture Frozen on `main`. Each milestone receives its own definitive planning freeze before implementation. This document freezes program boundaries, identities, coexistence rules, stale honesty, gates, and Deferred items — not detailed TSC.0 work packages.

**Production implementation status:** **TSC.0–TSC.3 COMPLETE**; TSC.4–TSC.6 **NOT STARTED.**

**Next:** Plan/implement **TSC.4** only when separately authorized. Do **not** start TSC.4 until authorized.

---

## 1. Executive objective

Expand **site-neutral** WordPress/WooCommerce visitor-facing **coverage** into the existing machinery:

```text
surface discovery
  → stable identity
  → extraction
  → existing Store
  → TIQ / Jobs / OTL
  → Store persistence
  → admitted frontend overlay
  → stale / retranslate / orphan
```

**TSC owns** translation-surface discovery, extraction, persistence/resolution adapters, identity grammars, admission boundaries, invalidation registration, and deletion/orphan mechanics (**facts and mechanics**).

**TSC does not own** QA policy, assessment policy, Jobs admission policy, review policy, publication eligibility policy, or OTL orchestration policy.

TSC must **not** redesign the OTL lifecycle already delivered in v1.3.0.

---

## 2. Repository baseline

Verified at parent Architecture Freeze authoring:

| Field | Value |
|---|---|
| Branch baseline | `main` == `origin/main` @ `a2445f8141a2addd798225d5f224022387b6994c` |
| Working tree | Clean at branch creation |
| Plugin version | **1.3.0** |
| Latest release tag | `v1.3.0` @ `c88ba30681439d9e7113a20d7ebc03c942dd240d` |
| Note | HEAD is one docs-closure commit after the release tag (unreleased docs only) |
| `Migrator::TARGET` | **7** |
| TIQ | **Complete** (TQ.0–TI.7) |
| OTL | **Complete** (OTL.0–OTL.6) |
| TSC before this freeze | Not started (no TSC parent on `main`) |
| Planning branch | `docs/tsc-parent-planning-freeze` |

If any precondition regresses before a milestone starts coding: **STOP**.

### Architecture spine (authoritative)

| Concern | Authority | Path |
|---|---|---|
| Segment Store | `Store` | `src/Translation/Store.php` |
| Schema / TARGET | `Schema`, `Migrator::TARGET = 7` | `src/Database/Schema.php`, `src/Database/Migrator.php` |
| Extract | `Extractor` + Block/Elementor/Integrations | `src/Translation/Extractor.php` |
| Assemble/sync | `SegmentAssembler` → `Store::sync_source` | `src/Workspace/SegmentAssembler.php` |
| Orphan on missing segment | `sync_source` → `status=ignored`, `error_code=orphaned` | `Store::sync_source` |
| Stale hook (v1.3.0) | `Plugin::register_stale_detection` — **`save_post` only** | `src/Plugin.php` |
| Frontend | `Renderer`, Block/Elementor bridges, Integration bridges | overlay model (ADR-0001) |
| OTL mutate gate | post-only | `src/Workspace/Operator/` |
| Publication publicness | post publish only today | `PublicationService::is_source_public` |
| Integration API v1 | `PluginIntegrationInterface` | `src/Integration/` · ADR-0017 |

**Physical uniqueness:** `(source_type, source_id, segment_hash, language_id)` where `segment_hash = sha1(field_key + "\x1f" + segment_key)`.

**`source_subtype` is NOT part of the UNIQUE KEY** (listing/TM indexing only).

Only defined runtime constant today: `Store::SOURCE_POST = 'post'`. Segment families: `post_*` · `b:` · `e:` · `p:`.

Built-in integrations registered in `Plugin.php`: Fluent Forms, WooCommerce, Rank Math.

---

## 3. Authority map

```text
TSC (facts/mechanics)
  → Extractors / hash material / overlay seams / orphan semantics
    → Store (aiml_translations)
      → TIQ (QA / assessment / TranslationService)     [policy]
      → TI.6 Jobs                                       [policy]
      → TI.7 PublicationPolicy / PublicationService     [policy]
      → OTL Operator orchestration                      [presentation/orchestration]
```

**Non-negotiable:** one Store; ADR-0001 overlay; ADR-0017 Integration ownership; ADR-0016 Elementor; ADR-0013 Gutenberg; ADR-0011 Jobs; ADR-0015 Review; ADR-0020 Publication.

OTL / TI.6 / TI.7 continue making their **own authoritative decisions** using TSC-supplied facts — TSC must not become a second policy engine.

---

## 4. Definition of a translation surface

A **translation surface** is an **internally admitted, visitor-facing text-bearing unit** that:

1. Declares **stable source identity** in the existing Store model
2. Can prove **source existence** and supply **extract + source-hash material**
3. Declares **object authorization** and **source visibility facts** (inputs to TI.7 — not publish decisions)
4. Declares **admitted overlay resolver seam(s)** (visitor/output only)
5. Declares **supported lifecycle capabilities** (inspect / translate / mutate / jobs / publish-eligible inputs)
6. Declares **deletion/removal/orphan** behavior reusing Store semantics where possible
7. Is **site-neutral** (no hardcoded site/form/page IDs; no Biopentra-specific production behavior)

**Not** every string in WordPress is a surface. Gettext msgids, scraped DOM, private meta, and unbounded option scans are **not** surfaces unless a later ADR proves a deterministic overlay seam.

**Inspection ≠ mutation.** OTL may display rows that cannot yet be mutated.

---

## 5. Schema / TARGET verdict — STATE A

| Decision | Value |
|---|---|
| Schema verdict | **STATE A** |
| `Migrator::TARGET` | **7** (unchanged) |
| New migration | **None** |
| Second Store | **Forbidden** |

TERM_ID identity and lazy adoption fit existing `VARCHAR`/`BIGINT` columns. **STOP → STATE B ADR** only if string-keyed first-class options/theme_mods become mandatory without an acceptable host pattern, or `segment_key` VARCHAR(191) proves insufficient.

---

## 6. TERM IDENTITY VERDICT — TERM_ID

### Canonical first-class identity (frozen)

```text
source_type      = 'term'          // Store::SOURCE_TERM when introduced
source_id        = term_id         // wp_terms.term_id
source_subtype   = taxonomy slug   // e.g. product_cat, category, pa_color
segment_key      = native field keys for term content (see coexistence; exact literals = TSC.1)
```

### Evidence (repository-grounded)

| Evidence | Finding |
|---|---|
| Woo C3–C6 keys | `p:woocommerce:{taxonomy}:{term_id}:name\|description` |
| Rank Math term SEO | `p:rankmath:term:{term_id}:…` |
| Overlays | Queried object / filter args use `term_id`; `get_term( $term_id )` read-only |
| `term_taxonomy_id` in `src/` | **Zero** usages |
| Term lifecycle hooks in AIML (v1.3.0) | **None** |
| WP hooks when added | `( $term_id, $tt_id, $taxonomy )` — use `$term_id` + `$taxonomy` |
| Modern split-term history | Not a current correctness blocker for TERM_ID on modern WP |
| Global `pa_*` values | Taxonomy terms → same TERM_ID model when admitted |
| Schema | BIGINT `source_id` fits; no TARGET bump |

### Rejected: TERM_TAXONOMY_ID

Would desync from every existing `p:` key and overlay path; zero codebase adoption; no compensating benefit for modern WP.

### source_subtype VERDICT

**`source_subtype` = taxonomy slug**, mirroring posts’ `source_subtype = post_type`.

Used for OTL labeling, TM domain allowlist (`TMDomainAllowlist` already lists taxonomies), capability context, Jobs labeling.

**Not** part of UNIQUE KEY — native first-class segment keys must not rely on subtype for disambiguation.

---

## 7. HOSTED TERM COEXISTENCE VERDICT — Lazy adoption

### Representation today (Woo `product_cat` / `product_tag` name+description)

| Aspect | Reality |
|---|---|
| Extract | Shop page only → `WooCommerceIntegration::extract_catalog_term_units` |
| Store host | `source_type=post`, `source_id=shop_page_id`, `source_subtype=page` |
| Keys | `p:woocommerce:{taxonomy}:{term_id}:name\|description` |
| Axes | Full Store row: text, hashes, status, review (ADR-0015), publish (ADR-0020), timestamps |
| FE | `IntegrationFrontendBridge` resolves Store on **shop page id**; overlays `single_term_title`, `woocommerce_page_title`, `term_description` |
| Stale | **UNSUPPORTED** (term edits do not fire observed hooks) |
| Jobs | Job scoped to `(post, shop_page_id, language)`; items by `segment_key` |
| Remap utility | **None** today |

Rank Math term SEO uses the same **hosting pattern** (shop or posts page) with keys `p:rankmath:term:{term_id}:…` — sibling coexistence case (plugin-owned meta under ADR-0017).

### Selected architecture (frozen)

**Lazy adoption with temporary read-alias.**

Required invariants:

- exactly one authoritative translation state for a logical term field;
- no silent duplicate authority;
- no silent target-text loss;
- no review-state reset;
- no publication-state reset;
- no hash/concurrency weakening;
- no Jobs ambiguity;
- existing v1.3.0 data remains usable;
- no second Store;
- no TARGET bump for this coexistence.

Parent-frozen rules:

1. **Authoritative identity for NEW native term-content rows:** `(source_type=term, source_id=term_id, source_subtype=taxonomy, language_id, segment_key)` with native keys such as `name` / `description` (exact literals = TSC.1).
2. **Find existing hosted Woo row:** `(post, shop_page_id, language_id, p:woocommerce:{tax}:{term_id}:name|description)` where `shop_page_id` comes from `wc_get_page_id('shop')` at lookup time.
3. **On first authoritative touch:** copy **all applicable lifecycle axes** to the authoritative identity; retire the hosted row as `status=ignored` with `error_code` cleared (**not** `orphaned` — orphan remains missing-extract/delete semantics per ADR-0021); **never dual-write thereafter**.
4. **Physical coexistence:** temporary only (hosted readable until adopted).
5. **Reads:** prefer authoritative first-class identity; hosted identity is **fallback/read-alias only**; read-alias never writes.
6. **Rank Math term SEO:** may use the same lazy-adoption *pattern* to move host to `source_type=term` while **keeping** `p:rankmath:…` keys; ordering vs native name/desc is TSC.1 sequencing.
7. **Rollback:** leave hosted rows intact until adopt; adopt is additive then retire hosted.

### Explicitly left to TSC.1 planning / ADR

- Exact adopt triggers (first edit vs first Jobs item vs admin tool)
- Alias sunset / optional eager backfill sweep
- Precise `field_key` / `segment_key` literals and cache invalidation
- Rank Math sibling adopt ordering
- Term stale hook wiring details

### Parent rejects

- “Allowlist `SOURCE_TERM`” without find-hosted + state-preserving adopt
- Permanent dual-write
- Eager wipe/migrate that resets review/publish
- TARGET bump solely for coexistence

---

## 8. Discovery / extraction / persistence / frontend

### Discovery

- Event-driven sync per surface family (extend beyond `save_post`)
- Registration/admission-driven — **no unbounded site scans**
- Lazy assemble on workspace/Jobs remains valid
- Bounded catalog queries; large Woo catalogs must not re-walk all terms on incidental shop saves without diff/cache strategy (TSC.1 performance gate)

### Extraction

Compose existing extractors; add term/meta adapters only after identity/admission contracts exist. Structured content stays **segmented** (never opaque JSON/blob translation).

### Persistence / resolution

**Model A only (ADR-0001):** Store overlay. Canonical WP/Woo objects remain source-language.

### Term frontend overlay (frozen boundary)

- Source WP/Woo term data remains canonical
- **Never** rewrite term tables for translation
- **Never** broadly mutate `get_term` / canonical term objects for admin/internal/business logic
- Translated values apply **only** through admitted visitor/output seams
- Feasibility is already proven by existing Woo output filters (`single_term_title`, `woocommerce_page_title`, `term_description`) without `get_term` mutation
- **Exact hook/filter set** remains a **TSC.1 planning gate** (not frozen here)

---

## 9. Structured-content safety

| Family | Identity | Write safety |
|---|---|---|
| Gutenberg | `b:` + UUID (ADR-0013) | Adapter apply in memory on render |
| Elementor | `e:` + element/control (ADR-0016) | Mutate request data tree only |
| Integrations | `p:` validated grammar (ADR-0017) | Owner filters only |
| Registered meta (TSC.2) | code-owned allowlisted paths | Scalar/bounded structured paths only; reject serialized PHP |

Hard rules: no whole-blob translation of serialized/JSON structures; preserve non-text keys; hash text payloads only; reuse existing concurrency locks.

**Capability ≠ Activation.** Gutenberg/Elementor feature flags default OFF in v1.3.0 and must **not** be silently enabled by coverage expansion. Per-adapter activation decisions are explicit (TSC.4 / TSC.5).

---

## 10. Source hashing / concurrency / permissions

- Hashing: ADR-0007; each adapter declares hash material and invalidation events
- Concurrency: existing `source_hash` / `translation_hash` / review / publish optimistic locks and Jobs conflict evaluation
- Permissions: surface auth facts (`edit_term`, taxonomy caps, etc.) — **no** global “edit all surfaces” capability

---

## 11. Stale-event inventory — CURRENT v1.3.0 (frozen honesty)

**Sole observed automatic stale event:** `save_post` → extract → `Store::sync_source`.

**Also:** opportunistic `sync_source` on Workspace assemble (not event-driven invalidation).

**Rule:** extraction/render success **≠** reliable stale invalidation. If source can change without an observed invalidation event, Stale cannot be **SUPPORTED**.

| ID | Surface | Source of truth | save_post reliable? | Other hooks needed | Observed? | **CURRENT Stale** | Target close |
|---|---|---|---|---|---|---|---|
| S1 | post_title | `WP_Post` | Yes | — | Yes | **SUPPORTED** | — |
| S2 | post_excerpt | `WP_Post` | Yes | — | Yes | **SUPPORTED** | — |
| S3 | classic post_content | `WP_Post` | Yes | — | Yes | **SUPPORTED** | — |
| S4 | Gutenberg leaves (flag on) | block markup | Yes* | — | Yes | **SUPPORTED** | TSC.0 note autosave skip |
| S5 | Elementor (flag on) | `_elementor_data` | Usually | `elementor/document/after_save` | save_post only | **PARTIAL** | TSC.5 |
| S6 | nav_menu_item title | menu item post | Yes | — | Yes | **SUPPORTED** | — |
| S7 | Woo product title/excerpt/content | product CPT | Yes | — | Yes | **SUPPORTED** | — |
| S8a | Woo attr names (product-local) | product attributes | Yes | — | Yes | **PARTIAL**† | TSC.0/3 |
| S8b | Woo attr names (global rename) | attribute admin | **No** | `woocommerce_attribute_updated` etc. | No | **UNSUPPORTED** | TSC.3 |
| S9 | Woo product_cat/tag name+desc | `wp_terms` | **No** | `edited_term` / `created_term` / `delete_term` | No | **UNSUPPORTED** | TSC.1 |
| S10 | Woo orderby/orderedby | hardcoded extract + filters | No | filter drift | No | **UNSUPPORTED** | DEFERRED / TSC.3 honesty |
| S11 | Woo checkout/account/thank-you/totals chrome | filters / hardcoded | No | filter-time | Overlay only | **UNSUPPORTED** | DEFERRED / TSC.3 |
| S12 | Woo email subject/heading | WC email options | **No** | `updated_option` | No | **UNSUPPORTED** | TSC.3 |
| S13 | Rank Math post SEO | post meta | Often | `updated_post_meta` | save_post only | **PARTIAL** | TSC.0 |
| S14 | Rank Math term SEO | term meta | **No** | term edit / termmeta | No | **UNSUPPORTED** | TSC.1 |
| S15 | Fluent Forms labels | form definition DB | **No** | Fluent form-save | Overlay only | **UNSUPPORTED** | TSC.0 |

\*Autosave/revision explicitly skipped in stale glue.  
†Family treated **PARTIAL** overall: product-save path works; global rename does not.

---

## 12. Internal surface capability contract (TSC.0)

TSC.0 introduces an **INTERNAL** capability abstraction only (name is an implementation detail).

It supplies **facts and mechanics**:

1. Source identity
2. Existence / not-found
3. Extraction → segment inventory
4. Source-hash material
5. Object authorization facts
6. Source visibility facts
7. Admitted overlay resolver registration
8. Supported lifecycle capability flags
9. Invalidation / sync hook registration
10. Deletion/removal/orphan semantics

It does **NOT** own: QA policy; assessment policy; Jobs policy; review policy; publication policy; OTL orchestration policy.

**No public SurfaceRegistry / public surface-registration API** is frozen by this parent. Public extension/SEO contract stabilization belongs to **TSC.6**, after the internal model is proven on terms, CPT admission, and registered meta.

OTL / Jobs / Publication must consume capability flags from this contract rather than scattering independent `if ( source_type === 'term' )` policy forks.

---

## 13. Deletion / orphan semantics

Reuse existing Store lifecycle where possible:

- Segment missing from extract → `status=ignored`, `error_code=orphaned` (`Store::sync_source`)
- Do **not** invent a second lifecycle vocabulary
- Publication eligibility and Jobs admission must refuse orphaned/ignored rows appropriately
- Object deletion (term deleted, meta unregistered, CPT deleted, block/control gone, integration unregisters) must have deterministic adapter behavior under this contract
- Retention/cleanup may follow ADR-0004 patterns but must be acknowledged in TSC.0

---

## 14. TIQ / OTL / Jobs / Publication integration

| System | TSC supplies | Must not do |
|---|---|---|
| TIQ | Valid segments + hash material | New QA/assessment policy |
| OTL | Lifecycle capability flags + identity labeling facts | Second workflow engine; scattered type forks |
| Jobs | Existence/extract/conflict-capable admission inputs | Unbounded discovery fan-out |
| Publication | Source visibility facts for new families | Bypass TI.7; force-publish |

Capability classes: full lifecycle · translate/read-only · later mutation.

---

## 15. ADR position

**No ADR is created during this parent freeze.**

**TSC.1 planning must** create/review an ADR covering at minimum:

- `SOURCE_TERM` identity
- TERM_ID
- `source_subtype` = taxonomy slug
- Lazy adoption
- Read-alias
- Single-writer / dual-write prohibition
- Lifecycle-axis preservation
- Term visibility facts for TI.7
- Visitor-only overlay policy

Do not prematurely freeze TSC.1 implementation details this parent leaves open (adopt triggers, alias sunset, exact hooks, field_key literals).

---

## 16. Registered meta (TSC.2)

Conservative **code-owned allowlist** first:

- Explicit scalar or bounded structured-path declarations
- Explicit frontend resolution seams
- Explicit source-hash material
- Explicit permissions
- No wildcard keys
- No arbitrary callbacks from admin settings
- No serialized PHP values
- `show_in_rest` does **not** mean provider-safe
- **No public** `register_translatable_meta()` API in this parent; public stabilization → TSC.6 or later
- Arbitrary postmeta auto-scan remains **UNSUPPORTED**

---

## 17. Fluent Forms neutrality (TSC.0)

Current hardcoded `FORM_ID = 5` / `CONTACT_PAGE_ID = 3410` in `FluentFormsIntegration` is a **site-neutrality defect**.

TSC.0 must:

- remove hardcoded site/form/page IDs from production behavior; and
- genericize via **bounded** evidence-based embed/host discovery and/or explicit integration configuration; **or**
- disable / tightly limit that path

**Forbidden:** replace hardcoding with an unbounded sitewide Fluent Forms scan.

---

## 18. Woo attribute-value split

| Surface | Ownership |
|---|---|
| Global attribute **values** (`pa_*` taxonomy terms) | **TSC.1** first-class term model when those taxonomies are admitted |
| Product-local / custom attribute text | **TSC.3** only if stable identity is proven; otherwise Deferred |
| Attribute **names** (P5/P7) | Already present; global rename stale → TSC.3 |

Do not create two authoritative paths for the same customer-visible global attribute value.

---

## 19. Coverage matrix summary (TS)

Disposition vocabulary: `SUPPORTED` | `PARTIAL` | `DEFERRED` | `UNSUPPORTED`  
Activation: `ON` | `OPT-IN` | `N/A`

| ID | Surface | CURRENT Stale | Activation | Target milestone notes |
|---|---|---|---|---|
| TS1–5 | Core post/product/nav titles | S | ON | Maintain |
| TS6 | Gutenberg allowlisted | S‡ | **OPT-IN** | TSC.4 expands; no silent ON |
| TS7 | Elementor allowlisted | P | **OPT-IN** | TSC.5; document-save seam |
| TS8 | Woo attr names | P | ON | TSC.3 global rename hooks |
| TS9 | Woo cat/tag hosted | U | ON | TSC.1 TERM_ID + lazy adopt + term hooks |
| TS10 | Rank Math post SEO | P | ON | TSC.0 meta observers |
| TS11 | Rank Math term SEO | U | ON | TSC.1 sibling adopt + term hooks |
| TS12 | Woo journey/email chrome | U | ON | TSC.3 events or remain Deferred honesty |
| TS13 | Fluent Forms | U | ON§ | TSC.0 neutrality + form-save or disable |
| TS14 | First-class WP terms | — | — | TSC.1 |
| TS15 | Woo terms adopted | — | — | TSC.1 lazy adoption |
| TS16 | Term stale hooks | — | — | TSC.1 |
| TS17 | Capability wiring | — | — | TSC.0→1 |
| TS18 | Code-owned registered meta | — | — | TSC.2 |
| TS19 | CPT opt-in | — | — | TSC.0 |
| TS20a | Global `pa_*` values as terms | — | — | TSC.1 if admitted |
| TS20b | Product-local attr text | — | — | TSC.3 or Deferred |
| TS21–22 | More blocks/Elementor | — | OPT-IN | TSC.4/5 |
| TS23 | Public SEO/extension API | — | — | TSC.6 |
| TS24 | Internal surface contract + orphan | — | — | TSC.0 |
| TS25 | Fluent Forms neutrality | — | — | TSC.0 |
| TS40 | theme_mods / Blocksy chrome | D | — | Deferred (site-global host gap) |
| TS41 | Age Gate / Cookie banners | D | — | Deferred |
| TS42 | Gettext-as-surface | U | — | Unsupported |
| TS43 | HTML scrape | U | — | Unsupported |
| TS44 | Translated leaf slugs SA1–SA6 | D | — | Deferred (SEO ADR gate) |
| TS45 | Arbitrary postmeta auto-scan | U | — | Unsupported |
| TS46 | Email HTML body CE7/CE8 | D | — | Deferred |
| TS47 | Cart/mini-cart/notices | D | — | Deferred |
| TS48 | Menu description/attr_title | D | — | Deferred |
| TS49 | Second translation Store | U | — | Unsupported |
| TS50 | Biopentra-specific adapters | U | — | Unsupported |

‡When extraction enabled and content saved via `save_post`.  
§Non-neutral until remediated.

---

## 20. Milestone ladder — TSC.0–TSC.6

```text
TSC.0 Internal contract
  ├─→ TSC.1 Terms + coexistence
  ├─→ TSC.2 Meta allowlist
  ├─→ TSC.4 Gutenberg
  └─→ TSC.5 Elementor
TSC.1 + TSC.2 → TSC.3 Woo-specific remainder
TSC.1–TSC.5 → TSC.6 Public extension / SEO stabilization
```

| Milestone | Responsibility |
|---|---|
| **TSC.0** | Internal surface capability foundation: internal contract; deletion/orphan semantics; CPT opt-in; stale multi-hook contract; Rank Math postmeta observation; Fluent Forms neutrality; PluginGuard site-ID protections; honest surface characterization. **No public API.** |
| **TSC.1** | First-class taxonomy terms: `SOURCE_TERM` / TERM_ID; subtype taxonomy slug; hosted Woo lazy adoption/read-alias; complete lifecycle-state preservation; term lifecycle/stale events; admitted visitor overlays; capability integration with OTL/Jobs/TI.7; global `pa_*` term values where admitted. **Requires milestone ADR.** |
| **TSC.2** | Conservative code-owned registered-meta allowlist. Still internal. |
| **TSC.3** | Woo-specific remainder: attribute rename invalidation; email/config invalidation where feasible; local/custom product attributes only if stable identity proven; unsupported filter-backed chrome may remain Deferred. **Must not duplicate global `pa_*` term ownership.** |
| **TSC.4** | Gutenberg adapter expansion. Explicit activation discipline. **Do not merge with TSC.5.** |
| **TSC.5** | Elementor control expansion and authoritative save/invalidation seam. Explicit activation discipline. |
| **TSC.6** | Public extension/SEO contract stabilization after internal architecture proven across earlier milestones. |

Each milestone: definitive plan → external review → materialize → independent review → freeze on `main` → only then implementation branch.

---

## 21. Performance / security / site neutrality

- No request-time full `postmeta` / options / global Elementor scans
- No OTL list N+1 adapter fan-out; no frontend N+1 resolution
- Catalog-scale expectations for Woo (products/terms) are milestone acceptance gates
- Provider payloads: admission-only; never secrets, credentials, private operational meta
- PluginGuard must ban Biopentra brand tokens **and** hardcoded site/post/form IDs in product code
- **TSC production architecture is site-neutral.** Biopentra may be a test site, never the domain model

---

## 22. Program-level acceptance / quality gates

- Adapter contract tests (identity, extract, hash, auth facts, visibility facts, overlay seam confinement, lifecycle flags, orphan/deletion)
- Stale-event honesty tests per family
- Hosted-term coexistence tests (no dual authority; axes preserved)
- Concurrency / Jobs / publication compatibility without policy duplication
- Provider payload safety
- PluginGuard neutrality including site IDs
- Catalog scale smoke where relevant
- TARGET remains 7 unless STATE B ADR accepted
- Browser acceptance for admitted visitor overlays only

---

## 23. STOP triggers

Halt and escalate rather than paper over:

- Second translation Store
- Destructive writes into WP/Woo as translation mechanism
- Arbitrary meta/options scanning
- Biopentra-specific production behavior
- Silent TARGET bump
- Duplicating TIQ / Jobs / OTL / publication policy
- Unbounded discovery on frontend/admin requests
- OTL pretending inspection equals mutation
- Broad `get_term` mutation as frontend architecture
- Duplicate authoritative term translations / dual-write
- Silent lifecycle reset on identity adoption
- Schema change without explicit STATE B justification
- Public surface API promised before TSC.6 proof
- Silently enabling Gutenberg/Elementor feature flags as “coverage”

---

## 24. Versioning / release

- This freeze does **not** bump plugin version, tag, or release
- Keep `TARGET = 7`
- Future coverage release train (e.g. v1.4.x) is decided at release prep, not here

---

## 25. Exact next action

This parent is **Architecture Frozen** on `main` (freeze merge `8c93d505a2afc7d9ebc14a29a44d9d3ceb98e41b`).

**Exact next step:** Begin definitive **TSC.1** milestone planning when authorized. Do not implement TSC.1 until its plan is frozen on `main`.

Do **not** author the TSC.1 ADR until TSC.1 planning (default).

---

## Appendix A — Deferred / Unsupported (hard rejects)

- Unlimited “translate every WordPress string”
- Gettext capture keyed by msgid
- HTML scraping
- Site-global theme_mods / options as first-class BIGINT identities without ADR (Deferred; STATE B risk)
- Yoast as required core commitment (optional adapter at TSC.6 boundary only)
- Biopentra-specific adapters / BCC product concept
- Second Store / second policy engine
- Public meta registration API before internal proof
