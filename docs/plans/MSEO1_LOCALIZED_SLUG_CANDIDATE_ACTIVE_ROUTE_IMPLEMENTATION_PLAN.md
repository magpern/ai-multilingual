# MSEO.1 — Localized Slug Candidate & Active Route Lifecycle — Implementation Plan

**Status:** **Architecture Frozen** — authoritative specification for MSEO.1 implementation
**Milestone:** MSEO.1 — Localized Slug Candidate & Active Route Lifecycle
**Parent:** [MSEO_PARENT_IMPLEMENTATION_PLAN.md](MSEO_PARENT_IMPLEMENTATION_PLAN.md) (**Architecture Frozen**)
**ADR:** [0023-localized-url-overlay-architecture.md](../adr/0023-localized-url-overlay-architecture.md) (**Accepted**)
**External review:** **FREEZE** (A1–A4 + B1–B5)
**STATE:** B · **TARGET 8** (unchanged — **no migration**)
**Implementation branch:** `feature/mseo1-localized-slug-route-lifecycle` (create after this freeze lands on main)
**Baseline:** `main` @ `c2282f3a12ce6f0882f6718fbf5e253164bc7013` (v1.4.0; `Migrator::TARGET` **8**; MSEO.0 COMPLETE)
**Depends on:** MSEO.0 COMPLETE; ADR-0023 Accepted; MSEO parent frozen

**This document is the authoritative implementation specification for MSEO.1.** MSEO.1 makes editorial slug candidates and prepared active routes real and testable while **public localized routing remains OFF**.

**Verdict:** `MSEO.1 PLAN REVIEW: FREEZE`

---

## External review amendments (frozen)

| ID | Disposition |
|---|---|
| **A1** | **Accepted** — `slug_origin` sole write via `SlugCandidateService` |
| **A2** | **Accepted** — Editorial candidate ≠ collision-resolved effective route slug |
| **A3** | **Accepted** — Same-object historical path reuse |
| **A4** | **Accepted** — `source_path` refresh on `RoutePublicationService` |
| **B1** | **Adopted** — FORMAT_SLUG publication axis is **route-owned** only |
| **B2** | **Adopted** — UI sync from lifecycle facts, not text equality |
| **B3** | **Adopted** — `PublicationService::publish` fail-closed for FORMAT_SLUG; internal under-route primitive |
| **B4** | **Adopted** — Unchanged published candidate → idempotent `publish_route` (preserve effective slug) |
| **B5** | **Confirmed** — TARGET 8 sufficient; `publish_status` is sync signal; no TARGET 9 |

---

## 1. Objective

Implement:

1. Editorial localized slug candidates in Store (`post_name` / `FORMAT_SLUG`)
2. Prepared active route lifecycle in `aiml_slug_routes`
3. Atomic route-owned candidate publication
4. Workspace/OTL operator controls with honest sync state

While **not** activating public localized URL routing, redirects, SEO localization, enable UI, or MSEO.2.

### Concepts

| Concept | Storage | Public effect in MSEO.1 |
|---|---|---|
| Editorial candidate | Store segment + `slug_origin` + TI.7 axes | None |
| Effective prepared route | `aiml_slug_routes` (`route_status=active`) | None — prepared only |
| Sync signal | Candidate `publish_status=published` | Means candidate incorporated into prepared route (**not** text equality) |

Valid collision-adjusted synchronized state: candidate `foo`, route `foo-2`, `publish_status=published`.

---

## 2. Scope

**In scope (post-like):** `post`, `page`, `product` (`Store::SOURCE_POST`).

**Deferred to MSEO.3:** term slug candidates, term routes, hierarchical page route semantics beyond capability gate, frontier traversal.

**Candidate editing:** all post-like with `post_name`.  
**Route publication:** capability-gated (`post_flat`, `page_top_level`, `product_plain_permalink`).

---

## 3. Segment identity

| Field | Value |
|---|---|
| `source_type` | Existing post ownership |
| `field_key` / `segment_key` | `post_name` |
| `text_format` | `Store::FORMAT_SLUG` |
| `source_text` | Canonical WP `post_name` |
| `translated_text` | Editorial localized leaf candidate |

`Extractor::FIELD_SLUG = 'post_name'` must emit in extract/sync. Never mutate WP `post_name`.

---

## 4. FORMAT_SLUG

- Plain slug; no HTML; no provider (`provider_allowed = false`)
- Normalize: `strtolower(trim(...))`
- Generate/validate: `sanitize_title( $text, '', 'save' )`
- Exclude from Jobs Missing/Stale, item processor, provider path; TM already denies

---

## 5. Source sync

Extractor sync updates `source_text`/`source_hash`; never overwrites `translated_text`/`slug_origin`; never writes `SlugRouteRepository`. Permalink/`post_name` change signals `RoutePublicationService::refresh_source_path`.

---

## 6. SlugCandidateService

Owns generation, validation, manual edit, clear, `slug_origin` transitions.

**Generate:** translated `post_title` → strip tags → decode entities → trim → `sanitize_title(..., '', 'save')`. Reject if `slug_origin=manual`. No provider. No auto-regenerate on title change. No bulk.

**Manual:** reject silent sanitize drift (return normalized suggestion). Empty → MISSING + `slug_origin=''`.

---

## 7. slug_origin (A1)

Allowed: `''` | `generated` | `manual`

| Actor | May write? |
|---|---|
| `SlugCandidateService` via `Store::save_slug_candidate` | **Yes — sole** |
| Generic `save_translation` | **Preserve only** |
| `RoutePublicationService` | **Copy** to route row only |
| Extractor / sync / PublicationService axis writers | **No** |

---

## 8. Candidate ≠ effective route (A2)

Collision suffix for **generated** persists on route only. Store candidate unchanged. Manual foreign collision → 409 (no silent suffix).

---

## 9. Route-owned FORMAT_SLUG publication (B1/B3)

| Path | Behavior |
|---|---|
| Generic `PublicationService::publish` / REST `/publish` | **Fail closed** for FORMAT_SLUG |
| `RoutePublicationService::publish_route` | **Sole** authority to set slug candidate `published` |
| Review axis | Independent (submit/approve/reject OK) |
| Non-slug formats | Unchanged |

`publish_status=published` means: current editorial candidate incorporated into current prepared route.

Material candidate edit → `publish_clear_fields` → pending; prior prepared route remains.

---

## 10. PublicationService integration (B3)

- Public `publish()` fail-closed for FORMAT_SLUG
- Internal `publish_under_route_authority(...)` reuses PublicationPolicy + `update_publish_metadata`
- Participates in outer route transaction via Store SAVEPOINT/TSC.1 nesting
- No early independent commit; no second TI.7 engine

---

## 11. RoutePublicationService

Single lifecycle authority (no second `RouteLifecycleService`).

Methods: `publish_route`, `refresh_source_path`, optional `unpublish_route`.

### publish_route sequence

1. Begin/participate in transaction  
2. Lock candidate (`FOR UPDATE`)  
3. Lock current route (`FOR UPDATE`)  
4. Validate candidate  
5. Evaluate review/publication eligibility  
6. **Idempotence (B4):** if already published + active route + no material change → preserve effective slug; success no-op  
7. Else resolve effective slug (collision/history reuse)  
8. Publish candidate via under-route primitive  
9. UPSERT prepared route  
10. History for superseded path; trim max 5  
11. Commit  
12. Invalidate  

Active routes while `localized_urls_state=off` are allowed (prepared, not public).

---

## 12. Idempotent re-publish (B4)

`foo` → collision → route `foo-2` → published. Later collision disappears → repeat `publish_route` with unchanged candidate → route **remains `foo-2`**. No MSEO.1 re-resolve operation.

---

## 13. History reuse (A3)

Same object/language MAY reclaim own historical path (delete own history row, then activate). Cross-object historical path = collision. Never current+historical same path same object/lang. Max 5. No destination/redirects.

---

## 14. source_path refresh (A4)

`RoutePublicationService::refresh_source_path` sole writer. Updates `source_path`/`source_path_hash` only for capability-supported existing routes. Never changes localized leaf, invents history, or publishes. Lock-serialized with publish.

---

## 15. RoutingCapabilityRegistry (inert)

Supported for publish: `post_flat`, `page_top_level`, `product_plain_permalink`. Unsupported: hierarchical, term_archive, product_category_permalink. Not wired into Router.

---

## 16. ObjectLanguagePublicEligibility

`is_route_publishable(...)` compositional. `is_discoverable(...)` **always false** in MSEO.1.

---

## 17. UI / REST sync semantics (B2)

States: `NO_CANDIDATE` | `CANDIDATE_PENDING` | `PREPARED_SYNCHRONIZED` | `NO_PREPARED_ROUTE` | `INCONSISTENT`.

`route_sync_state`: `none` | `pending` | `synchronized` | `inconsistent` — from `publish_status` + route presence, **not** text equality.

`collision_adjusted`: true when synchronized AND candidate ≠ active_route_slug.

Actions: generate-slug, edit, clear, **publish prepared route**. Hide/reject generic FORMAT_SLUG segment publish.

Permissions: `aiml_translate` + `edit_post`.

---

## 18. Trash / delete

Trash → route `inactive`. Untrash → stay inactive until explicit re-publish. Permanent delete → purge routes/history.

---

## 19. Jobs / bulk / public routing

FORMAT_SLUG excluded from Jobs/provider/TM. **No bulk.** No public routing, redirects, rewrite, home_url/canonical/hreflang/sitemap/switcher localization, enable UI, activation jobs, term URLs.

---

## 20. Schema / TARGET

TARGET remains **8**. No migration. No candidate hash/version column on routes. B5: `publish_status` is sync signal because standalone FORMAT_SLUG publish is impossible.

If proven false → `MSEO.1 ARCHITECTURE REOPEN REQUIRED`.

---

## 21. Work package ladder

| WP | Deliverable |
|---|---|
| **MSEO1.0** | Extractor `post_name`; Store hydrate + preserve origin; `save_slug_candidate` |
| **MSEO1.1** | `SlugCandidateService` generate/validate/manual/clear |
| **MSEO1.2** | Capability + eligibility + collision (effective on route only) |
| **MSEO1.3** | `RoutePublicationService` + under-route publish + history reuse + idempotence |
| **MSEO1.4** | Workspace/OTL REST/UI sync state; publish-route action |
| **MSEO1.5** | `refresh_source_path`; trash/delete; Jobs exclusion; concurrency |
| **MSEO1.6** | PluginGuard; evidence; zero-public-routing regression |

---

## 22. Requirements matrix (M1R1–M1R36)

| ID | Requirement | Status |
|---|---|---|
| M1R1 | Deterministic `post_name` / FORMAT_SLUG identity | Supported |
| M1R2 | Source sync from WP `post_name` | Supported |
| M1R3 | FORMAT_SLUG + provider denied | Supported |
| M1R4 | Generate from translated title via sanitize_title | Supported |
| M1R5 | slug_origin state machine | Supported |
| M1R6 | slug_origin sole write via SlugCandidateService (A1) | Supported |
| M1R7 | Generic save_translation preserves slug_origin (A1) | Supported |
| M1R8 | Manual edit validation; no post_name mutation | Supported |
| M1R9 | Candidate edit clears TI.7 publish; active route intact | Supported |
| M1R10 | Atomic RoutePublicationService | Supported |
| M1R11 | Active prepared routes while state=off | Supported |
| M1R12 | Transaction/lock ordering | Supported |
| M1R13 | Flat path construction | Supported |
| M1R14 | RoutingCapabilityRegistry gating | Supported |
| M1R15 | ObjectLanguagePublicEligibility | Supported |
| M1R16 | Collision checker | Supported |
| M1R17 | Editorial candidate ≠ effective route slug (A2) | Supported |
| M1R18 | History max 5 / source identity | Supported |
| M1R19 | Same-object historical path reuse (A3) | Supported |
| M1R20 | Source-path refresh via RoutePublicationService (A4) | Supported |
| M1R21 | Source slug change never alters localized leaf | Supported |
| M1R22 | Trash/delete data policy | Supported |
| M1R23 | Workspace/OTL slug UI | Supported |
| M1R24 | REST honesty (candidate + effective) | Supported |
| M1R25 | Permissions parity | Supported |
| M1R26 | Jobs/provider exclusion | Supported |
| M1R27 | No bulk | Supported |
| M1R28 | Invalidation events (no public rewrite) | Supported |
| M1R29 | Performance bounds | Supported |
| M1R30 | PluginGuard MSEO.1 | Supported |
| M1R31 | Zero public routing impact | Supported |
| M1R32 | Term / hierarchical / Woo category routes | Deferred |
| M1R33 | FORMAT_SLUG publication route-owned only (B1) | Supported |
| M1R34 | UI/REST sync from lifecycle facts; collision_adjusted separate (B2) | Supported |
| M1R35 | PublicationService fail-closed + under-route primitive (B3) | Supported |
| M1R36 | Idempotent publish_route; no opportunistic re-resolve (B4) | Supported |

**Count:** 36 (35 Supported + 1 Deferred).

---

## 23. Acceptance criteria (M1AC1–M1AC36)

| ID | Criterion |
|---|---|
| M1AC1 | Post-like source gets deterministic `post_name` slug segment |
| M1AC2 | Generated candidate comes from translated title; no provider call |
| M1AC3 | Manual candidate persists with `slug_origin=manual` |
| M1AC4 | Generate cannot overwrite manual |
| M1AC5 | Generic `save_translation` preserves `slug_origin` |
| M1AC6 | Only SlugCandidateService transitions slug_origin |
| M1AC7 | Candidate edit clears candidate publication |
| M1AC8 | Active route unchanged after candidate edit |
| M1AC9 | Candidate publish + route swap are atomic inside publish_route |
| M1AC10 | Active route may exist while `localized_urls_state=off` |
| M1AC11 | Route is not publicly used while state off |
| M1AC12 | Prior route enters history on replacement |
| M1AC13 | History max 5 |
| M1AC14 | Manual collision → 409; candidate unchanged |
| M1AC15 | Generated collision → route `foo-2`, Store candidate `foo`, origin `generated` |
| M1AC16 | Object-language eligibility enforced for route publish |
| M1AC17 | Unsupported capability cannot publish route |
| M1AC18 | Same-object red→blue→red reuses history deterministically |
| M1AC19 | Cross-object historical path remains collision |
| M1AC20 | Same path never current+historical for same object/language |
| M1AC21 | Source-path refresh via RoutePublicationService; leaf unchanged; no history |
| M1AC22 | Source slug change races serialize with publish |
| M1AC23 | Trash/delete behavior correct |
| M1AC24 | Permissions enforced (`aiml_translate` + `edit_post`) |
| M1AC25 | REST shows candidate and effective route honestly |
| M1AC26 | FORMAT_SLUG excluded from provider Jobs |
| M1AC27 | No Router/home_url/SEO/rewrite/`post_name` mutation |
| M1AC28 | TARGET stays 8; version stays 1.4.0 |
| M1AC29 | Clearing candidate resets `slug_origin` to `''` |
| M1AC30 | Hierarchical page can edit candidate but cannot publish route |
| M1AC31 | Direct generic FORMAT_SLUG publish is rejected |
| M1AC32 | Review axis remains independent of route publication |
| M1AC33 | Only RoutePublicationService publishes slug candidate |
| M1AC34 | Collision-adjusted synchronized state (`foo`/`foo-2`/published) is valid; text inequality alone ≠ pending |
| M1AC35 | Unchanged route re-publish is idempotent; disappeared collision does not churn effective route |
| M1AC36 | Normal non-slug PublicationService behavior unchanged |

**Count:** 36.

---

## 24. Test strategy

Unit + integration covering M1AC1–M1AC36, concurrency/rollback, PluginGuard, public-routing-off regression. Browser: local/non-CI optional UI smoke only.

---

## 25. Explicit non-goals

Public localized routing; redirects; SEO graph localization; enable UI; activation jobs; terms/hierarchy/Woo category; provider slug translation; bulk; re-resolve-route; version/TARGET bump; tag/release/deploy; **MSEO.2**.

---

## 26. Architecture verdicts

| Item | Verdict |
|---|---|
| STATE | B |
| TARGET | 8 (no migration) |
| Schema | TARGET 8 sufficient (B5) |
| ADR-0023 | Sufficient — no new ADR |
| MSEO.2 | NOT STARTED |

**MSEO.1 PLAN REVIEW: FREEZE**

**Exact next step:** Create `feature/mseo1-localized-slug-route-lifecycle` from this freeze SHA and implement MSEO1.0–MSEO1.6. STOP before MSEO.2.
