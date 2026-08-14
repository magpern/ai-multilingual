# MSEO — Multilingual SEO & Localized URL Architecture — Parent Program Plan

**Status:** **Architecture Frozen** (external review complete; A1–A16 + F1–F14)
**Program:** MSEO — Multilingual SEO & Localized URL Architecture
**Plan freeze:** Optional administrator-controlled localized URL slugs; default OFF; STATE B; TARGET 8; ADR-0023 required
**ADR:** [0023-localized-url-overlay-architecture.md](../adr/0023-localized-url-overlay-architecture.md) (**Accepted** at planning freeze)
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md)
**Implementation priority:** [PRODUCT_PRIORITIES.md](../PRODUCT_PRIORITIES.md)
**Planning branch:** `docs/mseo-parent-plan-freeze`
**Baseline (plan freeze):** `main` @ `2c5bb0fd47a8fbb8e57d51c6ee85bbc1647a8386` (released **v1.4.0**)
**Depends on:** v1.4.0; TIQ / OTL / TSC / Extension API v1 / A.SEOa–A.SEOf **Complete**; `Migrator::TARGET` **7** at freeze; ADR-0001 / 0002 / 0003 / 0005 / 0007 / 0008 / 0020 / 0021 **Accepted**

**Milestone ladder:** MSEO.0 → MSEO.5 (see §11)

**This document is the authoritative MSEO program architecture contract.** Do not implement production routing/SEO URL changes until the relevant milestone plan is Architecture Frozen and explicitly authorized. MSEO.0 is inert infrastructure only.

---

## 1. Purpose

Deliver **optional localized URL slugs** as a first-class multilingual capability:

- Administrators choose source-slug vs localized-slug behavior (global, default **OFF**).
- Canonical WordPress `post_name` / term `slug` are **never mutated**.
- Localized URLs converge automatically across routing, canonical, hreflang, sitemap (Model A), switcher, and ordinary outbound permalinks.
- No provider calls for slug generation; no rewrite-rule duplication (ADR-0002 preserved).

MSEO does **not**:

- mutate WP/Woo canonical slug columns
- register AIML rewrite rules or flush rewrite state
- expand Extension API v1
- translate rewrite bases or Woo machine endpoints
- ship activatable localized URLs before MSEO.2 minimum safe stack

---

## 2. Preconditions (verified at plan freeze)

| Precondition | Status |
|---|---|
| `main` == `origin/main` @ `2c5bb0fd47a8fbb8e57d51c6ee85bbc1647a8386` | **Pass** |
| Working tree clean | **Pass** |
| Plugin version **1.4.0** | **Pass** |
| `Migrator::TARGET` = **7** | **Pass** |
| A.SEOa–A.SEOf complete | **Pass** |
| No prior MSEO production code | **Pass** |
| v1.4.0 dogfood authorized MSEO planning | **Pass** |

---

## 3. Repository integration map (current v1.4.0)

| Concern | Current owner | MSEO integration |
|---|---|---|
| Language prefix routing | [`Router`](../src/Routing/Router.php), [`LanguageResolver`](../src/Language/LanguageResolver.php) | Extend `Router::resolve()` inbound pipeline; post-resolution canonical @ `wp:5`; delegate outbound to `EffectiveUrlService` |
| Outbound prefix | `Router::filter_home_url()` @ `parse_request:0` | Call `EffectiveUrlService` (source → localize → prefix → query) |
| Preview URLs | [`PreviewService`](../src/Workspace/PreviewService.php) | **Unchanged** — source-slug only (ADR-0023) |
| SEO URL graph | [`LanguageRelationshipService`](../src/Seo/LanguageRelationshipService.php) (SB11) | Consume `EffectiveUrlService` per language (MSEO.2+) |
| Canonical / hreflang | [`DocumentSeoHead`](../src/Seo/DocumentSeoHead.php) | Filter values via upgraded SB11 |
| Sitemap alternates | [`RankMathSitemapOverlay`](../src/Integration/RankMath/RankMathSitemapOverlay.php) | Model A — xhtml:link only; no `loc` replacement |
| Switcher | [`Switcher`](../src/Frontend/Switcher.php) | SB11 URLs |
| Segment store | [`Store`](../src/Translation/Store.php) | Slug candidate segments + `slug_origin` column; `publish_clear_fields` on candidate edit |
| Publication | [`PublicationService`](../src/Translation/Publication/PublicationService.php) | Composed by eligibility; route publication separate action |
| Public overlay eligibility | `Store::is_publicly_overlay_eligible()` | Composed by `ObjectLanguagePublicEligibility` — not title-only proxy |
| Settings | [`Settings`](../src/Settings.php) | `localized_urls_state` machine + activation checkpoint fields |
| Migrations | [`Migrator`](../src/Database/Migrator.php), [`Schema`](../src/Database/Schema.php) | TARGET 8 step |
| Jobs pattern | Action Scheduler + [`Jobs`](../src/Jobs/) | Activation/reindex jobs (MSEO.2+) |
| PluginGuard | [`PluginGuardTest`](../tests/integration/PluginGuardTest.php) | MSEO structural invariants |

---

## 4. Frozen architecture (A1–A16 + F1–F14 summary)

### 4.1 Persistence — STATE B, TARGET 8

| Artifact | Role |
|---|---|
| `aiml_slug_routes` | One row per `(source_type, source_id, language_id)`; bidirectional full-path authority |
| `aiml_route_history` | Source-identity historical paths; no stored destination |
| `aiml_slug_reindex_frontier` | Incremental hierarchy reindex queue |
| `aiml_translations.slug_origin` | Candidate origin (`generated` \| `manual`) |

Path hashes: **`BINARY(32)` SHA-256** + mandatory full-string verify after lookup.

### 4.2 Path authority

- **PathCanonicalizer** — single contract for all path operations.
- Index keys: `(language_id, localized_path_hash)` inbound; `(language_id, source_path_hash)` outbound.
- `route_namespace` metadata only; no `parent_path` column.

### 4.3 GenerationEnablement vs PathRecognition

| Concept | Rule |
|---|---|
| **GenerationEnablement** | `localized_urls_state=on` + capability + active route → localized effective URLs |
| **PathRecognition** | **Always** recognizes AIML paths on frontend; **never** gated by setting |

Inactive/disabled: **302 temporary** to source-slug language URL. Permanent **301** only for deliberate slug replacement (history, ON) and post-resolution source-slug duplicate (ON + active route).

### 4.4 Candidate vs active route

- **Candidate:** Store segment (`translated_text`, `slug_origin`, normal TI.7 axes).
- **Active route:** `aiml_slug_routes` row (`route_status` active \| inactive).
- Candidate edit → `publish_clear_fields` on slug segment; **active route unchanged** until atomic route publication.

### 4.5 Collision and reservation

- **CanonicalPathCollisionChecker** before activation: AIML routes + history + bounded `url_to_postid()` / term path owner.
- `(language_id, localized_path)` reserved across current + history tables.
- Lookup precedence: **current route → history → WP parse**.

### 4.6 Eligibility and capabilities

- **ObjectLanguagePublicEligibility** — object/language bundle (not title-only); composes Store publication gate + language status + source publicness.
- **RoutingCapabilityRegistry** — Model B: global ON but unsupported objects stay source-slug until milestone ships capability.

### 4.7 Activation state machine

`localized_urls_state`: **`off` | `activating` | `on` | `failed`**

UI never shows **On** during `activating`. Settings save O(1); `SlugRouteActivationJob` automatic and resumable.

### 4.8 Outbound order (F12)

1. Canonical unprefixed source path  
2. Canonicalize  
3. Source → localized substitution  
4. Language prefix (ADR-0002)  
5. Query string  

### 4.9 Sitemap Model A

Rank Math owns `<loc>` (default language). AIML injects **xhtml:link alternates** with per-language effective URLs only.

### 4.10 Hierarchy reindex (F8)

Incremental frontier queue; ≤100 objects per worker tick; no full descendant materialization.

---

## 5. TARGET 8 schema (frozen)

See [ADR-0023](../adr/0023-localized-url-overlay-architecture.md) §Schema and [MSEO0 plan](MSEO0_LOCALIZED_URL_FOUNDATION_IMPLEMENTATION_PLAN.md) §Schema audit.

---

## 6. Milestone ladder

| Milestone | State exposure | Delivers |
|---|---|---|
| **MSEO.0** | Internal `off`; no UI | ADR-0023, TARGET 8, PathCanonicalizer, EffectiveUrlService scaffold, repositories — **inert** |
| **MSEO.1** | Still no enable UI | Candidate/active model, slug_origin, ObjectLanguagePublicEligibility, Workspace slug field, RoutePublicationService scaffold |
| **MSEO.2** | **First activatable** | Full safe stack: recognition, collisions, history, outbound, SEO graph, state machine, activation job; flat post, top-level page, plain product |
| **MSEO.3** | On if enabled | Terms, hierarchical pages/categories, frontier reindex |
| **MSEO.4** | On | Woo permalink-structure hardening, operator UX |
| **MSEO.5** | On | PluginGuard, browser acceptance, release, dogfood |

**Hard invariant:** `localized_urls_state` not administrator-activatable until MSEO.2 acceptance complete.

---

## 7. Requirements matrix (MSEO1–MSEO74, count: 74)

See planning freeze archive §Requirements in ADR-0023 cross-reference. Categories: product, storage, path canonicalization, routing, lifecycle, collision, redirects, SEO graph, outbound, capability/activation, hierarchy, security, compatibility.

---

## 8. Acceptance criteria (MAC1–MAC34, count: 34)

Program-level acceptance spans MSEO.2–MSEO.5. MSEO.0 acceptance defined in [MSEO0 plan](MSEO0_LOCALIZED_URL_FOUNDATION_IMPLEMENTATION_PLAN.md) §Acceptance.

---

## 9. Deferred scope

- Translated rewrite bases (`/sv/produkt/`, `/sv/product-category/`)
- Woo endpoint names (cart, checkout, my-account)
- Attachment slugs; author/date/search archives
- Translated parent path components
- Product variations; `nav_menu_item` slugs
- Custom CPTs / taxonomies; multisite; headless
- Localized-slug preview URLs
- SE11 `SitemapDiscovery`; Extension API v1.1 URL observation
- Path reservation release admin tool (history blocks reuse until explicit future tool)

---

## 10. Unsupported scope

- Mutating `post_name` / term `slug`
- Runtime rewrite registration / flush
- Store full-table scans on frontend requests
- Fuzzy URL matching; provider slug generation
- Competing sitemap XML generator
- Per-language localized URL policy matrix (v1)

---

## 11. Program verdict

**MSEO PARENT PLAN REVIEW: FREEZE**

**Exact next step:** Implement [MSEO0_LOCALIZED_URL_FOUNDATION_IMPLEMENTATION_PLAN.md](MSEO0_LOCALIZED_URL_FOUNDATION_IMPLEMENTATION_PLAN.md) on `feature/mseo0-localized-url-foundation` when authorized. Do not expose localized URL enable UI until MSEO.2.
