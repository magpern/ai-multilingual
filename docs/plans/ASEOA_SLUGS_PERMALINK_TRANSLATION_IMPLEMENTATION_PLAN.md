# A.SEOa — Slugs & Permalink Translation — Implementation Plan

**Status:** **Architecture Frozen (planning)** — freeze merged to `main`; implementation authorized for SA7/SA10 only; not started
**Milestone:** Program A — **A.SEOa** (first wave of A.SEO)
**Plan freeze:** Evidence-driven admissions SA1–SA10; ADR-0002 prefix-strip preserved; translated rewrite bases Deferred; no URL-history DB / redirect registry / second router; TARGET **6**; Supported = **SA7**, **SA10** only
**ADR assessment:** **No new ADR required** for the Supported set (SA7/SA10). Focused ADRs are **required before** any future Support of SA1–SA6/SA8/SA9 (see §8). Do not silently reopen ADR-0002.
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — §6.3 / A.SEO family
**Parent architecture:** [ASEO_PARENT_IMPLEMENTATION_PLAN.md](ASEO_PARENT_IMPLEMENTATION_PLAN.md) (authoritative; not redesigned)
**Dependency matrix:** [A_SEO_DEPENDENCY_MATRIX.md](A_SEO_DEPENDENCY_MATRIX.md)
**Evidence:** [aseoa-evidence/](aseoa-evidence/)
**Planning branch:** `feature/aseoa-slugs-permalinks-plan` (merged)
**Implementation branch:** create only after this plan freezes on `main` — `feature/aseoa-slugs-permalinks`
**Baseline (plan authoring):** `main` @ `d8375f37abb6e5ce337a866ebd07dd5f960677e3`
**Depends on:** A.SEO parent freeze on `main`; ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 **Accepted**; Integration API v1; TARGET **6**

**Operational success (this freeze):** Language-aware permalink generation and preview URL routing remain correct under ADR-0002/0008 without inventing translated leaf-slug resolution, rewrite-base translation, or a URL-history subsystem. Deferred candidates stay Deferred until focused ADRs land.

**This plan is the frozen implementation contract for A.SEOa.** Do not implement production code on the planning branch. Do not widen Supported admissions without new evidence + ADR where gated.

---

## 1. Purpose

Freeze the **A.SEOa URL architecture** after investigation: ownership, routing, rewrites, collisions, migration, identity, and evidence-based admissions.

A.SEOa does **not**:

- implement A.SEOb–A.SEOf
- reopen the parent SEO architecture
- translate rewrite bases
- introduce a URL-history database or redirect registry
- invent a second router or uniqueness engine
- assume candidates are Supported before evidence

A.SEOa **does**:

- inventory WP / Woo / AIML ownership and routing
- freeze Supported vs Deferred vs Unsupported for SA1–SA10
- define work packages for the Supported set
- record ADR gates for Deferred candidates

---

## 2. Preconditions (verified at plan authoring)

| Precondition | Status |
|---|---|
| Working tree clean; `main` == `origin/main` | **Pass** (`d8375f37a`) |
| A.SEO parent plan on `main` | **Pass** |
| `A_SEO_DEPENDENCY_MATRIX.md` on `main` | **Pass** |
| No prior `ASEOA_*` plan / `aseoa-evidence/` | **Pass** |
| TARGET = **6** | **Pass** |
| ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 Accepted | **Pass** |
| Integration API v1 present | **Pass** |

If any precondition regresses before coding: **STOP**.

---

## 3. Frozen contracts (carry forward — do not reopen)

| Contract | Rule |
|---|---|
| Parent A.SEO plan + dependency matrix | Authoritative family boundaries |
| ADR-0001 | Overlay only; never mutate WP/Woo tables as translation store |
| ADR-0002 | Prefix-strip routing; **translated rewrite bases Deferred** |
| ADR-0007 | Hash ≠ identity |
| ADR-0008 | Preview capability-gated; no fallback chains |
| ADR-0013 / 0016 / 0017 | Identity families unchanged |
| Integration API v1 | Unchanged |
| Store / Workspace / Review / TM / Glossary / Jobs / Diagnostics / Router / LanguageContext | Reuse |
| TARGET | **6** — no bump in this plan |

**Forbidden:**

- new identity family / URL / path / rewrite identity / routing serializer
- second routing engine
- persistent URL-history DB, redirect registry, rewrite engine, or routing cache without ADR
- HTML scraping
- fuzzy URL matching / URL guessing
- duplicate ownership
- Store / schema redesign

---

## 4. Ownership model (from evidence)

| Party | Owns |
|---|---|
| **WordPress** | `post_name`, term slugs, rewrite rules, permalink generation, `redirect_canonical` baseline, `wp_unique_post_slug` / `wp_unique_term_slug` |
| **WooCommerce** | Product permalink structures, taxonomy rewrite structures / bases |
| **AIML** | Language prefix strip/prefix (Router), translated slug **overlays if/when admitted**, language-aware URL generation/lookup when admitted, redirect **policy** within stop conditions, diagnostics |
| **Elementor / Blocksy** | No canonical URL ownership (verified) |

---

## 5. Investigation summary

Full evidence: [aseoa-evidence/](aseoa-evidence/).

| Topic | Finding |
|---|---|
| Routing | Prefix-strip then WP resolves **source** slugs; `redirect_canonical` suppressed when prefixed |
| Store | `FORMAT_SLUG` exists; no slug segments; reads are object-keyed only — **no reverse `translated_text` lookup** |
| Schema | No `slugs` / redirect-history table (classic ROADMAP only); TARGET 6 |
| Rewrite bases | Translating bases reopens ADR-0002 → Deferred |
| Collisions | WP uniqueness is source-only; translated uniqueness needs index/registry → ADR |
| Migration | WP `_wp_old_slug` does not track Store overlays; history registry forbidden without ADR |

---

## 6. Admission matrix (evidence-based)

Canonical detail: [aseoa-evidence/admission-matrix.md](aseoa-evidence/admission-matrix.md).

| ID | Candidate | Disposition |
|---|---|---|
| SA1 | Translated post slugs | **Deferred** (ADR: reverse resolution + uniqueness) |
| SA2 | Translated page slugs | **Deferred** (same as SA1) |
| SA3 | Translated product slugs | **Deferred** (same as SA1; bases still Deferred) |
| SA4 | Translated taxonomy slugs | **Deferred** (no `SOURCE_TERM` + ADR) |
| SA5 | Slug uniqueness | **Deferred** (depends on SA1 ADR) |
| SA6 | Historical redirects | **Deferred** (URL-history ADR) |
| SA7 | Language-aware permalink generation | **Supported** |
| SA8 | Reserved words | **Deferred** (depends on SA1 ADR) |
| SA9 | Collision handling | **Deferred** (depends on SA1 ADR) |
| SA10 | Preview URLs | **Supported** |

---

## 7. Redirect / rewrite / collision policies (frozen)

### Redirect

- No chains; no heuristic language guessing; no arbitrary cross-language redirects.
- No new global redirect registry / URL-history DB in A.SEOa.
- Keep Router prefix-loop protection until A.SEOb replaces it with correct language-aware canonical policy.
- SA6 remains Deferred.

### Rewrite

- No AIML rewrite rules; no runtime rewrite rebuilding.
- Translated rewrite bases (`product`, `category`, `tag`, `author`, `feed`, pagination, …) remain **Deferred**.

### Collision

- Document and reuse WordPress / WooCommerce source-slug behavior.
- Do not invent an AIML uniqueness engine in this wave.
- Translated-slug collision safety waits on SA1 ADR.

---

## 8. ADR gates (Deferred — not blockers for Supported freeze)

| Future ADR topic | Unblocks |
|---|---|
| Reverse slug resolution + uniqueness under overlay model (justify TARGET/index if needed) | SA1, SA2, SA3, SA5, SA8, SA9 |
| Term slug identity without ownership theft | SA4 |
| URL-history / redirect registry **or** proven existing-owner reuse | SA6 |
| Reopen ADR-0002 | Translated rewrite bases only |

**No new ADR required** to implement SA7/SA10 under this plan.

---

## 9. Identity / Store / platform reuse

| Decision | Freeze |
|---|---|
| Identity | No new family. Hypothetical future `post_name` units would use `SOURCE_POST` + `field_key=post_name` + `FORMAT_SLUG` — **not admitted end-to-end now** |
| Store | Reuse unchanged; no new source type; TARGET 6 |
| Platform | Store, Workspace, Suggestions, Review, TM, Glossary, Jobs, Diagnostics, Router, LanguageContext |
| PluginIdentity | No additions in Supported scope |

---

## 10. URL lifecycle (Supported scope)

For **SA7 / SA10** (source paths + language prefix):

| Stage | Owner | Behavior |
|---|---|---|
| URL generation | AIML Router + WP `get_permalink` | Prefix when translated context |
| Preview | PreviewService + ADR-0008 | Capability-gated |
| Lookup | WP after prefix strip | Source slug match |
| Failure | — | Source path / WP 404 — no guessing |

Lifecycle stages that require translated leaf slugs or history (publish/rename/restore translated slug, historical inbound) are **out of Supported scope** — see Deferred + [url-migration-analysis.md](aseoa-evidence/url-migration-analysis.md).

---

## 11. Work packages (ASEOA.0–ASEOA.8)

Commit after each package on the implementation branch. Planning branch is docs-only.

### ASEOA.0 — Baseline

| | |
|---|---|
| **Objective** | Open validation log; confirm TARGET 6, ADRs, parent freeze; record Supported={SA7,SA10} |
| **Scope** | Docs + baseline checks |
| **Deps** | This plan on `main` |
| **Likely files** | `docs/plans/ASEOA_*_VALIDATION_LOG.md` |
| **Validation** | Unit/integration/PluginGuard/PHPCS baseline green |
| **Rollback** | Revert docs commit |
| **Stop** | TARGET ≠ 6; parent missing; attempt to code SA1–SA6 |
| **Commit** | `docs(seo): establish A.SEOa baseline` |

### ASEOA.1 — Inventory lock

| | |
|---|---|
| **Objective** | Lock evidence inventories as implementation inputs |
| **Scope** | Confirm `aseoa-evidence/*` unchanged in meaning; note any env drift |
| **Deps** | ASEOA.0 |
| **Likely files** | `docs/plans/aseoa-evidence/*` (editorial only if drift) |
| **Validation** | Evidence links resolve |
| **Stop** | Evidence contradicts Supported set |
| **Commit** | `docs(seo): lock A.SEOa evidence inventories` |

### ASEOA.2 — SA7 contract tests

| | |
|---|---|
| **Objective** | Failing-first / characterizing tests for language-aware permalink generation |
| **Scope** | Tests around Router `filter_home_url`, LanguageContext, default unprefixed |
| **Deps** | ASEOA.1 |
| **Likely files** | `tests/unit/Routing/*`, existing Router tests |
| **Validation** | EN unprefixed; SV prefixed; admin/login/REST exclusions |
| **Stop** | Requires second router |
| **Commit** | `test(seo): characterize A.SEOa SA7 permalink generation` |

### ASEOA.3 — SA10 contract tests

| | |
|---|---|
| **Objective** | Characterize preview URL capability gating |
| **Scope** | PreviewService + LanguageResolver preview rules |
| **Deps** | ASEOA.2 |
| **Likely files** | `tests/unit/**/Preview*`, LanguageResolver tests |
| **Validation** | Preview routable only with capability; published unchanged |
| **Stop** | Preview exposed publicly |
| **Commit** | `test(seo): characterize A.SEOa SA10 preview URLs` |

### ASEOA.4 — SA7 hardening (only if gaps)

| | |
|---|---|
| **Objective** | Close any SA7 gaps vs frozen contract without adding translated leaf slugs |
| **Scope** | Minimal Router/`home_url` fixes if tests prove gaps |
| **Deps** | ASEOA.2 |
| **Likely files** | `src/Routing/Router.php` |
| **Validation** | ASEOA.2 green; no rewrite rules added |
| **Stop** | Fix would require slug reverse map |
| **Commit** | `fix(seo): harden A.SEOa SA7 language-aware permalinks` |

### ASEOA.5 — SA10 hardening (only if gaps)

| | |
|---|---|
| **Objective** | Close SA10 gaps vs ADR-0008 |
| **Scope** | PreviewService / resolver only |
| **Deps** | ASEOA.3 |
| **Likely files** | `src/Workspace/PreviewService.php`, LanguageResolver |
| **Validation** | ASEOA.3 green |
| **Stop** | Public preview leakage |
| **Commit** | `fix(seo): harden A.SEOa SA10 preview URL gates` |

### ASEOA.6 — Deferred guardrails

| | |
|---|---|
| **Objective** | Ensure Deferred candidates remain untouched; document ADR gates in validation log |
| **Scope** | Tests/docs asserting no slug reverse lookup API, no history table, no rewrite bases |
| **Deps** | ASEOA.4–5 |
| **Likely files** | tests + validation log |
| **Validation** | No `SOURCE_TERM`; no slugs table; Router registers zero rewrite rules |
| **Stop** | Accidental SA1 implementation |
| **Commit** | `test(seo): guard A.SEOa Deferred slug admissions` |

### ASEOA.7 — Acceptance

| | |
|---|---|
| **Objective** | Full validation matrix for Supported set + regressions |
| **Scope** | Unit, integration, PluginGuard, PHPCS, EN/SV permalink/preview checks |
| **Deps** | ASEOA.6 |
| **Validation** | FP=0 / leakage=0 on Supported surfaces; Gutenberg/Elementor/Woo/Fluent regression |
| **Stop** | Regression fail |
| **Commit** | `test(seo): accept A.SEOa Supported surfaces` |

### ASEOA.8 — Closure

| | |
|---|---|
| **Objective** | Validation log PASS; roadmap pointer update; merge-ready |
| **Scope** | Docs status only |
| **Deps** | ASEOA.7 |
| **Rollback** | Revert closure docs |
| **Stop** | Validation incomplete |
| **Commit** | `docs(seo): close A.SEOa Slugs & Permalink Translation` |

---

## 12. Architectural acceptance criteria

1. A.SEOa admissions are evidence-based (SA1–SA10 started as Candidate).
2. Supported set is exactly SA7 and SA10 unless a future ADR + plan amendment changes it.
3. SA1–SA6 and SA8–SA9 remain Deferred under this freeze.
4. ADR-0001 overlay model is preserved.
5. ADR-0002 prefix-strip routing is preserved.
6. Translated rewrite bases are Deferred.
7. ADR-0007 hash semantics unchanged.
8. ADR-0008 preview gating preserved for SA10.
9. No new identity family.
10. No URL/path/rewrite identity.
11. No routing serializer.
12. Store reused without new source type.
13. TARGET remains 6.
14. Integration API v1 unchanged.
15. No second routing engine.
16. No persistent URL-history database.
17. No redirect registry.
18. No rewrite engine / AIML rewrite rules.
19. No routing cache subsystem.
20. No HTML scraping.
21. No fuzzy URL matching.
22. No URL guessing / heuristic cross-language redirects.
23. No redirect chains.
24. WordPress owns `post_name` persistence.
25. WordPress owns term slug persistence.
26. WordPress owns rewrite rules and permalink generation.
27. WordPress owns `redirect_canonical` baseline.
28. WooCommerce owns product permalink structures.
29. WooCommerce owns taxonomy rewrite structures.
30. Elementor has no SEO URL ownership.
31. Blocksy has no SEO URL ownership.
32. AIML owns language prefix strip/prefix only within Router.
33. SA7 builds on existing `home_url` / `get_permalink` behavior.
34. SA10 builds on PreviewService + LanguageResolver.
35. Inbound resolution continues to use source slugs after prefix strip.
36. Store cannot be claimed to reverse-resolve by `translated_text` without ADR.
37. Collision policy documents WP/Woo behavior; no AIML uniqueness engine in this wave.
38. Migration policy forbids invented URL history in this wave.
39. Platform reuse: Store, Workspace, Suggestions, Review, TM, Glossary, Jobs, Diagnostics, Router, LanguageContext.
40. No parallel SEO / slug pipeline.
41. Deferred candidates must not be coded in ASEOA.0–8.
42. EN default URLs remain unprefixed.
43. SV translated-context URLs are prefixed.
44. Preview languages are not public routes.
45. FP = 0 on Supported surfaces.
46. Language leakage = 0 on Supported surfaces.
47. Gutenberg regression protected.
48. Elementor regression protected.
49. WooCommerce regression protected.
50. Fluent Forms regression protected.
51. A.SEOb–A.SEOf remain out of scope.
52. Stop conditions in §13 are binding.

---

## 13. Stop conditions

**STOP** (and do not redesign) if work requires:

- Store redesign
- schema / TARGET bump
- new identity family
- translated rewrite bases
- second routing engine
- persistent URL-history subsystem
- custom redirect registry
- HTML scraping
- fuzzy matching
- duplicate ownership
- breaking ADR-0002
- breaking Integration API v1
- implementing Deferred SA1–SA6/SA8/SA9 without the gated ADR

---

## 14. Out of scope

- A.SEOb canonical/hreflang
- A.SEOc Rank Math meta
- A.SEOd social metadata
- A.SEOe sitemaps/robots
- A.SEOf SEO diagnostics product
- Attachment slug translation
- Product Priorities edits

---

## 15. Architecture verdict

**Architecture Frozen (planning).**

Supported set {SA7, SA10} is implementable inside existing contracts.
End-to-end translated leaf slugs and historical redirects are **honestly Deferred** with explicit ADR gates — not silently Supported.

---

## Document control

| Item | Value |
|---|---|
| Canonical path | `docs/plans/ASEOA_SLUGS_PERMALINK_TRANSLATION_IMPLEMENTATION_PLAN.md` |
| Evidence | `docs/plans/aseoa-evidence/` |
| Planning branch | `feature/aseoa-slugs-permalinks-plan` |
| Implementation | Not started |
