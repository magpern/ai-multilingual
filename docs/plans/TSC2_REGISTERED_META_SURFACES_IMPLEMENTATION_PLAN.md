# TSC.2 — Registered Meta Translation Surfaces Implementation Plan

**Status:** **Architecture Frozen** on `main` — production implementation **NOT STARTED**
**Milestone:** TSC.2 Registered Meta Translation Surfaces
**Parent:** [TSC_PARENT_IMPLEMENTATION_PLAN.md](TSC_PARENT_IMPLEMENTATION_PLAN.md) (Architecture Frozen on `main`) §16 / TS18
**External review:** **FREEZE** (ten amendments incorporated) · **STATE A** · **TARGET 7**
**Independent planning review:** **PASS** — [TSC2_REGISTERED_META_SURFACES_PLANNING_VALIDATION_LOG.md](TSC2_REGISTERED_META_SURFACES_PLANNING_VALIDATION_LOG.md)
**ADR:** **None** (direct application of parent §16 + ADR-0001/0005/0007 + TSC.0 Surface spine; ADR-0017 Integration `p:` retained for Rank Math)
**Freeze merge:** `51be1f0aa771261c3d7e44d2ea891da7bb9ffcd1`
**Depends on:** AI Multilingual **v1.3.0**; TIQ Complete; OTL Complete; TSC Parent Frozen; **TSC.0 COMPLETE**; **TSC.1 COMPLETE**; `Migrator::TARGET` **7**
**Related:** [TSC0_INTERNAL_SURFACE_CAPABILITY_FOUNDATION_IMPLEMENTATION_PLAN.md](TSC0_INTERNAL_SURFACE_CAPABILITY_FOUNDATION_IMPLEMENTATION_PLAN.md); [TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md](TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md); ADR-0021; ADR-0017

**This document is the authoritative implementation specification for TSC.2.** Work packages TSC2.0–TSC2.7 are **NOT STARTED**.

**Production implementation status:** **NOT STARTED.**
**TSC.3–TSC.6 implementation status:** **NOT STARTED.**

**Exact next step:** Begin authorized **TSC.2 implementation** from frozen main via a dedicated feature branch only when an implementation task is opened. Execute TSC2.0→TSC2.7 per this plan. Do **not** start TSC.3+. Do **not** bump version/TARGET, tag, release, or deploy as part of planning closure.

---

## 1. Baseline

| Field | Value |
|---|---|
| Planning baseline main HEAD | `2d51bd2def983bf6a8078ea1ada8fbea7ef3f0ba` |
| `main` == `origin/main` | Yes (at branch creation) |
| Version | **1.3.0** |
| Tag `v1.3.0` | Unchanged |
| `Migrator::TARGET` | **7** |
| Schema | **STATE A** — no migration |
| TSC.0 | COMPLETE |
| TSC.1 | COMPLETE |
| TSC.2–TSC.6 implementation | Not started |
| External plan source | Amended Cursor planning freeze candidate · verdict **TSC.2 PLAN REVIEW: FREEZE** |

### Repository spine (pre-TSC.2)

| Concern | Reality |
|---|---|
| SurfaceCapability / SurfaceRegistry | Post + term only; no public registration API |
| MetaSurface / `SOURCE_META` / `register_translatable_meta` | **Absent** |
| Production WP meta extract/overlay | Rank Math SEO only (`RankMathIntegration`) |
| Meta stale observers | Hardcoded `PostSurfaceAdapter::RANK_MATH_SEO_META_KEYS` (+ term mirror) |
| `Store::sync_source` | Missing extract key → `ignored` + `error_code=orphaned`; inactive Integration extract already false-orphans Rank Math rows |
| `field_key` convention | Family buckets (`post_*`, `_plugin`, `_elementor`, `post_content`); unique identity in `segment_key` VARCHAR(191); `field_key` VARCHAR(64) |
| Elementor / Gutenberg | Structured document paths → TSC.5 / TSC.4 |
| Woo economic meta | Guarded must-not-translate |
| Public meta API | Deferred TSC.6 |

### External amendments incorporated (FREEZE)

1. RegisteredMetaRegistry subordinate to SurfaceCapability (field catalog only)
2. No generic production `filter:{hook}` overlay engine
3. Hardened native `m:` identity / collision / mode-switch rules
4. No artificial production ceiling of 32 definitions (O(R) only)
5. CASE A/B/C lifecycle; `retain_segment_keys` on `sync_source`
6. Distinct `extract_store_capable` / `provider_allowed` / `overlay_capable`
7. Rank Math definition module as single source of truth for six SEO keys
8. Explicit TSC.1 term Jobs regression contract
9. `field_key=_meta`; identity on `segment_key`
10. Honest product-value claims (no generic custom-field / ACF)

---

## 2. Objective

Allow **explicitly admitted, code-owned** WordPress metadata fields containing visitor-facing text to participate safely in the existing translation system — **without** turning arbitrary postmeta/termmeta into translation surfaces, and **without** a second translation system.

Deliver:

- Internal `RegisteredMetaDefinition` + `RegisteredMetaRegistry` as a **narrow field-definition catalog**
- Native scalar meta identity `m:{namespace}:{meta_key}` under owning `post` / `term` sources
- Rank Math six-key catalog ownership + invalidation single source of truth (preserve `p:` Integration path)
- CASE A/B/C lifecycle via optional `Store::sync_source(…, $retain_segment_keys)`
- Distinct extract / provider / overlay facts; TI.6 segment-level provider skip
- Term registered meta under TSC.1 identity + term Jobs regression suite
- Reference/test adapters only for native `m:` frontend proof
- PluginGuard / architecture tests

Does **not** deliver: public registration API, generic custom-field UI, ACF, Woo economic meta, Gutenberg/Elementor expansion, `SOURCE_META`, schema bump, or generic metadata interception.

---

## 3. Ownership boundaries

```text
SurfaceCapability / Admitted* / TI.6 / TI.7 / OTL
  → source admission, existence, edit auth, publicness, Jobs policy, publication, OTL mutate

RegisteredMetaRegistry (field catalog)
  → exact key registration, identity, scalar semantics, activation,
     extract_store_capable / provider_allowed / overlay_capable facts,
     frontend resolver ownership, labels

RankMathIntegration
  → p: identities, literal/template exclusion, social inheritance,
     frontend filters, sitemap

Store
  → segment persistence, sync/orphan, retain_keys, hashes, overlay eligibility
```

**Freeze rule:** A registered meta definition is usable for extract / invalidate / overlay / provider **only when** the owning source is already admitted by its authoritative `SurfaceCapability` (and Admitted\* helpers). Subtype hints refine emission within the admitted set; they do **not** create a parallel admission authority.

---

## 4. Definition of registered meta

A registered field is a **code-owned field-definition** with:

- normalized code-owned `namespace`
- `source_type` ∈ {`post`, `term`}
- exact `meta_key` (no wildcards)
- subtype hints within Surface-admitted set
- `value_type` = `scalar_string` only
- `segment_key_mode` = `native_m` \| `external_p` (immutable after register)
- activation predicate
- `extract_store_capable`, `provider_allowed` (default **false**), `overlay_capable`
- `overlay_resolver_ownership` = `integration:{id}` \| `reference_adapter:{id}` \| `none`
- `label`; source-hash material = extracted visitor text only

**Not** registered meta: any `get_*_meta` key, `show_in_rest`, non-`_` keys, text heuristics, admin wildcards, serialized objects, options, theme_mods, usermeta.

---

## 5. Source identity model (STATE A)

```text
post meta → source_type=post, source_id=post_id, source_subtype=post_type
term meta → source_type=term, source_id=term_id, source_subtype=taxonomy
NO source_type=meta / SOURCE_META
```

Uniqueness remains `(source_type, source_id, segment_hash, language_id)`.
`source_type` is **not** embedded in native segment keys.

---

## 6. Internal registration architecture

| Piece | Role |
|---|---|
| `RegisteredMetaDefinition` | Declarative value object |
| `RegisteredMetaRegistry` | Code-owned catalog; O(1) by `(source_type, meta_key)` and segment identity |
| `RegisteredMetaReader` | Keyed `get_post_meta` / `get_term_meta` only |
| `RegisteredMetaExtractor` | Emits **native_m** units only for Surface-admitted owners |
| `RegisteredMetaInvalidation` | Meta hooks → coordinator dirty iff key registered **and** owner admitted |
| Rank Math definition module | Sole authority for six SEO text meta keys |
| Reference/test adapters | Native `m:` overlay proof only — **not** a production generic binder |

**No** production facility that binds arbitrary WP filters from a definition string.

Wiring: boot beside SurfaceRegistry; adapters query catalog for invalidation; Extractor/TermExtractor merge native `m:` units; Rank Math Integration consumes Reader and retains `PluginIdentity` / overlays.

---

## 7. Native `m:` identity contract

**Form:** `m:{namespace}:{meta_key}`

**Frozen rules:**

1. `namespace` normalized (lowercase-safe ASCII token class), code-owned
2. `meta_key` exact; no wildcards
3. `strlen(segment_key) ≤ 191`; reject at bootstrap otherwise
4. No object / site / database IDs in identity
5. Duplicate segment identity within same source family rejected at register
6. Collisions fail deterministically at bootstrap / tests
7. Changing namespace or meta_key = identity migration (**unsupported** in TSC.2)
8. No silent key renames/migrations
9. `native_m` ↔ `external_p` cannot switch silently (reject)
10. `source_type` not in key

External Rank Math keys remain `p:rankmath:…` via PluginIdentity — **no `m:` migration**.

---

## 8. `field_key` contract

| Unit | `field_key` | Identity carrier |
|---|---|---|
| Native registered meta | **`_meta`** | `segment_key` = `m:{namespace}:{meta_key}` |
| Rank Math (external) | `_plugin` (unchanged) | `p:rankmath:…` |

Rationale: matches established family buckets (`_plugin`, `_elementor`); `field_key` VARCHAR(64) must not hold full `m:…` for long meta keys; OTL labels come from definition `label`; FieldSemanticMapper adds an `m:` branch on `segment_key` (default `GENERIC` unless specialized).

---

## 9. Value types

**SUPPORTED:** scalar strings (trim-empty → omit from extract).

**UNSUPPORTED:** serialized PHP, opaque JSON, binary, credentials, IDs, bools, non-textual numbers, arbitrary arrays, Elementor/Gutenberg-like path walks.

Bounded structured paths → **Deferred** (not TSC.2).

---

## 10. Rank Math ownership contract

**Catalog / Rank Math definition module owns:**

- exact six admitted SEO text keys (`rank_math_title`, `rank_math_description`, `rank_math_facebook_title`, `rank_math_facebook_description`, `rank_math_twitter_title`, `rank_math_twitter_description`)
- activation, scalar type, provider/extract/overlay facts
- invalidation admission

**RankMathIntegration retains:**

- `p:` segment identities
- extraction semantics + literal/template exclusion (`is_literal_seo_field`)
- social inheritance (`rank_math_twitter_use_facebook` remains **not** registered — operational **D**)
- frontend filters + sitemap

**PostSurfaceAdapter / TermSurfaceAdapter:** query catalog for invalidation — **no independent six-key literal array as source of truth**. Any BC test constants **must derive** from the definition module. Drift-prevention architecture tests required.

Partial reuse only — no Rank Math rewrite.

---

## 11. Term-meta strategy

- Registered term meta under TSC.1 term identity and Surface-admitted taxonomies
- Rank Math term keys: catalog **invalidation**; extract stays host Integration + TSC.1 adoption
- Native `m:` term units merged into term extract
- **Never** emit Rank Math term SEO as both `m:` and `p:`

---

## 12. Woo / structured / deferred boundaries

| Surface | Disposition |
|---|---|
| Woo economic/operational meta (`_price`, `_stock`, `_sku`, …) | **Forbidden** — zero registrations |
| Woo product-local attributes | **TSC.3** |
| Gutenberg | **TSC.4** |
| Elementor | **TSC.5** |
| Public registration / frontend stabilization | **TSC.6** |
| Options / theme_mods / usermeta / ACF wildcards | **Unsupported** |
| Biopentra / site-specific keys | **Forbidden** |

---

## 13. Invalidation / stale

```text
meta hook
  → catalog has exact key?
  → owning Surface admits object?
  → coordinator.mark_dirty(owner)
shutdown → extract → sync_source(segments, retain_keys)
```

No provider call from meta hook; no all-meta scan; unrelated keys ignored.

---

## 14. CASE A/B/C lifecycle + `retain_segment_keys`

### CASE A — active definition, meta removed/empty

- Unit omitted from extract
- Normal orphan: `ignored` + `error_code=orphaned`
- Overlay suppressed; Jobs skip; translation text retained in Store

### CASE B — definition present but inactive (plugin/integration temporarily absent)

- Do **not** treat as field deletion
- No extract emit; no overlay; no provider
- **Existing Store rows retained and genuinely unchanged** by the orphaning phase
- Mechanism (STATE A — no durable registration table):

```text
retain_keys = catalog.segment_keys_for_inactive_definitions(source_type, source_id)
Store::sync_source($type, $id, $subtype, $segments, $retain_segment_keys = [])
```

Semantics:

- key ∉ segments **and** key ∈ retain_keys → **leave row genuinely untouched** by the orphaning phase (no `status`, `error_code`, `updated_at`, source-hash, or other column mutations from sync’s missing-key branch)
- key ∉ segments **and** key ∉ retain_keys → orphan (CASE A or CASE C)
- keys present in `$segments` continue through normal hash/stale sync (retain set does not suppress updates for actively extracted units)

**Computing `retain_keys` (no durable registration table):**

| Mode | Computation |
|---|---|
| `native_m` inactive | Deterministic `m:{namespace}:{meta_key}` (no object id in key) |
| `external_p` inactive (Rank Math) | Union of (1) deterministic `PluginIdentity` rebuilds for direct `post:{id}` / `term:{id}` field keys belonging to the inactive definition, and (2) existing Store `segment_key`s for this `(source_type, source_id)` that parse as the inactive Rank Math identity family (covers shop / `page_for_posts` **host-emitted** `p:rankmath:term:{term_id}:*` rows without re-deriving host mapping incorrectly) |

Inactive Rank Math definitions remain in the **code catalog** with `activation=false`. Retain computation must not invent new Store rows — it only protects existing ones from false orphaning.

### CASE C — definition permanently removed from code

- Intentional retirement (definition no longer registered)
- Not in catalog → not in retain_keys → next owner sync may intentionally orphan
- No sitewide sweep
- Permanent code deletion **is** the retirement signal (no durable retirement table)

### Minimal API change

Optional `$retain_segment_keys` on `Store::sync_source` (default `[]` = current behavior). Coordinator / SegmentAssembler compute retain set from catalog. **No schema / TARGET bump.**

If retain-keys proved insufficient and a durable registration table were required → **STOP / REDESIGN**. Planning audit: retain-keys suffice → **STATE A**.

---

## 15. Target persistence

**Store-only overlays.** TSC.2 must not write translated values into postmeta/termmeta.

---

## 16. Frontend resolution

**Allowed:**

- **A.** Integration-owned production overlays (Rank Math existing filters)
- **B.** Explicit code-owned **reference/test adapters** for native `m:` lifecycle proof (fixtures; not a production generic binder)

**Forbidden:**

- Generic production helper reading `filter:{hook}` from definitions
- Global `get_post_meta` / `get_term_meta` translation filters
- Public/admin configurable overlay binding

General/public frontend binding → **TSC.6**. Admin/REST/cron/Woo business paths remain untranslated canonical reads.

---

## 17. OTL / concurrency / TI.7

- Registered meta segments appear under owning post/term in existing OTL
- Labels from definition `label`; no second UI; no list N+1 meta enrichment
- Reuse Store concurrency hashes; no separate meta concurrency model
- TI.7 remains publication authority; registration ≠ auto-publish

---

## 18. Jobs + `provider_allowed`

| Fact | Meaning |
|---|---|
| `extract_store_capable` | May appear in extract / Store / OTL inspect / manual edit |
| `provider_allowed` | May be sent to AI provider (default **false**) |
| `overlay_capable` | May resolve on admitted visitor seam when published-eligible |

When `extract_store_capable=true` and `provider_allowed=false`:

- Manual / imported / inspect / review **supported**
- **No** AI provider generation for that segment
- TI.6 **consumes** the fact; TSC does **not** invent a second Jobs policy engine
- ItemProcessor / materialization must not provider-call that segment (skip path analogous to `allow_provider=false`) **without** dropping the whole source job
- Sibling segments remain eligible per their own facts

Inactive definitions never reach provider. Unregistered keys never extract as registered meta.

### Term Jobs regression contract (first-class)

TSC2.3 extends term Jobs materialization to full term extraction with **explicit regressions**:

1. TSC.1 native `name` / `description` behavior unchanged
2. Native registered `m:` meta appear as **additional** term units
3. No duplicate segment enumeration
4. Rank Math term `p:` units remain on integration/adoption path only
5. Rank Math term SEO **never** emitted as both `m:` and `p:`
6. Jobs source snapshots match Store canonical source text
7. Stale / conflict / retry semantics unchanged
8. TSC.1 adoption authority unchanged

Dedicated RM / AC / integration tests — not incidental.

---

## 19. Provider security / PluginGuard

Registration is the hard allowlist boundary. **No** heuristic secret detection as primary boundary.

Must prove:

- unregistered keys never extract as registered meta
- `provider_allowed` defaults false
- inactive definitions never reach provider
- `provider_allowed=false` segments never reach provider
- Woo economic keys cannot be admitted by generic registration modules
- no wildcards; no all-meta read; no public admin/API registration
- Rank Math `%templates%` still excluded by literal gate before unit emit
- no `SOURCE_META`; no Biopentra keys; no generic filter-hook overlay engine
- Rank Math key drift: adapter ≡ catalog
- `m:` collision / rename / mode-switch bans
- retain-keys path covered for inactive definitions
- no schema/TARGET change

---

## 20. Performance

- Cost **O(R)** for R explicitly registered fields on the relevant source
- Each field: keyed/bounded metadata access
- No all-meta fetch; no list N+1; no frontend global interception
- Registry lookup O(1); invalidation O(1) per event
- **No artificial production ceiling of 32** — do not reject the 33rd legitimate definition
- ~32 may be used only as characterization test workload size

---

## 21. Product-value statement (honest)

**PRODUCTION**

- Rank Math’s six admitted meta keys, invalidation, and security facts become registry-backed with one source of truth
- Existing Rank Math extract / overlay / literal / social / sitemap behavior is preserved

**ARCHITECTURE PROOF**

- Native `m:` scalar post/term lifecycle proven via code-owned fixtures / reference adapters

**PLATFORM VALUE**

- Internal meta contract ready for later first-party TSC.3 work and TSC.6 public stabilization

**DO NOT CLAIM**

- Generic custom-field translation available to users
- General ACF / arbitrary customer meta support
- Admin-configurable meta translation

---

## 22. Schema / TARGET / ADR / public API

| Verdict | Freeze |
|---|---|
| Schema | **STATE A** — no migration; no durable registration table |
| TARGET | **7** unchanged |
| ADR | **None** |
| Public API | **None** → TSC.6 |
| SOURCE_META | **Forbidden** |

---

## 23. RM requirement matrix (RM1–RM34)

| ID | Requirement | Disposition |
|---|---|---|
| RM1 | Internal RegisteredMetaRegistry as **field catalog** | Supported |
| RM2 | Code-owned registration only | Supported |
| RM3 | Catalog subordinate to SurfaceCapability admission | Supported |
| RM4 | Meta segments under post/term only | Supported |
| RM5 | Native `m:{ns}:{key}` with hardened identity rules | Supported |
| RM6 | Scalar strings only | Supported |
| RM7 | Bounded structured paths | Deferred |
| RM8 | Registered-key invalidation via coordinator | Supported |
| RM9 | Frontend = Integration overlays **or** reference adapters only; **no** generic `filter:{hook}` engine | Supported |
| RM10 | OTL under owning source | Supported |
| RM11 | Jobs as owner segments | Supported |
| RM12 | TI.7 unchanged | Supported |
| RM13 | Store concurrency reused | Supported |
| RM14 | Distinct extract_store / provider_allowed / overlay_capable; provider deny-by-default | Supported |
| RM15 | Jobs must not provider-call `provider_allowed=false` segments | Supported |
| RM16 | Rank Math six keys single source of truth in catalog | Supported |
| RM17 | Adapters derive invalidation keys from catalog (no duplicate literals) | Supported |
| RM18 | Rank Math Integration retains p:/literal/social/overlay/sitemap | Supported |
| RM19 | Term meta catalog + Rank Math term invalidation | Supported |
| RM20 | Rank Math term extract stays adopt/host (no dual m:+p:) | Supported |
| RM21 | Term Jobs full extract with TSC.1 regression contract | Supported |
| RM22 | CASE A orphan on active empty/delete | Supported |
| RM23 | CASE B retain-keys on inactive definition | Supported |
| RM24 | CASE C permanent code removal → orphan on sync | Supported |
| RM25 | field_key=`_meta`; identity on segment_key | Supported |
| RM26 | Performance O(R); no production ceiling of 32 | Supported |
| RM27 | Woo economic meta excluded | Unsupported (forbidden) |
| RM28 | PluginGuard architecture suite | Supported |
| RM29 | No public registration API | Supported |
| RM30 | No schema / TARGET bump | Supported |
| RM31 | No Gutenberg/Elementor in TSC.2 | Unsupported (TSC.4/5) |
| RM32 | No options/theme_mods/usermeta/ACF wildcards | Unsupported |
| RM33 | Honest product-value claims only | Supported |
| RM34 | TSC.3+ / public SEO API | Deferred |

**Count: 34**

---

## 24. Work package ladder (TSC2.0–TSC2.7)

| WP | Scope |
|---|---|
| **TSC2.0** | Inventory characterization tests; disposition honesty |
| **TSC2.1** | Definition + Registry + Reader; Rank Math definition module; collision/identity rules; subordinate-to-Surface tests |
| **TSC2.2** | Post invalidation from catalog; native `m:` extract merge; Rank Math Reader; drift tests |
| **TSC2.3** | Term invalidation; native term `m:` extract; **term Jobs full extract + regression suite**; retain-keys on `sync_source` |
| **TSC2.4** | OTL labels; TI.7 smoke; concurrency; provider_allowed Jobs gating |
| **TSC2.5** | Rank Math overlay regression; **reference/test adapters only** for native `m:` proof (no generic filter engine) |
| **TSC2.6** | Security allowlist tests; PluginGuard; O(R) performance characterization (not ceiling) |
| **TSC2.7** | Docs/evidence; TS18 characterization; CASE A/B/C evidence |

**Count: 8 work packages (TSC2.0–TSC2.7)**

---

## 25. Acceptance criteria (AC1–AC32)

1. Native scalar **post** `m:` meta extracts under `source_type=post` with `field_key=_meta`.
2. Native scalar **term** `m:` meta extracts under `source_type=term`.
3. Unregistered meta never enters registered-meta extract/provider paths.
4. Private/operational meta never translates merely by existing.
5. No all-meta / wildcard scan helpers in production paths.
6. Native keys match hardened `m:` contract; Rank Math `p:` unchanged.
7. Registered meta edit → stale via coordinator+sync (Surface-admitted owners only).
8. Unrelated meta edit → no stale.
9. **CASE A:** active definition + meta delete/empty → orphan; overlay suppressed.
10. **CASE B:** inactive definition (plugin absent) → Store rows **retained** (not orphaned); no overlay/provider.
11. **CASE B reactivate:** extract resumes without requiring sitewide repair.
12. **CASE C:** definition removed from code → next sync orphans (intentional retirement).
13. Manual edit/review/publication via existing Store lifecycle for extract_store-capable units.
14. `provider_allowed=false` units: manual/inspect OK; **never** sent to provider; sibling segments unaffected.
15. Jobs snapshots/conflicts/retry unchanged for eligible segments; no meta job type.
16. Visitor overlay only via Integration or reference adapters; **no** generic filter engine; **no** global get_*_meta filter.
17. Admin/REST canonical meta reads unchanged.
18. No public registration API in `src/`.
19. No `SOURCE_META` / `source_type=meta`.
20. TARGET **7**; no migration / no durable registration table.
21. No TSC.3+ Woo remainder / Gutenberg / Elementor expansion.
22. Post title/content unchanged; term **name/description** unchanged (TSC.1 regression).
23. Rank Math SEO extract/overlay/stale still works for literal fields.
24. Rank Math term SEO adopt/host only — never dual-emitted as `m:`.
25. Term Jobs materialization includes native `m:` without duplicate enumeration; snapshots match Store.
26. Catalog is sole Rank Math key source; adapters/constants derive from it (drift test).
27. Definition unusable when Surface does not admit owner.
28. Registry rejects identity collisions, overlong keys, silent mode switches, wildcards; `provider_allowed` defaults false.
29. Performance: ≤ R keyed meta reads; 33rd definition not rejected solely by count.
30. Woo economic keys cannot be registered by production modules (guard).
31. PluginGuard suite green for amended bans.
32. Product claims limited to Rank Math catalog + architecture proof (no generic custom-field claim in docs).

**Count: 32**

---

## 26. Test strategy

**UNIT:** catalog subordinate checks; identity/collision; retain-key computation; provider_allowed default; activation; field_key=`_meta`; CASE B untouched-row assertion.

**INTEGRATION:** CASE A/B/C; Rank Math deactivate/reactivate; post/term lifecycle; term Jobs regression; provider gating with mixed siblings; OTL; TI.7; concurrency.

**SECURITY:** allowlist; inactive/provider_allowed=false never in provider payload; Woo ban.

**ARCHITECTURE:** PluginGuard; no generic filter engine; no public API; no SOURCE_META; Rank Math drift; TARGET 7.

**PERFORMANCE:** O(R) characterization (~32), not production reject.

**BROWSER:** Not required for planning freeze. Optional Rank Math smoke post-implementation — not a freeze gate.

---

## 27. Risks / debt

| Risk | Mitigation |
|---|---|
| False orphan on plugin absent (pre-existing) | CASE B retain-keys (fixes Rank Math path too) |
| Generic overlay engine creep | Explicitly banned; reference adapters only |
| Catalog grows into shadow SurfaceRegistry | RM3 + tests: no auth/publicness/Jobs/publish decisions |
| Term Jobs regression | Dedicated RM21 / AC22–25 |
| field_key collapse to `_meta` | Matches `_plugin`; labels + segment_key carry meaning |
| Overclaiming product value | Amendment 10 / AC32 |
| Retain-keys misuse | Document CASE C code removal as intentional; tests for untouched CASE B rows |

---

## 28. STOP / redesign conditions

| Condition | Planning status |
|---|---|
| Need `SOURCE_META` / non-BIGINT host | Not required |
| Need durable registration table for CASE B | **Not required** — retain-keys suffice |
| Need translated meta table writes | Not required |
| Need public register API for value | Not required |
| Need generic filter overlay engine | **Rejected** |
| Need hard ceiling of 32 | **Rejected** |
| Schema/TARGET bump | Not required → **STATE A** |
| Rank Math full rewrite / `m:` Store migration | Not required |
| Redesign required? | **No** |

If implementation discovers that retain-keys cannot preserve CASE B without corrupting CASE A/C, or that durable registration state is mandatory → **STOP** and escalate to STATE B / redesign — do not force into TARGET 7.

---

## 29. Non-goals (explicit)

- Arbitrary ACF / custom-field translation
- User-selected / admin-wildcard meta
- Options / theme_mods / usermeta
- Gutenberg document structures (TSC.4)
- Elementor document structures (TSC.5)
- Woo product-local attributes / economic meta
- Public extension API (TSC.6)
- Scanning third-party plugin tables
- HTML scraping / gettext
- Source-table translation writes
- Second TIQ / Jobs / OTL / publication engine

---

## 30. Implementation gate

Do **not** implement production code under TSC.2 until this plan is **Architecture Frozen** on `main`.

Authorized implementation branch (when opened later): `feature/tsc2-registered-meta-surfaces` (name may be adjusted by implementation task).

Execute TSC2.0 → TSC2.7 per this document. Do not start TSC.3+.
