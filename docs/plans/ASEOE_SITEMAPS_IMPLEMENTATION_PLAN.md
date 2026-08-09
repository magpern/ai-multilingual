# A.SEOe — XML Sitemaps / Robots / Indexability — Implementation Plan

**Status:** **Complete** — merged to `main`; tagged `a-seoe-sitemaps-complete`; Supported SE1–SE9/SE12; Deferred SE10/SE11
**Milestone:** Program A — **A.SEOe** (fifth wave of A.SEO)
**Plan freeze:** Evidence-driven admissions SE1–SE12; Rank Math remains foreign sitemap/robots owner when active; AIML overlays via official Rank Math sitemap filters only; SB11 + A.SEOa–d consumed unchanged; TARGET **6**; Supported = **SE1–SE9, SE12**; Deferred = **SE10, SE11**
**ADR assessment:** **No new ADR required** for the Supported set if Implementation uses Integration API v1 + Rank Math official sitemap filters + SB11 + existing Router/LanguageContext. Do not reopen ADR-0001 / 0002 / 0008 / 0017. Do not change A.SEOa–d contracts.
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — §6.3 / A.SEO family
**Parent architecture:** [ASEO_PARENT_IMPLEMENTATION_PLAN.md](ASEO_PARENT_IMPLEMENTATION_PLAN.md) (authoritative; not redesigned)
**Dependency matrix:** [A_SEO_DEPENDENCY_MATRIX.md](A_SEO_DEPENDENCY_MATRIX.md)
**Evidence:** [aseoe-evidence/](aseoe-evidence/)
**Planning branch:** `feature/aseoe-sitemaps-plan`
**Implementation branch:** create only after this plan freezes on `main` — `feature/aseoe-sitemaps`
**Baseline (plan authoring):** `main` @ `4f1f231eca1086db88855d490c536739da69a916`
**Depends on:** A.SEOa–A.SEOd **Complete** (`a-seod-opengraph-complete`); A.1 / ADR-0017; ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 **Accepted**; Integration API v1; TARGET **6**; SB11

**Operational success (Supported):** When Rank Math sitemap is active, the single Rank Math sitemap index and type sitemaps expose language-correct discovery for published/routable languages via official Rank Math filters and SB11 — including language-aware URLs and/or xhtml:link alternates as admitted — without a second sitemap provider, without XML scraping/post-processing, with preview/noindex/unpublished excluded, and with robots.txt ownership preserved.

**This plan is the frozen implementation contract for A.SEOe.** Do not widen Supported admissions without new evidence + ADR where gated. Do not open an implementation branch until this plan is independently reviewed and merged to `main`.

---

## 1. Purpose

Freeze how AIML cooperates with Rank Math (and WordPress when Rank Math sitemap is inactive) for multilingual **XML sitemaps**, **robots/indexability honesty**, and **search-engine discovery** — without becoming a second sitemap provider, scraping XML, or redesigning A.SEOa–d.

A.SEOe does **not**:

- redesign slugs, canonical, hreflang, or SB11
- take ownership of titles, meta descriptions, OpenGraph, or Twitter
- implement SEO diagnostics UI (A.SEOf)
- invent a reusable sitemap/discovery contract when none exists (SE11)
- assume SE candidates are Supported before evidence

A.SEOe **does**:

- inventory sitemap/robots ownership across WP / Woo / Rank Math / AIML
- freeze Supported / Deferred / Unsupported for SE1–SE12
- define work packages ASEOE.0–ASEOE.8 for the admitted set

---

## 2. Preconditions (verified at plan authoring)

| Precondition | Status |
|---|---|
| Working tree clean; `main` == `origin/main` | **Pass** (`4f1f231ec`) |
| A.SEO parent plan + dependency matrix on `main` | **Pass** |
| A.SEOa–A.SEOd Complete + tags including `a-seod-opengraph-complete` | **Pass** |
| SB11 `LanguageRelationshipService` on `main` | **Pass** |
| TARGET = **6** | **Pass** |
| ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 Accepted | **Pass** |
| Integration API v1 present | **Pass** |
| Rank Math sitemap active on inventory host | **Pass** (1.0.275) |
| AIML sitemap/robots product code absent | **Pass** |

If any precondition regresses before coding: **STOP**.

---

## 3. Frozen contracts (carry forward — do not reopen)

| Contract | Rule |
|---|---|
| Parent A.SEO plan + dependency matrix | Authoritative family boundaries |
| A.SEOa | SA7 + SA10; Deferred leaf slugs untouched |
| A.SEOb | Canonical / hreflang / SB11 unchanged |
| A.SEOc | Rank Math title/meta/schema unchanged |
| A.SEOd | Social metadata overlays unchanged |
| ADR-0001 / 0002 / 0007 / 0008 | Overlay; prefix-strip; hash≠identity; preview gates |
| ADR-0013 / 0016 / 0017 | Identity families unchanged |
| Integration API v1 | Unchanged |
| Store / Router / LanguageContext / PreviewService / PluginIdentity | Reuse |
| TARGET | **6** — no bump |

**Forbidden:** new identity family; Store/schema redesign; TARGET bump; second router; A.SEOf scope creep; inventing SE11.

---

## 4. Implementation boundary

A.SEOe owns only:

- sitemap integration
- sitemap language alternates
- sitemap inclusion/exclusion
- robots policy honesty (not a new policy engine)
- search-engine discovery cooperation

It must **not** modify: slugs, canonical, hreflang, titles, descriptions, OpenGraph, Twitter, diagnostics.

---

## 5. Hard ownership rule

A.SEOe must cooperate with the existing sitemap owner.

It must never become an independent sitemap provider unless evidence proves no supported extension point exists.

**Priority:**

1. Official Rank Math sitemap APIs (when Rank Math owns emission — **this site**)
2. Official WordPress sitemap APIs (when WordPress owns emission)
3. Candidate-local deferral

**Unsupported:**

- parallel sitemap generator
- duplicate sitemap index
- XML scraping
- sitemap post-processing / buffered rewrite
- shadow sitemap registry
- second discovery/routing system

---

## 6. Ownership model (frozen)

### Rank Math owns (when sitemap module active)

- Sitemap index and type sitemap XML emission
- Provider registration and pagination
- Image namespace population (`include_images`)
- `Sitemap:` line in `robots.txt`
- Core `wp_sitemaps` disable + redirect

### WordPress / WooCommerce own

- Dynamic `robots.txt` body (when Rank Math custom content empty)
- Woo Disallow rules; shop/product data underlying RM providers

### AIML owns

- Language-aware overlay of admitted sitemap entry/URL/urlset filters using SB11
- Preview / published / noindex honesty gates for discovery overlays
- Bounded validation strategy (SE12) — not A.SEOf diagnostics UI

### Must not

- Register a competing provider that replaces Rank Math index ownership
- Scrape or rewrite Rank Math XML outside official filters
- Mutate Media Library for SE10
- Make noindex/preview content more discoverable

---

## 7. Sitemap lifecycle (frozen)

```text
Rank Math Router serves sitemap_index / {type}-sitemap
  → Providers build entries (default-language locs today)
  → Filters: rank_math/sitemap/entry|url|xml_post_url|{type}_urlset|…
      → AIML A.SEOe overlays (SB11 public relationships)
  → XML output owned by Rank Math Generator
```

Missing Rank Math sitemap module → skip AIML sitemap overlays; optionally evaluate WP `wp_sitemaps` official filters; if unsafe → defer; **never** AIML generator. Never fatal.

---

## 8. Robots / indexability lifecycle (frozen)

| State | Behavior |
|---|---|
| Normal public | Preserve WP + WC + RM `Sitemap:` stack |
| `blog_public=0` | Honesty — do not invent indexability |
| Per-object noindex | Exclude from sitemap overlays; respect RM `include_noindex` default |
| Preview language | Excluded via SB11 |
| Unpublished language | Excluded via SB11 routable/published set |
| Translated URL exists | Must **not** force indexable if source is noindex |

---

## 9. XML namespace policy (frozen)

| Namespace | Owner / policy |
|---|---|
| Default urlset | Rank Math |
| `xmlns:image` | Rank Math Image_Parser — leave; SE10 Deferred for multilingual media |
| `xmlns:xhtml` | Add only via official `{$type}_urlset` when SE3 emits xhtml:link |
| `xmlns:video` / `xmlns:news` | Inactive PRO — Deferred / out of wave |

Do not regex XML, buffer-and-rewrite, inject malformed namespaces, or duplicate namespace declarations.

---

## 10. Deterministic language discovery policy (SE2/SE3/SE4/SE5)

1. Resolve unprefixed object path/URL from Rank Math entry
2. Build SB11 relationships via `for_path(..., false)` (public only)
3. Emit xhtml:link alternates (and/or language-prefixed locs per ASEOE.3 freeze detail) for published languages
4. Skip preview; skip duplicates; skip cross-language guessing
5. Keep document hreflang (A.SEOb) as relationship authority — sitemap must not contradict SB11

Exact emission shape (xhtml-only vs locs+xhtml) is finalized in ASEOE.3 against live XML validity and duplicate rules — both must use official filters only.

---

## 11. SE11 — reusable sitemap/discovery contract

**Deferred.** No `SitemapDiscovery` (or equivalent) contract exists in `src/`. Do not invent one.

If a future wave freezes a contract, it may depend only on A.SEOa–d, Router, LanguageContext, Store, Integration API v1. It must not depend on A.SEOf, and must introduce no circular milestone dependency.

Progression: SB11 (language relationships) → SD12 (social — Deferred) → SE11 (sitemap/discovery — Deferred).

---

## 12. Identity strategy

| Case | Identity |
|---|---|
| Sitemap URL / alternate overlays | **No new Store identity** — runtime from SB11 + Rank Math entry context |
| Robots honesty gates | No identity |
| SE10 media | None (Deferred) |
| SE11 contract | None (Deferred) |

---

## 13. Compatibility / lifecycle

| State | Behavior |
|---|---|
| Rank Math missing / sitemap module off | Skip RM sitemap overlays; WP path only if official and safe; else native; never fatal |
| Required filter missing | Skip that surface |
| Integration disabled | Native Rank Math/WP |
| Cache (RM sitemap cache) | Respect owner invalidation; do not invent AIML sitemap cache subsystem |

---

## 14. Admission matrix (frozen)

See [aseoe-evidence/admission-matrix.md](aseoe-evidence/admission-matrix.md).

| Disposition | IDs |
|---|---|
| **Supported** | SE1, SE2, SE3, SE4, SE5, SE6, SE7, SE8, SE9, SE12 |
| **Deferred** | SE10, SE11 |

---

## 15. Work packages (ASEOE.0–ASEOE.8)

### ASEOE.0 — Baseline + live sitemap inventory

| | |
|---|---|
| **Objective** | Lock Rank Math version, modules, index children, robots.txt, live default-only gap into validation log |
| **Scope** | Docs only |
| **Deps** | Plan freeze on `main` |
| **Likely files** | validation log |
| **Tests** | None beyond inventory notes |
| **Live evidence** | RM 1.0.275; index; page/product/product_cat; no xhtml:link; no `/sv/` locs |
| **Rollback** | Revert docs |
| **Stop** | Rank Math sitemap absent and no safe WP owner path for claimed Supported set |
| **Commit** | `docs(seo): establish A.SEOe baseline` |

### ASEOE.1 — Ownership / admissions freeze

| | |
|---|---|
| **Objective** | Confirm SE dispositions; freeze hooks; forbid second provider; register sitemap hooks on `rankmath` integration |
| **Scope** | Integration wiring plan; Deferred SE10/SE11 guards |
| **Deps** | ASEOE.0 |
| **Likely files** | `src/Integration/RankMath/*`, bridge if needed |
| **Tests** | No `rank_math/sitemap/providers` replacement; inactive skip |
| **Stop** | Requires AIML generator or Store redesign |
| **Commit** | `feat(seo): lock A.SEOe sitemap ownership admissions` |

### ASEOE.2 — Sitemap integration contracts (SE1/SE8)

| | |
|---|---|
| **Objective** | Wire official RM sitemap filters; preserve singular index ownership |
| **Scope** | Hook registration; compatibility gates |
| **Deps** | ASEOE.1 |
| **Likely files** | Rank Math integration |
| **Tests** | Owner singular; hooks present/absent paths |
| **Stop** | Only workable via XML scrape |
| **Commit** | `feat(seo): register A.SEOe Rank Math sitemap hooks` |

### ASEOE.3 — Language URL / alternate contracts (SE2/SE3/SE4/SE5)

| | |
|---|---|
| **Objective** | SB11-driven language locs and/or xhtml:link; published only; preview excluded |
| **Scope** | `entry` / `url` / `urlset` / `xml_post_url` as needed |
| **Deps** | ASEOE.2 |
| **Likely files** | Rank Math integration; maybe small pure helper (no new identity) |
| **Tests** | EN/SV reciprocity; preview absent; duplicates=0; SB11 agreement |
| **Live evidence** | Before: default-only; After: language discovery present |
| **Stop** | Namespace cannot be safely extended via official urlset filter |
| **Commit** | `feat(seo): implement A.SEOe sitemap language discovery` |

### ASEOE.4 — Robots / indexability contracts (SE6/SE7)

| | |
|---|---|
| **Objective** | Preserve robots stack; noindex honesty; never increase indexability |
| **Scope** | Gates in sitemap overlays; no AIML robots.txt replacement |
| **Deps** | ASEOE.3 |
| **Tests** | noindex excluded; blog_public honesty; Sitemap: line retained |
| **Stop** | Requires annexing Rank Math robots meta as AIML DB |
| **Commit** | `feat(seo): implement A.SEOe sitemap indexability gates` |

### ASEOE.5 — Woo / media / namespace admissions (SE9; SE10 Deferred)

| | |
|---|---|
| **Objective** | Product + product_cat language discovery; confirm SE10 Deferred; xmlns policy |
| **Scope** | Same filters on product types; no second Woo provider; no media mutation |
| **Deps** | ASEOE.3–4 |
| **Tests** | Product EN/SV; product_cat; Deferred attachment guard |
| **Commit** | `feat(seo): extend A.SEOe sitemap overlays to Woo surfaces` |

### ASEOE.6 — Platform / lifecycle / compatibility

| | |
|---|---|
| **Objective** | RM missing/inactive; module off; integration disabled; cache honesty |
| **Deps** | ASEOE.2–5 |
| **Tests** | Never fatal; skip overlays |
| **Commit** | `feat(seo): harden A.SEOe sitemap lifecycle guards` |

### ASEOE.7 — Acceptance / crawl / regression / performance (SE12)

| | |
|---|---|
| **Objective** | Full suites; live index/XML/robots; A.SEOa–d regression; observe performance |
| **Deps** | ASEOE.6 |
| **Tests** | Unit + integration + PluginGuard + PHPCS |
| **Live evidence** | sitemap index; page/product XML; robots.txt; EN/SV |
| **Commit** | validation evidence updates |

### ASEOE.8 — Documentation closure

| | |
|---|---|
| **Objective** | Factual status on plan, validation log, roadmaps; final SE dispositions |
| **Deps** | ASEOE.7 |
| **Commit** | `docs(seo): close A.SEOe Sitemap integration` |

---

## 16. Architectural acceptance criteria

1. TARGET remains **6**.
2. Store schema unchanged.
3. Integration API v1 unchanged.
4. No new identity family.
5. Rank Math remains sole sitemap XML owner when module active.
6. AIML-added sitemap providers that replace RM index = **0**.
7. Duplicate sitemap indexes = **0**.
8. No XML scrape / buffered post-processing rewrite.
9. No shadow sitemap registry.
10. Official Rank Math sitemap hooks only (RM-active path).
11. SB11 consumed unchanged.
12. A.SEOa URL identity unchanged.
13. A.SEOb canonical/hreflang unchanged.
14. A.SEOc title/meta unchanged.
15. A.SEOd social overlays unchanged.
16. SE1 Supported — singular RM generation preserved.
17. SE2 Supported — language-aware discovery URLs via official filters.
18. SE3 Supported — alternates via official urlset/url seams + SB11.
19. SE4 Supported — published/routable only.
20. SE5 Supported — preview excluded.
21. SE6 Supported — robots stack preserved; no AIML robots engine.
22. SE7 Supported — noindex honesty; never more indexable via translation.
23. SE8 Supported — overlays without replacing ownership.
24. SE9 Supported — product/product_cat without second Woo provider.
25. SE10 remains Deferred.
26. SE11 remains Deferred — no invented discovery contract.
27. SE12 Supported — bounded validation without A.SEOf UI.
28. `xmlns:xhtml` only when emitting xhtml:link.
29. Image NS left to Rank Math; no multilingual media annexation.
30. Video/news PRO modules not claimed.
31. Sitemap locs/alternates do not contradict SB11 document relationships.
32. Duplicate `<loc>` entries introduced by AIML = **0**.
33. Duplicate xhtml alternates = **0**.
34. Malformed XML = **0**.
35. Duplicate namespace declarations = **0**.
36. `Sitemap:` robots directive remains single RM index URL.
37. `/wp-sitemap.xml` continues to defer to RM (owner behavior).
38. FP = **0**.
39. Language leakage = **0**.
40. Incorrect alternate locales/URLs = **0**.
41. EN↔SV reciprocal discovery where both published.
42. Page + product live acceptance.
43. product_cat covered when present in index.
44. Unit suite green.
45. Integration suite green.
46. PluginGuard pass.
47. PHPCS pass.
48. `git diff --check` clean.
49. A.SEOf not started.
50. Performance observed only — no invented cache subsystem.
51. `/sv/` home 301-loop not “fixed” inside A.SEOe; recorded if it affects crawl.
52. Rank Math inactive → never fatal.
53. Required hook missing → skip surface.
54. Integration disabled → native RM/WP.
55. Validation log records baseline + final dispositions.
56. Roadmap pointers factual — no milestone renumbering.
57. Implementation boundary (discovery only) preserved.
58. Hard ownership rule preserved.

---

## 17. Stop conditions

**Candidate-local defer** if: no official seam; namespace cannot be safely extended; media/robots ownership ambiguous; safe SB11 mapping unproven.

**Milestone STOP** if meaningful support requires: Store/schema/TARGET bump; new identity family; second sitemap provider; XML scrape/post-processing; shadow registry; SB11 redesign; A.SEOa–d redesign; Integration API redesign; persistent discovery subsystem.

Ordinary defects: fix, test, continue.

---

## 18. ADR assessment

**No new ADR required** for Supported SE1–SE9/SE12 if Implementation stays on Integration API v1 + Rank Math official sitemap filters + SB11 + existing LanguageContext/Router.

Do not reopen ADR-0001, 0002, 0008, 0017.

---

## 19. Out of scope (reminder)

- A.SEOf diagnostics
- SE10/SE11 implementation
- Translated leaf slugs / rewrite bases
- Fixing pre-existing `/sv/` front-page redirect loop
- PRODUCT_PRIORITIES edits unless factual status strictly requires

---

## 20. Architecture verdict

A.SEOe is ready to freeze as **Architecture Frozen (planning)** with evidence-driven SE admissions. Rank Math owns sitemap emission; AIML overlays language discovery via official filters and SB11. Implementation is authorized only after this plan merges to `main`. Implementation must not begin A.SEOf.

---

## Document control

| Version | Date | Notes |
|---|---|---|
| 0.1 | 2026-08-09 | Planning freeze authoring on `feature/aseoe-sitemaps-plan`; baseline `4f1f231ec`; Rank Math 1.0.275 sitemap inventory; SE1–SE12 dispositions frozen for review |
