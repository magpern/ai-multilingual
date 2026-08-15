# MSEO.3 — Hierarchical Pages, Terms & Taxonomy Localized URLs — Implementation Plan

**Status:** **Architecture Frozen** — authoritative specification for MSEO.3 implementation  
**Milestone:** MSEO.3 — Hierarchical Pages, Terms & Taxonomy Localized URLs  
**Parent:** [MSEO_PARENT_IMPLEMENTATION_PLAN.md](MSEO_PARENT_IMPLEMENTATION_PLAN.md)  
**ADR:** [0023-localized-url-overlay-architecture.md](../adr/0023-localized-url-overlay-architecture.md) (**Accepted**)  
**External review:** **FREEZE** (A1–A5)  
**STATE:** B · **TARGET 8** (no migration) · **Version:** 1.4.0  
**Planning materialization:** docs-only on `main`  
**Implementation branch:** `feature/mseo3-hierarchy-terms-taxonomy-localized-urls` (create after freeze push)  
**Baseline:** `main` @ `c4556506c8f72fad39c38ba3f1033c29f51c2c59`  
**Depends on:** MSEO.0–MSEO.2 COMPLETE; ADR-0023 Accepted  

**This document is the authoritative implementation specification for MSEO.3.** Do not start MSEO.4.

---

## 1. Repository baseline

| Item | Value |
|---|---|
| HEAD | `c4556506c8f72fad39c38ba3f1033c29f51c2c59` |
| Version | 1.4.0 |
| TARGET | 8 |
| Public routing | MSEO.2 live for flat post / top-level page / plain product |
| Terms / hierarchy | Capability-blocked; frontier table inert |
| Settings | `localized_urls_state` / activation checkpoint / error |

---

## 2. Exact objective

Extend the same MSEO URL architecture so admitted **hierarchical pages** and **taxonomy terms** get prepared-route localized URLs, one-hop redirects, SEO graph, and bounded descendant path maintenance — without mutating WP `post_name` / term `slug`, without rewrite rules, without a second router/store/EffectiveUrl/history/publication model, and without translating rewrite bases.

**Hierarchy model:** OPTION B — each hierarchical object contributes its effective localized slug (full localized path).

---

## 3. External amendments A1–A5 (frozen)

### A1 — Implemented vs publicly admitted capability

| Fact | Definition |
|---|---|
| **Implemented** | Code can safely process the shape (`RoutingCapabilityRegistry` technical support). |
| **Publicly admitted** | Shape passed verification and may participate in public EffectiveUrl / PathRecognition generation / discoverability / SEO / outbound. |

**Epoch model (Settings option JSON — no migration):**

- `CODE_CAPABILITY_EPOCH` — PHP constant in code
- `localized_urls_verified_capability_epoch` — persisted int (default `0`)
- `localized_urls_admitted_capabilities` — persisted list/set of shape ids (`term_archive`, `page_hierarchical`, …)

Shape public iff: `localized_urls_state === on` AND shape ∈ admitted set AND normal eligibility/route rules.

**Single admission authority:** `RoutingCapabilityAdmission` (or registry-consistent equivalent). Consumers must use admission — not raw `supports_*` — for EffectiveUrl, PathRecognition generation, discoverability, SB11, `home_url`, `term_link`, SEO.

**Atomic admission:** Do not update admitted-set/verified-epoch incrementally during a pass. Checkpoint may advance; public admission writes only after the entire relevant verification pass succeeds without blocking failure.

**Deploy safety:** state=on → MSEO.3 code deploys → implemented=true, admitted still old → new shapes source-slug/non-public → verification → admit → public.

### A2 — Admission gate before first MSEO.3 public activation

Public-admission foundation lands in **MSEO3.0**.

| Boundary | When |
|---|---|
| First **term** public | End of **MSEO3.2**, after successful `term_archive` verification/admission |
| First **hierarchy** public | End of **MSEO3.4**, after successful `page_hierarchical` verification/admission |

No second global enable toggle.

### A3 — Overlapping frontier semantics

UNIQUE `(parent_source_type, parent_source_id)` — different roots are distinct rows.

- `generation` supersedes **only same-root** prior work.
- **No** cross-root coalescing claim.
- Overlapping frontiers **allowed**; workers **recompute** desired paths from **current** WP hierarchy + **current** ancestor routes (never stale path snapshots).
- Route lock before mutate; equal paths → no-op; history only on real transition.
- Second worker on same child converges/no-ops (no duplicate history).

### A4 — Bounded O(depth) DFS checkpoint

```
checkpoint_json = {
  generation,
  stack: [ { source_type, source_id, last_child_id } ],
  processed_count,
  conflict_ids,       // hard-capped e.g. 32
  conflict_overflow
}
```

- `MAX_STACK_DEPTH` e.g. 64; overflow/cycle → `failed`
- ≤100 descendants/tick; direct-child pages bounded (20–50)
- Expand top frame only; update `last_child_id`; push child for DFS; pop when exhausted
- **No** unbounded `queue:[all nodes]`; checkpoint size O(depth), independent of N
- 1000-node trees require multiple ticks; every descendant eventually reachable

### A5 — Degraded subtree + term source-path authority

**Collision:** parent not rolled back; non-conflicting descendants continue; conflicting child retains prior route; no candidate/`slug_origin` mutation; frontier → `degraded` (never `completed` while conflicts remain); diagnostics expose root/conflicts; retry recomputes from current state. Mixed hierarchy accepted TARGET 8 limitation.

**Term source path:** derive from WordPress/Woo `get_term_link()` (+ proven rewrite facts). AIML substitutes admitted localized components into that structure. Custom category/product-category bases respected, untranslated. Fixtures required. No rewrite-rule duplication.

---

## 4. Capability / taxonomy matrix

| Shape | Class |
|---|---|
| `category`, `post_tag` | Supported (after `term_archive` admission) |
| `product_cat` / `product_tag` **archives** | Supported (object slug; base untranslated) |
| `pa_*` term **values** (public admitted taxonomies with real archives) | Supported when routable |
| Attribute taxonomy **labels** | Unsupported |
| Non-public/machine taxonomies | Unsupported |
| Custom taxonomies outside AdmittedTaxonomies | Deferred |
| Hierarchical pages | Supported after `page_hierarchical` admission |
| `%product_cat%` product permalinks | **MSEO.4** — do not enable |

---

## 5. Term FORMAT_SLUG lifecycle

| Field | Value |
|---|---|
| `source_type` | `term` |
| `source_id` | `term_id` |
| `source_subtype` | taxonomy slug |
| Segment | `FORMAT_SLUG`; `source_text` = canonical `term.slug` |
| `slug_origin` | generated \| manual |
| Generated from | translated term **name** (local sanitize; no provider) |

Publication under `Store::with_term_compat_authority` + route locks; `publish_under_route_authority` only. Standalone FORMAT_SLUG rejected. Permissions: AIML cap + underlying term edit caps.

---

## 6. Hierarchy path authority

Sole authority: `HierarchyPathBuilder`.

Inputs: source identity, language, WP parents, active ancestor route leaves, canonical permalink structure (`get_term_link` / page URI facts).  
Outputs: source full path, localized full path.  
No duplicate concatenation in publication / EffectiveUrl / worker / collision checker.

---

## 7. Frontier statuses

`pending` | `running` | `completed` | `degraded` | `failed`  
(free-form VARCHAR — no migration)

---

## 8. Capability verification taxonomy

Reuse MSEO.2 patterns: `ADMITTED`, `SKIPPED_UNSUPPORTED`, `SKIPPED_NOT_PUBLIC`, `INVALID_DATA`, `CONFLICT`, `SYSTEM_ERROR`.

- Unsupported/not-public objects need not fail the capability globally.
- Invariant corruption / system failure blocks capability admission.
- Degraded hierarchy conflicts ≠ completed.
- Term/hierarchy verification failure must **not** disable working MSEO.2 flat routes.

---

## 9. Work package ladder

| WP | Scope | Public? |
|---|---|---|
| **MSEO3.0** | Characterization; admission epoch foundation; path-builder scaffold | No |
| **MSEO3.1** | Term FORMAT_SLUG + candidate/publication under TSC (inert public) | No |
| **MSEO3.2** | Term routing + SEO; verify → admit `term_archive` | **First term public** after admit |
| **MSEO3.3** | Hierarchy path authority + DFS frontier worker | No visitor hierarchy |
| **MSEO3.4** | Triggers, rematerialize, history, degraded/overlap; verify → admit `page_hierarchical` | **First hierarchy public** after admit |
| **MSEO3.5** | Diagnostics / CLI | — |
| **MSEO3.6** | PluginGuard, perf 1k, browser, evidence | — |

---

## 10. Requirements M3R1–M3R56

| ID | Requirement | Class |
|---|---|---|
| M3R1 | OPTION B full localized hierarchy | Supported |
| M3R2 | Same Router / EffectiveUrl / history | Supported |
| M3R3 | Term FORMAT_SLUG reuses MSEO.1 model | Supported |
| M3R4 | Term publication under TSC.1 authority | Supported |
| M3R5 | No post_name / term.slug mutation | Supported |
| M3R6 | No rewrite-rule duplication | Supported |
| M3R7 | Rewrite bases untranslated | Supported |
| M3R8 | product_cat archive Supported; %product_cat% product Deferred MSEO.4 | Supported |
| M3R9 | pa_* values ≠ attribute labels | Supported |
| M3R10 | TARGET 8 / no migration | Supported |
| M3R11 | ADR-0023 sufficient | Supported |
| M3R12 | Non-atomic subtree migration accepted | Supported |
| M3R13 | HISTORY_MAX=5 source-identity history | Supported |
| M3R14 | No provider slug generation | Supported |
| M3R15 | Implemented ≠ publicly admitted capability | Supported |
| M3R16 | CODE_CAPABILITY_EPOCH + Settings verified epoch/admitted set | Supported |
| M3R17 | Single RoutingCapabilityAdmission authority | Supported |
| M3R18 | Atomic admission after full verification only | Supported |
| M3R19 | Deploy while ON does not instantly expose new shapes | Supported |
| M3R20 | Admission foundation before first MSEO.3 public | Supported |
| M3R21 | First term public end MSEO3.2 after admit | Supported |
| M3R22 | First hierarchy public end MSEO3.4 after admit | Supported |
| M3R23 | No second global enable toggle | Supported |
| M3R24 | Same-root generation supersede only | Supported |
| M3R25 | Different-root frontiers overlap + converge idempotently | Supported |
| M3R26 | Workers recompute from current hierarchy/routes | Supported |
| M3R27 | No stale path snapshots applied | Supported |
| M3R28 | Overlap no-op / no duplicate history | Supported |
| M3R29 | O(depth) DFS checkpoint; no unbounded BFS queue | Supported |
| M3R30 | ≤100 descendants/tick; bounded child pages | Supported |
| M3R31 | MAX_STACK_DEPTH fail on overflow/cycle | Supported |
| M3R32 | Every descendant eventually reachable | Supported |
| M3R33 | Parent not rolled back on descendant collision | Supported |
| M3R34 | Conflicting child retains prior route; no candidate mutation | Supported |
| M3R35 | Frontier degraded ≠ completed while conflicts remain | Supported |
| M3R36 | Term source path from get_term_link / WP-Woo authority | Supported |
| M3R37 | Custom category / product-category bases respected | Supported |
| M3R38 | HierarchyPathBuilder sole path authority | Supported |
| M3R39 | Descendant rematerialization is route maintenance | Supported |
| M3R40 | Term delete purges route/history/candidate | Supported |
| M3R41 | Permissions: AIML + term edit caps | Supported |
| M3R42 | Source-neutral SEO discoverability for terms | Supported |
| M3R43 | Capability verify failure does not disable MSEO.2 flats | Supported |
| M3R44 | Cache invalidation on admission/route/hierarchy events | Supported |
| M3R45 | Frontend no ancestor walk; full path persisted | Supported |
| M3R46 | Synthetic 1k page + 1k term multi-tick | Supported |
| M3R47 | Diagnostics for epoch/frontier/conflicts | Supported |
| M3R48 | PluginGuard positive + forbid invariants | Supported |
| M3R49 | category / post_tag / product_tag archives | Supported |
| M3R50 | Hierarchical taxonomy parent-in-URL detection | Supported |
| M3R51 | Preview remains source-slug (pages); no invented term preview | Supported |
| M3R52 | Race matrix coverage (overlap, supersede, OFF mid-job) | Supported |
| M3R53 | Recognition kinds unchanged (NONE/CURRENT/HISTORY/SOURCE) | Supported |
| M3R54 | Query preservation on redirects | Supported |
| M3R55 | MSEO.2 flat regression retained | Supported |
| M3R56 | MSEO.4 not started | Supported |

**Count: M3R1–M3R56.**

---

## 11. Acceptance M3AC1–M3AC50

| ID | Criterion |
|---|---|
| M3AC1 | TARGET remains 8 |
| M3AC2 | No migration |
| M3AC3 | Version 1.4.0 |
| M3AC4 | Canonical WP slugs untouched |
| M3AC5 | Term candidate generated locally from name |
| M3AC6 | Provider not used for slugs |
| M3AC7 | Term slug publication route-owned |
| M3AC8 | TSC term locking preserved |
| M3AC9 | Hierarchical path computation deterministic via HierarchyPathBuilder |
| M3AC10 | Descendant discovery bounded DFS |
| M3AC11 | No all-descendant materialization |
| M3AC12 | Ancestor route change updates descendant URLs (maintenance) |
| M3AC13 | Old descendant routes enter history on real transition |
| M3AC14 | Redirects one-hop |
| M3AC15 | Collision fail-safe (hold child, degraded) |
| M3AC16 | Job resumable |
| M3AC17 | Same-root generation coalescing |
| M3AC18 | Delete cleanup |
| M3AC19 | Page hierarchical route works after admission |
| M3AC20 | Category route works after admission |
| M3AC21 | product_cat archive Supported; product %product_cat% not enabled |
| M3AC22 | pa_* verdict proven |
| M3AC23 | Switcher correct |
| M3AC24 | Canonical correct |
| M3AC25 | Hreflang correct |
| M3AC26 | Sitemap Model A correct |
| M3AC27 | Feature OFF behavior correct |
| M3AC28 | Capability expansion while ON safe (no instant exposure) |
| M3AC29 | No rewrite rules |
| M3AC30 | No source slug mutation |
| M3AC31 | MSEO.4 not started |
| M3AC32 | Admission authority used by EffectiveUrl/SEO/outbound |
| M3AC33 | Admitted set written only after full verification pass |
| M3AC34 | Term first-public only end MSEO3.2 after admit |
| M3AC35 | Hierarchy first-public only end MSEO3.4 after admit |
| M3AC36 | Different-root overlap converges without duplicate history |
| M3AC37 | Workers never apply stale path snapshots |
| M3AC38 | Checkpoint size O(depth) not O(N) |
| M3AC39 | 1000-page tree multi-tick |
| M3AC40 | 1000-term tree multi-tick |
| M3AC41 | Degraded frontier never marked completed with open conflicts |
| M3AC42 | Parent remains active under degraded model |
| M3AC43 | Conflict retry deterministic from current hierarchy |
| M3AC44 | Term source URL from get_term_link |
| M3AC45 | Custom category base fixture |
| M3AC46 | Woo product category base fixture |
| M3AC47 | Term discoverability excludes FORMAT_SLUG-only |
| M3AC48 | Verification failure does not disable MSEO.2 flats |
| M3AC49 | PluginGuard green for MSEO.3 boundaries |
| M3AC50 | MSEO.2 flat routing regression suite green |

**Count: M3AC1–M3AC50.**

---

## 12. Non-goals / Deferred / Unsupported

- Translated taxonomy rewrite bases  
- Woo endpoint localization  
- `%product_cat%` product permalinks (MSEO.4)  
- Arbitrary CPT/taxonomy public admission  
- Extension API URL registration  
- Atomic subtree generation flip / TARGET 9  
- Provider slug generation  
- Tag / release / deploy  
- MSEO.4+

---

## 13. Schema / ADR verdict

| Item | Verdict |
|---|---|
| STATE | B |
| TARGET | 8 |
| Migration | None |
| Frontier table | Sufficient (checkpoint_json + generation + status) |
| Settings epoch | Sufficient (option JSON) |
| ADR-0023 | Sufficient (§19–§21 + Settings admission) |

---

## 14. STOP conditions

TARGET 9 requirement, ADR contradiction, rewrite-rule necessity, or atomic-subtree product requirement → **MSEO.3 ARCHITECTURE REOPEN REQUIRED**.

**MSEO.4 NOT STARTED.**
