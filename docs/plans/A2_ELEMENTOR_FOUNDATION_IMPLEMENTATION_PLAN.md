# A.2 — Elementor Foundation — Implementation Plan

**Status:** Planning complete — implementation **not started**  
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — milestone **A.2** (Architecture / Foundation)  
**Baseline:** `main` @ `fbf8a553813172794bc37c3a84210de6e865e2cd` (A.R1 complete; ADR-0016 Accepted; tag `ar1-elementor-identity-research-complete`)  
**Planning branch:** `feature/a2-elementor-foundation-plan`  
**Implementation branch (later):** `feature/a2-elementor-foundation` (create only after this plan is reviewed/frozen and coding is authorized)  
**ADR:** [0016-elementor-identity-and-ownership.md](../adr/0016-elementor-identity-and-ownership.md) — **Accepted** (immutable for A.2)  
**Evidence:** [AR1_ELEMENTOR_IDENTITY_RESEARCH_LOG.md](AR1_ELEMENTOR_IDENTITY_RESEARCH_LOG.md) (**CONDITIONAL GO**); [DENY_LIST.md](../../research/ar1-elementor-identity/DENY_LIST.md)  
**Validation log (reserved):** `docs/plans/A2_ELEMENTOR_FOUNDATION_VALIDATION_LOG.md` — create when A20 begins  

**This document authorizes planning only.** It does **not** authorize Elementor production coding until separately approved after plan freeze.

---

## 1. Purpose

Prove the Accepted ADR-0016 architecture on the **smallest safe production surface**: three Elementor widget controls, document-owned only, with deterministic Hybrid D identities, Store overlays, Elementor-supported request-time rendering, language-safe caching, and source fallback for everything else.

A.2 is **Elementor Foundation**, not broad Elementor translation.

---

## 2. Preconditions (verified at plan authoring)

| Precondition | Status |
|---|---|
| A.R1 research complete / merged | **Pass** |
| ADR-0016 **Accepted** | **Pass** |
| CONDITIONAL GO accepted | **Pass** |
| No Elementor production implementation in `src/` | **Pass** |
| Baseline `main` @ `fbf8a5538…` | **Pass** |

If any precondition regresses before coding starts: **STOP**.

---

## 3. Goals

1. Extract only the A.2 allowlist from Elementor documents without mutating `_elementor_data`.  
2. Persist overlays in the existing Store under a Hybrid-D-compatible key family distinct from Gutenberg `b:<uuid>:<field>`.  
3. Apply overlays at an Elementor-supported frontend integration point (no HTML scraping).  
4. Enforce language-aware cache isolation before activation.  
5. Leave unsupported widgets/controls as source.  
6. Preserve Gutenberg, Store, TM, Glossary, Review, Jobs, PluginGuard, and UUID contracts.  
7. Fail safely when Elementor is absent or unsupported.  
8. Deliver Tier 0 + targeted Elementor browser acceptance with **rendered false positives = 0**.

---

## 4. Frozen implementation scope

### In scope (allowlist — exclusive)

| Widget | Control | Notes |
|---|---|---|
| `heading` | `title` | Desktop/base `title` only in A.2 |
| `text-editor` | `editor` | Rich text; sanitize on write/display per platform rules |
| `button` | `text` | Plain text control |

### Ownership (A.2-only)

- **Document-owned** Elementor content on ordinary posts/pages (and equivalent Elementor edit-mode documents treated as the owning post).  
- Owner identifier = WordPress post ID of the Elementor document.

### Explicitly out of A.2

Responsive title variants (`title_mobile`, etc.), nested/repeater IDs, Theme Builder, library/shared templates, template/global widgets, ambiguous ownership, accordions/toggles/repeaters, image alt/caption, loop-grid, dynamic tags, HTML/shortcode, Woo/Fluent/custom chrome, third-party adapters, editor UI translation, AIML render-cache enablement, Candidate B, HTML scrape, fuzzy rematch, Store schema bumps, `_elementor_data` mutation.

Deferred homes: **A.3+** (widget coverage), **A.4** (nested Gutenberg — unrelated), **A.6** (WP chrome), **A.7** (Woo). Do not silently expand A.2.

---

## 5. Frozen architecture (ADR-0016 — do not reopen)

- Elementor remains canonical owner of Elementor data.  
- AIML owns overlays only.  
- Hybrid D conceptual composition is mandatory.  
- Owner precedence / deny-list / adapter graduation remain authority; A.2 uses **allowlist + deny-list**, document-owned only.  
- Candidate B rejected.  
- No HTML scraping; no fuzzy rematch.  
- Store / TM / Glossary / Review / Jobs / renderer platform ownership unchanged.  
- Cache must be language-aware.  
- Stability ≠ support ≠ ownership.

---

## 6. Additive production identity grammar (A.2 contract)

ADR-0016 freezes composition, not serialization. A.2 defines this **additive** Store `segment_key` grammar, consistent with Hybrid D and disjoint from Gutenberg:

```text
e:d:<owner_post_id>:<element_id>:<control_key>
```

| Token | Meaning |
|---|---|
| `e` | Elementor identity family (≠ `b` Gutenberg) |
| `d` | Owner scope = **document-owned** (only scope shipped in A.2) |
| `owner_post_id` | Decimal WordPress post ID |
| `element_id` | Native Elementor element `id` (required; non-empty) |
| `control_key` | Allowlisted control (`title` \| `editor` \| `text`) |

**Rules:**

- No nested-item segment in A.2 (not required for the three controls).  
- No responsive suffix in A.2 (base controls only; do not invent variants).  
- `source_hash` = freshness only (ADR-0007); never part of the key.  
- Source/translated text are Store payload, not identity.  
- Missing element ID, unknown widget/control, or non-document ownership → **do not extract** (source).  
- Keys must not collide with `b:…` (different prefix). Intra-document uniqueness = element_id + control_key under owner.

Future scopes (e.g. definition-owned) would use a different scope token under a later milestone/ADR amendment — **not** A.2.

---

## 7. Extraction architecture

### Detection

Deterministic Elementor-managed document detection (aligned with existing `Extractor` / gate knowledge of `_elementor_data` / `_elementor_edit_mode`), without enabling Gutenberg body translation for Elementor posts.

### Boundary

Dedicated Elementor extraction path (do **not** fold into Gutenberg block extractors):

1. Confirm Elementor edit-mode / data present.  
2. Decode `_elementor_data` read-only.  
3. Walk element tree.  
4. For each widget in allowlist, read only approved controls.  
5. Emit translation units with Hybrid D fields + `segment_key` + source text + source hash.  
6. Skip deny-listed / unknown / malformed nodes with source fallback (no throw to visitors).  
7. Never write `_elementor_data`. Never scrape rendered HTML. Never fuzzy-match.

### Gutenberg isolation

Elementor extraction must not alter Gutenberg UUID registration, block save pipeline, or `b:` Store rows. Existing `elementor_body` skip for **block** pipeline remains correct; A.2 adds a **parallel** Elementor path, not a hijack of block rendering.

---

## 8. Translation-unit → Store mapping

Map each unit into existing Store APIs (`source_type` / `source_id` / `language_id` / `segment_key` / hashes / text_format) without a second Store.

| Field | Mapping |
|---|---|
| Owner scope | Encoded in key (`d`) + internal unit metadata |
| Owner ID | `source_id` = post ID; also in key |
| Element ID | In key |
| Control | In key |
| Source text | Store source payload |
| Source hash | Freshness / stale |
| Target language | Existing language_id |
| Kind | Prefer existing segment kind patterns; if a dedicated kind constant is needed, it must be **additive** and schema-compatible **without** a schema version bump — if impossible, **STOP** and escalate |

**No** Elementor-specific TM/Review/Glossary/Jobs tables.

Stale: when source text changes, `source_hash` mismatch marks stale per existing Store semantics; identity retained when element_id+control unchanged.

---

## 9. Rendering strategy

### Technical validation gate (before production wiring)

Candidate hook from A.R1: `elementor/frontend/builder_content_data` (listeners observed). **Do not freeze blindly.**

A24 must prove on the live stack:

1. Hook fires on actual frontend render for document-owned pages.  
2. Data is available before final HTML generation.  
3. Canonical Elementor data / postmeta remain unchanged after request.  
4. Overlays are language-scoped.  
5. Unsupported controls remain source.  
6. Admin/editor rendering unmodified.  
7. Store lookups can be batched per document.

If the candidate fails, evaluate other Elementor-supported data/settings filters researched in A.R1. If only HTML post-render replacement works: **STOP**.

### Rejected

Post-render HTML string replacement; DOM scraping; final-HTML regex replace.

### Behavior

Request-time: for current visitor language, replace allowlisted setting values in the in-memory Elementor data tree (or equivalent supported injection) with Store translations when fresh/renderable; else source.

---

## 10. Language and cache safety (hard gate)

A25 must validate before production activation:

- Elementor internal render/data/CSS caches  
- WP object cache interactions  
- Full-page cache behavior where observable  
- Repeated EN/SV (or configured pair) requests  
- Logged-in vs anonymous  
- Cold vs warm  

**No translated output may leak between languages.**

If isolation cannot be demonstrated: **STOP** before activation.

Do **not** enable the existing AIML render cache as part of A.2.

---

## 11. Allowlist + deny-list

- Translation is **allowlist-driven** (three pairs only).  
- A.R1 [DENY_LIST.md](../../research/ar1-elementor-identity/DENY_LIST.md) remains authoritative for known unsupported classes.  
- No recursive “string-looking” setting extraction.  
- Unsupported → source.

---

## 12. Component boundaries (illustrative names)

Prefer focused classes under a dedicated Elementor namespace (e.g. `src/Elementor/` or repository-conventional equivalent). Do not mix into Gutenberg block classes.

| Component | Responsibility |
|---|---|
| `ElementorDocumentDetector` | Deterministic Elementor document detection |
| `ElementorControlRegistry` | Allowlist + deny awareness for A.2 |
| `ElementorIdentity` | Hybrid D → `e:d:…` key build/parse |
| `ElementorExtractor` | Read-only walk + units |
| `ElementorTranslationUnit` | Unit DTO |
| `ElementorOverlayResolver` | Batched Store lookup + stale/fallback |
| `ElementorFrontendBridge` | Hook registration + request-time overlay |
| `ElementorDiagnostics` | Bounded counters/events |
| `ElementorCompatibility` | Version policy / safe fail |

No Elementor logic inside TM/Review/Glossary/Jobs services.

---

## 13. Plugin registration

- Register Elementor bridge only when Elementor is available.  
- Core AIML (Gutenberg, Store, Workspace) must run if Elementor is disabled.  
- Version mismatch: warn/diagnose; fail closed for Elementor overlay (source), do not fatal.  
- Preserve Gutenberg behavior when Elementor absent.

---

## 14. Version support policy

A.R1 verified **Elementor 4.2.1** / **Elementor Pro 4.2.1** only.

| Policy | Rule |
|---|---|
| Production-supported baseline | Current verified 4.2.x family on Biopentra (confirm at A20) |
| Unknown / untested versions | Compatibility warning + Elementor overlays disabled (source) or fail-safe |
| Claims | No universal compatibility claim |
| Widening | Requires explicit verification evidence |

---

## 15. Migration / existing content

- No data migration; no source rewrite.  
- Existing Elementor documents become readable when A.2 extraction runs.  
- Existing Store rows untouched unless they use the new `e:` key family.  
- Optional explicit reconciliation/backfill job later — not required to mutate Elementor meta.

---

## 16. Workspace / Review / TM / Glossary / Jobs

Integrate through existing contracts only:

- Workspace displays/edits Elementor units via existing ViewModels with minimal additive metadata (identity family / widget/control labels) if needed.  
- Review, TM, Glossary, Jobs, suggestion path **unchanged** in ownership and policy.  
- Machine translations still follow existing Review/TM rules.  

If redesign is required: **STOP**.

---

## 17. Security

- Existing AIML capabilities only.  
- Sanitized handling of Elementor strings; Text Editor rich-text safety (kses/platform rules).  
- No executable HTML injection via overlays.  
- No translation of denied HTML/shortcode/dynamic controls.  
- No wp-admin / Elementor editor UI translation.  
- Diagnostics: no source/target body logging; no secrets.

---

## 18. Performance validation

A.R1: tree walk/map lookup cheap vs Elementor render (~606 ms cold / ~12 ms warm for 40 headings).

A.2 must measure (no invented budgets before measurement):

- Extraction overhead  
- Store lookup count / batching  
- Cold vs warm render  
- Translated vs source  
- Supported vs unsupported documents  
- Representative widget counts  

Compare to A.R1 baseline; record in validation log.

---

## 19. Testing strategy

### Unit

Identity build/parse; owner scope `d` only; allowlist; source hashes; deny skip; malformed JSON/tree.

### Integration

Detection; extraction; Store upsert/get; stale; Gutenberg regression; Elementor absent; PluginGuard.

### Acceptance / browser (targeted — not F9 35-suite)

Heading/Text Editor/Button translated; unsupported remains source; EN/SV isolation; cache warm/cold isolation; page duplicate does not share translations; edit retains identity when source unchanged; source change → stale; Elementor disabled → AIML healthy; editor/wp-admin unaffected; **rendered FP = 0**.

---

## 20. Work packages (A20–A28)

Ordering is fixed. One package at a time on `feature/a2-elementor-foundation` after coding authorization.

### A20 — Baseline + ADR contract verification

| Field | Content |
|---|---|
| **Objective** | Confirm ADR-0016 Accepted, baseline versions, no premature Elementor code, open validation log |
| **Scope** | Docs/harness scaffolding only; version capture |
| **Dependencies** | Plan freeze; coding authorization |
| **Likely files** | `docs/plans/A2_ELEMENTOR_FOUNDATION_VALIDATION_LOG.md`; optional `acceptance/a2-elementor/` README |
| **Tests** | N/A (verification) |
| **Validation** | Elementor/Pro versions recorded; ADR Accepted; `src/` clean of Elementor foundation |
| **Rollback** | Delete validation log scaffolding |
| **Stop** | ADR not Accepted; production Elementor code already present unexpectedly |
| **Commit boundary** | `docs(elementor): open A.2 validation log` |

### A21 — Detection + first-surface control registry

| Field | Content |
|---|---|
| **Objective** | Detector + allowlist registry for three controls |
| **Scope** | Detection + registry; no Store writes yet |
| **Dependencies** | A20 |
| **Likely files** | `src/Elementor/ElementorDocumentDetector.php`, `ElementorControlRegistry.php`, registration guard |
| **Tests** | Unit: allowlist/deny; detector true/false |
| **Validation** | Elementor absent → detector false; Gutenberg unaffected |
| **Rollback** | Remove registration |
| **Stop** | Detection requires HTML scrape |
| **Commit boundary** | `feat(elementor): add A.2 document detector and control allowlist` |

### A22 — Hybrid D identity + extraction

| Field | Content |
|---|---|
| **Objective** | Key grammar + read-only extractor emitting units |
| **Scope** | Identity + extraction; no render |
| **Dependencies** | A21 |
| **Likely files** | `ElementorIdentity.php`, `ElementorExtractor.php`, `ElementorTranslationUnit.php` |
| **Tests** | Unit identity; extraction fixtures (sanitized); no meta writes |
| **Validation** | Only three controls emitted; malformed → skip |
| **Rollback** | Disable extractor registration |
| **Stop** | Value-level identity impossible for allowlist; schema change demanded |
| **Commit boundary** | `feat(elementor): extract A.2 Hybrid D translation units` |

### A23 — Store integration + stale handling

| Field | Content |
|---|---|
| **Objective** | Persist/retrieve `e:d:…` via existing Store |
| **Scope** | Store I/O only; no Elementor render |
| **Dependencies** | A22 |
| **Likely files** | Overlay resolver; save/extract pipeline hooks as designed without block-pipeline conflict |
| **Tests** | Integration Store roundtrip; `b:` unaffected; stale on source change |
| **Validation** | No schema bump; collision safety vs Gutenberg |
| **Rollback** | Feature flag / unregister Elementor save path |
| **Stop** | Unavoidable schema migration |
| **Commit boundary** | `feat(elementor): persist A.2 overlays in Store` |

### A24 — Frontend overlay bridge + fallback

| Field | Content |
|---|---|
| **Objective** | Request-time overlay via validated Elementor hook |
| **Scope** | Frontend bridge; admin/editor excluded |
| **Dependencies** | A23; hook validation evidence |
| **Likely files** | `ElementorFrontendBridge.php`, `ElementorOverlayResolver.php` |
| **Tests** | Integration render with/without overlays; unsupported source |
| **Validation** | Hook validation checklist PASS; no `_elementor_data` mutation; FP=0 on fixtures |
| **Rollback** | Unregister bridge |
| **Stop** | Only HTML scrape works; admin mutated |
| **Commit boundary** | `feat(elementor): apply A.2 frontend overlays` |

### A25 — Language / cache isolation

| Field | Content |
|---|---|
| **Objective** | Prove no cross-language leakage |
| **Scope** | Cache/language tests; fix isolation if needed without AIML render-cache enable |
| **Dependencies** | A24 |
| **Likely files** | Bridge/cache helpers; acceptance probes |
| **Tests** | Browser/integration EN/SV warm/cold; anon/logged-in |
| **Validation** | Isolation PASS recorded |
| **Rollback** | Keep overlays disabled if fail |
| **Stop** | Isolation cannot be demonstrated |
| **Commit boundary** | `test(elementor): prove A.2 language cache isolation` |

### A26 — Workspace / diagnostics

| Field | Content |
|---|---|
| **Objective** | Minimal Workspace surfacing + bounded diagnostics |
| **Scope** | Additive metadata/diagnostics only |
| **Dependencies** | A23+ |
| **Likely files** | ViewModel adapters; `ElementorDiagnostics.php` |
| **Tests** | Unit diagnostics privacy; Workspace smoke |
| **Validation** | Review/TM/Glossary/Jobs unchanged; no body logging |
| **Rollback** | Hide Elementor metadata |
| **Stop** | Requires Review/TM redesign |
| **Commit boundary** | `feat(elementor): add A.2 diagnostics and Workspace surfacing` |

### A27 — Compatibility / performance hardening

| Field | Content |
|---|---|
| **Objective** | Version policy enforcement + performance evidence |
| **Scope** | Compatibility gate; metrics vs A.R1 |
| **Dependencies** | A24–A26 |
| **Likely files** | `ElementorCompatibility.php`; acceptance perf notes |
| **Tests** | Unsupported version fail-safe; perf harness |
| **Validation** | Policy documented; measurements logged |
| **Rollback** | Disable Elementor overlays by default on unknown versions |
| **Stop** | Cannot fail safely on version mismatch |
| **Commit boundary** | `feat(elementor): harden A.2 compatibility and performance evidence` |

### A28 — Tier 0 + targeted browser + closure

| Field | Content |
|---|---|
| **Objective** | Full AC sign-off; validation log PASS; release readiness without claiming broad Elementor support |
| **Scope** | Tests + docs closure |
| **Dependencies** | A20–A27 |
| **Likely files** | `A2_ELEMENTOR_FOUNDATION_VALIDATION_LOG.md`; `acceptance/a2-elementor/*` |
| **Tests** | Tier 0; targeted browser suite |
| **Validation** | All ACs; FP=0; roadmap status update |
| **Rollback** | Do not merge if AC fail |
| **Stop** | Any hard-gate AC fail |
| **Commit boundary** | `docs(elementor): close A.2 Elementor Foundation validation` |

---

## 21. Acceptance criteria

1. ADR-0016 contracts preserved (Hybrid D, overlays-only, deny-list, no Candidate B).  
2. Production grammar `e:d:<owner>:<element>:<control>` matches Hybrid D document-owned A.2 surface.  
3. A.2 ownership is document-owned only.  
4. `heading` / `title` extracts and translates when eligible.  
5. `text-editor` / `editor` extracts and translates when eligible.  
6. `button` / `text` extracts and translates when eligible.  
7. Unsupported widgets remain source.  
8. Deny-list / non-allowlist controls remain source.  
9. No HTML scraping.  
10. No fuzzy rematch.  
11. No `_elementor_data` mutation.  
12. No Store schema version bump.  
13. Store reused as sole overlay persistence.  
14. Gutenberg `b:` keys unaffected.  
15. Cross-document / duplicate-page overlays do not silently share (owner in key).  
16. Identity collision safety within document for distinct element+control pairs.  
17. Stale/source_hash behavior matches platform freshness rules.  
18. Language isolation proven (no cross-language leak).  
19. Cache warm/cold isolation proven.  
20. Editor / wp-admin Elementor UI not translated.  
21. Elementor disabled → AIML core healthy; Gutenberg intact.  
22. Version compatibility policy enforced (fail safe).  
23. Workspace can surface Elementor units without redesign.  
24. Review ownership/behavior unchanged.  
25. TM ownership/behavior unchanged.  
26. Glossary ownership/behavior unchanged.  
27. Jobs ownership/behavior unchanged.  
28. Suggestion architecture unchanged.  
29. Diagnostics privacy (no body/secret logging).  
30. Performance evidence recorded vs A.R1 baseline.  
31. PluginGuard / capability model preserved.  
32. Unit + integration coverage for identity/extract/Store/bridge.  
33. PHPCS / Tier 0 green for touched code.  
34. Targeted browser acceptance PASS.  
35. Rendered false positives = 0.  
36. AIML render cache not enabled by A.2.  
37. Release notes claim Foundation/first-surface only — not broad Elementor support.

**Acceptance-criteria count: 37.**

---

## 22. Stop conditions

Stop planning revisions or implementation if:

- Language cache isolation cannot be designed/proven.  
- Allowlisted controls lack value-level deterministic identity.  
- Production hook requires HTML scraping.  
- Elementor source must be mutated.  
- Candidate B becomes necessary.  
- Store schema must change unexpectedly.  
- Theme Builder/shared ownership becomes necessary for the first surface.  
- Gutenberg contracts would change.  
- Review/TM/Glossary/Jobs need redesign.  
- Version compatibility cannot fail safely.

---

## 23. Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Elementor version drift | High | Compatibility policy; revalidate |
| Cache language leakage | High | A25 hard gate |
| Hook choice wrong | High | Pre-wiring validation |
| Scope creep beyond three controls | High | Frozen allowlist |
| Theme Builder pressure | Medium | Explicit A.2 exclusion |
| Rich-text safety (`editor`) | Medium | Platform kses/sanitization |
| Duplicate-page ID reuse | High | Owner ID in key (proven need) |
| Perf regression on large docs | Medium | Measure; batch Store lookups |
| Accidental Gutenberg coupling | Medium | Separate component namespace |

---

## 24. Out of scope (Program A later)

| Deferred | Home |
|---|---|
| Broader Elementor widgets / repeaters / images | **A.3** |
| Nested Gutenberg identity | **A.4** (A.R2 first) |
| Additional surfaces as roadmap defines | **A.5+** as applicable |
| WordPress visitor chrome | **A.6** |
| WooCommerce visitor coverage | **A.7** |
| Theme Builder / shared definitions | Later Coverage after ownership evidence |
| Adapter SDK / third-party bridges | **A.1** / **E.*** |

---

## 25. Exact next step

1. Review and freeze this plan.  
2. Authorize coding.  
3. Create `feature/a2-elementor-foundation` from updated `main`.  
4. Execute **A20** (validation log + baseline verification).  

Do not start A21+ until A20 PASS.

---

## Document control

| Item | Value |
|---|---|
| Canonical plan | `docs/plans/A2_ELEMENTOR_FOUNDATION_IMPLEMENTATION_PLAN.md` |
| Supersedes | None |
| Related | ADR-0016, ADR-0001, ADR-0007, ADR-0013; A.R1 research log; DENY_LIST |
