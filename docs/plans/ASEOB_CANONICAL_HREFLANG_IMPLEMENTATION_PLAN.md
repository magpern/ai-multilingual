# A.SEOb — Canonical URLs, hreflang & Language Relationships — Implementation Plan

**Status:** **Implementation Complete — Ready for Independent Review** — on `feature/aseob-canonical-hreflang`; Supported SB1–SB11; **not** merged / **not** tagged  
**Milestone:** Program A — **A.SEOb** (second wave of A.SEO)  
**Plan freeze:** Evidence-driven admissions SB1–SB11; language graph frozen for A.SEOc–A.SEOf consumers; ADR-0002/0008 preserved; TARGET **6**; Supported = **SB1–SB11**  
**ADR assessment:** **No new ADR required** for the Supported set. Do not reopen ADR-0002 / ADR-0008. Deferred topics (persistent relationship tables, translated leaf URLs) remain ADR-gated under A.SEOa / future ADRs.  
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — §6.3 / A.SEO family  
**Parent architecture:** [ASEO_PARENT_IMPLEMENTATION_PLAN.md](ASEO_PARENT_IMPLEMENTATION_PLAN.md) (authoritative; not redesigned)  
**Dependency matrix:** [A_SEO_DEPENDENCY_MATRIX.md](A_SEO_DEPENDENCY_MATRIX.md)  
**Evidence:** [aseob-evidence/](aseob-evidence/)  
**Planning branch:** `feature/aseob-canonical-hreflang-plan`  
**Implementation branch:** create only after this plan freezes on `main` — `feature/aseob-canonical-hreflang`  
**Baseline (plan authoring):** `main` @ `a1e91f4429428cac166db5e72c892734b4587b5c`  
**Depends on:** A.SEOa **Complete** (`a-seoa-slugs-permalinks-complete`; Supported SA7/SA10); ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 **Accepted**; Integration API v1; TARGET **6**

**Operational success (Supported):** Published-language documents expose a single language-aware canonical URL and a reciprocal hreflang set (including `x-default`) derived from a reusable SB11 language-relationship contract built on SA7 URLs; preview languages excluded; Rank Math/WP cooperation without scraping; blind `redirect_canonical` suppress replaced by language-preserving policy.

**This plan is the frozen implementation contract for A.SEOb.** Do not widen Supported admissions without new evidence + ADR where gated. Do not open an implementation branch until this plan is independently reviewed and merged to `main`.

---

## 1. Purpose

Freeze the **multilingual SEO relationship layer** after investigation: ownership, canonical policy, hreflang, `x-default`, alternate discovery, and a reusable language-relationship contract for downstream SEO waves.

A.SEOb does **not**:

- implement A.SEOc–A.SEOf emission work
- reopen A.SEOa URL identity or translated leaf-slug Deferred set
- introduce Store/schema/TARGET changes
- invent a second router, URL-history DB, or scrape-based discovery
- assume candidates are Supported before evidence

A.SEOb **does**:

- inventory WP / Woo / Rank Math / theme / Elementor / AIML ownership
- freeze Supported vs Deferred vs Unsupported for SB1–SB11
- freeze the SB11 contract consumed unchanged by A.SEOc–A.SEOf
- define work packages for the Supported set

---

## 2. Preconditions (verified at plan authoring)

| Precondition | Status |
|---|---|
| Working tree clean; `main` == `origin/main` | **Pass** (`a1e91f442`) |
| A.SEO parent plan on `main` | **Pass** |
| `A_SEO_DEPENDENCY_MATRIX.md` on `main` | **Pass** |
| A.SEOa Complete + tag `a-seoa-slugs-permalinks-complete` | **Pass** |
| No prior `ASEOB_*` plan / `aseob-evidence/` | **Pass** |
| TARGET = **6** | **Pass** |
| ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 Accepted | **Pass** |
| Integration API v1 present | **Pass** |

If any precondition regresses before coding: **STOP**.

---

## 3. Frozen contracts (carry forward — do not reopen)

| Contract | Rule |
|---|---|
| Parent A.SEO plan + dependency matrix | Authoritative family boundaries |
| A.SEOa | URL identity = SA7 + SA10; SA1–SA6/SA8/SA9 Deferred |
| ADR-0001 | Overlay only |
| ADR-0002 | Prefix-strip routing; translated rewrite bases Deferred |
| ADR-0007 | Hash ≠ identity |
| ADR-0008 | Preview capability-gated; no fallback chains; preview excluded from public SEO discovery |
| ADR-0013 / 0016 / 0017 | Identity families unchanged |
| Integration API v1 | Unchanged |
| Store / Workspace / Review / TM / Glossary / Jobs / Diagnostics / Router / LanguageContext / PreviewService | Reuse |
| TARGET | **6** — no bump |

**Forbidden:**

- new identity family / URL / path / rewrite identity
- second routing engine
- persistent URL-history / relationship DB / redirect registry without ADR
- HTML scraping / fuzzy matching / URL guessing
- circular dependencies on A.SEOc–A.SEOf
- duplicate SEO ownership / annexation of Rank Math or Woo meta as translation store

---

## 4. Hard rails

- Integration API v1, TARGET 6, existing Store / Router / LanguageContext / PreviewService
- No Store redesign, schema redesign, new identity family, second routing engine
- WordPress / Woo / Rank Math remain foreign owners of their surfaces
- AIML overlays only through official integration points
- Do not invent a subsystem merely because it appears convenient

---

## 5. Language relationship contract (SB11)

A.SEOb is the canonical place to define how alternate-language relationships are represented.

### 5.1 Investigation outcome

Primitives exist (Languages::routable, LanguageContext, SA7 URL rules, Switcher path logic), but **no** stable reusable contract exists today. Switcher is UI-coupled.

**Disposition:** **Supported** — freeze a lightweight **read-only** language-relationship contract implementable without Store/schema/TARGET/Integration-API/identity changes. See [relationship-service-analysis.md](aseob-evidence/relationship-service-analysis.md).

### 5.2 Contract shape (frozen)

A small AIML service (implementation name free) returns ordered relationship records for a document/request:

- `language_code`, `hreflang` (BCP47), `url` (absolute SA7), `is_default`, `is_current`
- Public SEO mode: **published languages only** (preview excluded)

### 5.3 Downstream dependency rule

The language-relationship contract frozen by A.SEOb must be self-contained.

It may depend on:

- A.SEOa
- Router
- LanguageContext
- Store
- existing Integration API v1

It must **not** depend on implementation details from A.SEOc, A.SEOd, A.SEOe, or A.SEOf.

Those later waves are consumers of the contract only.

Circular dependencies between SEO waves are not permitted. Do not defer this contract to “decide in A.SEOc.”

A.SEOc–A.SEOf must consume this contract **unchanged** rather than independently rebuilding language relationships.

---

## 6. Ownership model

Summarized from [ownership-inventory.md](aseob-evidence/ownership-inventory.md):

| Surface | Owner | AIML role |
|---|---|---|
| Permalinks / rewrites | WordPress (+ Woo structures) | Consume SA7 |
| `redirect_canonical` | WordPress | Language-preserving policy overlay |
| Canonical tag (Rank Math active) | Rank Math | Filter to SB11 current URL; respect overrides (SB2) |
| Canonical tag (Rank Math inactive) | WordPress | Overlay via `get_canonical_url` / `rel_canonical` |
| Document hreflang | AIML | Emit from SB11 |
| Title/meta/schema | Rank Math | Out of scope → A.SEOc |
| OG/Twitter | Rank Math | Out of scope → A.SEOd |
| Sitemaps | Rank Math | Out of scope → A.SEOe (consumes SB11) |
| Storefront `get_canonical_url` | biopentra-storefront | Foreign — do not steal |
| Elementor / Blocksy | Body / chrome | No SEO relationship ownership |

---

## 7. Investigation summary

| Area | Finding |
|---|---|
| Live canonical tags | Often omitted under sitewide noindex; Rank Math owns emission path when indexable |
| Live hreflang | Absent; Switcher UI attrs only |
| Blind suppress | Still present; must become language-aware policy |
| SA7 | Sufficient URL identity for relationship graph |
| SB11 | Lightweight contract Supported; consumer-proven for A.SEOc–A.SEOf |

---

## 8. Admission matrix

Authoritative detail: [admission-matrix.md](aseob-evidence/admission-matrix.md).

| ID | Topic | Disposition |
|---|---|---|
| SB1 | Canonical URL generation | **Supported** |
| SB2 | Canonical override policy | **Supported** |
| SB3 | hreflang generation | **Supported** |
| SB4 | x-default policy | **Supported** |
| SB5 | Alternate language discovery | **Supported** |
| SB6 | Cross-language URL relationships | **Supported** |
| SB7 | Language availability policy | **Supported** |
| SB8 | Canonical / hreflang interaction | **Supported** |
| SB9 | Preview exclusion | **Supported** |
| SB10 | Language relationship validation | **Supported** (tests/guards; diagnostics UI → A.SEOf) |
| SB11 | Canonical reusable language-relationship contract consumed unchanged by A.SEOc–A.SEOf | **Supported** |

**Supported set (frozen):** SB1–SB11.

---

## 9. Canonical lifecycle

1. Resolve current published language (LanguageContext) and source object / unprefixed path (WP).
2. Compute SB11 relationships.
3. Canonical URL = current language’s SB11 `url`, unless SB2 foreign override applies.
4. Emit/overlay via Rank Math filter **or** WP canonical path — never both competing tags.
5. `redirect_canonical`: allow only language-preserving corrections; never strip prefix to unprefixed equivalent; no loops.

---

## 10. hreflang lifecycle

1. Build SB11 published set (SB5/SB7/SB9).
2. Emit `rel="alternate" hreflang="{bcp47}"` for each record.
3. Emit `hreflang="x-default"` → default language URL (SB4).
4. Ensure reciprocity across the set (SB8/SB10).
5. Preview languages never appear.

---

## 11. Language relationship lifecycle

1. Discover published languages.
2. Map each to SA7 absolute URL for the same source path/object.
3. Expose via SB11 contract to emitters and future consumers.
4. Validate reciprocity / orphans / duplicates (SB10) without guessing URLs.

---

## 12. Identity decisions

| Decision | Verdict |
|---|---|
| New identity family for URLs/relationships | **Forbidden** |
| Store segment keys for relationship rows | **Not required** under SA7 |
| PluginIdentity additions | **None** in A.SEOb |
| Relationship persistence table | **Deferred** (ADR) — not Supported |

---

## 13. Platform reuse

Reuse Workspace / Review / TM / Glossary / Jobs / Diagnostics infrastructure unchanged. A.SEOb does not add translation units for canonical/hreflang strings. Diagnostics **UI** for SEO health remains A.SEOf but may call SB11.

---

## 14. Work packages (ASEOB.0–ASEOB.8)

Planning defines these packages for the future **implementation** branch. This planning branch is docs-only.

### ASEOB.0 — Baseline

| | |
|---|---|
| **Objective** | Open validation log; confirm TARGET 6, ADRs, A.SEOa Complete, Supported=SB1–SB11 |
| **Scope** | Docs + baseline checks |
| **Deps** | This plan on `main` |
| **Likely files** | `docs/plans/ASEOB_*_VALIDATION_LOG.md` |
| **Validation** | Unit/integration/PluginGuard/PHPCS baseline green |
| **Stop** | TARGET ≠ 6; plan missing; A.SEOa incomplete |
| **Commit** | `docs(seo): establish A.SEOb baseline` |

### ASEOB.1 — Ownership freeze / inventory lock

| | |
|---|---|
| **Objective** | Lock `aseob-evidence/*` as implementation inputs; confirm no ownership drift |
| **Scope** | Evidence re-check |
| **Deps** | ASEOB.0 |
| **Stop** | Evidence contradicts Supported set |
| **Commit** | `docs(seo): lock A.SEOb evidence inventories` |

### ASEOB.2 — Canonical architecture (SB1/SB2/SB8 redirect policy)

| | |
|---|---|
| **Objective** | Language-aware canonical generation + override policy + language-preserving `redirect_canonical` |
| **Scope** | Router policy + WP/Rank Math canonical filters |
| **Deps** | ASEOB.1; SB11 available or co-delivered |
| **Likely files** | `src/Routing/Router.php`, new SEO head/canonical collaborator under `src/` |
| **Validation** | EN/SV canonical correctness; no double tags; no prefix strip |
| **Stop** | Requires scrape or Store redesign |
| **Commit** | `feat(seo): implement A.SEOb canonical generation` |

### ASEOB.3 — hreflang architecture (SB3/SB4)

| | |
|---|---|
| **Objective** | Document hreflang + x-default emission from SB11 |
| **Scope** | `wp_head` emission; reciprocity |
| **Deps** | ASEOB.2 / SB11 |
| **Validation** | Full alternate set; x-default; preview absent |
| **Stop** | Duplicate conflicting Rank Math hreflang without cooperation rule |
| **Commit** | `feat(seo): implement A.SEOb hreflang generation` |

### ASEOB.4 — Language relationship contract (SB5–SB7/SB9/SB11)

| | |
|---|---|
| **Objective** | Implement SB11 service; wire discovery/availability/preview exclusion |
| **Scope** | New lightweight service; optional Switcher refactor to consume SB11 (UX unchanged) |
| **Deps** | ASEOB.1 |
| **Validation** | Consumer-style contract tests; no TARGET/schema change |
| **Stop** | Circular dependency on A.SEOc–f; new identity family |
| **Commit** | `feat(seo): implement A.SEOb language relationship contract` |

### ASEOB.5 — Deferred guardrails (SB10 + Deferred)

| | |
|---|---|
| **Objective** | Prove Deferred surfaces remain inactive; reciprocity/duplicate/orphan guards |
| **Scope** | Tests/docs |
| **Deps** | ASEOB.2–4 |
| **Stop** | Accidental leaf-slug reverse map / history table |
| **Commit** | `test(seo): guard A.SEOb Deferred admissions` |

### ASEOB.6 — Platform reuse confirmation

| | |
|---|---|
| **Objective** | Confirm Workspace/Diagnostics/Review/TM/Glossary/Jobs unchanged |
| **Scope** | Audit + light tests |
| **Deps** | ASEOB.5 |
| **Commit** | `test(seo): confirm A.SEOb platform reuse` |

### ASEOB.7 — Acceptance

| | |
|---|---|
| **Objective** | Full validation matrix ([validation-strategy.md](aseob-evidence/validation-strategy.md)) |
| **Scope** | Unit, integration, PluginGuard, PHPCS, live EN/SV, regressions |
| **Deps** | ASEOB.6 |
| **Validation** | FP=0; leakage=0; reciprocity; A.SEOa/Gutenberg/Elementor/Woo/Fluent/A.6 green |
| **Commit** | `test(seo): accept A.SEOb Supported surfaces` |

### ASEOB.8 — Closure

| | |
|---|---|
| **Objective** | Docs status Complete on feature branch; roadmap pointers; ready for review |
| **Scope** | Docs only |
| **Deps** | ASEOB.7 PASS |
| **Commit** | `docs(seo): close A.SEOb Canonical & hreflang` |

---

## 15. Architectural acceptance criteria

1. Supported set is exactly SB1–SB11 unless amended with evidence + ADR where gated.
2. Deferred leaf-slug / history / A.SEOc–e emission topics remain Deferred.
3. TARGET remains 6.
4. No Store redesign; no schema migration in A.SEOb.
5. No new identity family; no PluginIdentity additions required for SB11.
6. Integration API v1 unchanged.
7. ADR-0002 prefix-strip preserved; no translated rewrite bases.
8. ADR-0008 preview exclusion preserved for public SEO graph.
9. SB11 depends only on A.SEOa / Router / LanguageContext / Store / Integration API v1.
10. SB11 does not depend on A.SEOc–A.SEOf.
11. No circular SEO-wave dependencies.
12. A.SEOc–A.SEOf consume SB11 unchanged.
13. Default language URLs remain unprefixed (SA7).
14. Non-default URLs remain prefixed (SA7).
15. Canonical for current language equals SB11 current URL (absent SB2 override).
16. SB2 respects foreign Rank Math / storefront overrides without guessing.
17. At most one document canonical tag emitter path active (cooperate, don’t duplicate).
18. Document hreflang emitted for every published language in the set.
19. `x-default` points at default language absolute URL.
20. Hreflang reciprocity holds across the published set.
21. Preview languages never appear in public hreflang.
22. Preview capability gating (SA10) unchanged.
23. No HTML scraping.
24. No fuzzy URL matching / URL guessing.
25. No reverse translated-slug lookup.
26. No URL-history / relationship persistence table.
27. No second router.
28. `redirect_canonical` never strips language prefix to unprefixed equivalent.
29. No redirect loops introduced.
30. Woo product singular EN/SV canonical+hreflang correct under SA7.
31. Rank Math active path covered by tests.
32. Rank Math inactive / WP path covered by tests.
33. Switcher UX may consume SB11 but must not own the SEO contract.
34. Workspace / Review / TM / Glossary / Jobs unchanged.
35. Diagnostics UI not required in A.SEOb (A.SEOf).
36. FP = 0 on Supported surfaces.
37. Language leakage = 0 on Supported surfaces.
38. Duplicate conflicting hreflang sets = 0 under AIML emission.
39. Orphan published language without resolvable SA7 URL fails closed (no invent).
40. Query-string policy: do not encode language in query; do not invent archives.
41. Admin / login / REST exclusions for relationship emission as applicable.
42. External absolute URLs not rewritten into the graph.
43. A.SEOa SA7/SA10 regression green.
44. Gutenberg regression green.
45. Elementor regression green.
46. Woo A.7a–A.7d regression green.
47. Fluent Forms A.8 regression green.
48. A.6 visitor chrome regression green.
49. Performance: no relationship caching subsystem without ADR; observe head overhead only.
50. If any Supported item cannot complete inside frozen architecture: **STOP** — do not invent a redesign.
51. Planning branch contains docs only (this freeze).
52. Implementation starts only after plan merge to `main`.

---

## 16. Stop conditions

STOP implementation if architecture would require:

- Store redesign or TARGET bump
- new identity family
- second routing engine
- HTML scraping as primary mechanism
- reverse translated-slug map / URL-history DB
- translated rewrite bases (reopen ADR-0002)
- depending on A.SEOc–A.SEOf to define SB11
- breaking Integration API v1
- annexing Rank Math / Woo persistence as translation store
- silently implementing A.SEOa Deferred leaf-slug scope

Ordinary bugs: fix, test, continue. Architecture violation: STOP and report.

---

## 17. ADR assessment

**No new ADR required** for Supported SB1–SB11 under SA7 source-leaf URLs + official WP/Rank Math hooks + read-only SB11 service.

Future ADRs remain required before Supporting: persistent relationship/history tables; translated leaf URLs in the graph (A.SEOa SA1+ gates).

---

## 18. Out of scope

- A.SEOc Rank Math title/meta/schema
- A.SEOd OpenGraph/Twitter field overlays beyond consuming SB11 URLs
- A.SEOe sitemap emission (must consume SB11 later)
- A.SEOf diagnostics UI
- Translated leaf slugs / rewrite bases / URL-history registry
- Product priority reordering

---

## 19. Architecture verdict

**Implementation Complete — Ready for Independent Review.**

Supported set {SB1–SB11} is implemented inside existing contracts with `LanguageRelationshipService` (SB11), `DocumentSeoHead`, and language-preserving `redirect_canonical`. Downstream SEO waves must consume SB11 unchanged.

---

## Document control

| Item | Value |
|---|---|
| Canonical path | `docs/plans/ASEOB_CANONICAL_HREFLANG_IMPLEMENTATION_PLAN.md` |
| Evidence | `docs/plans/aseob-evidence/` |
| Planning branch | `feature/aseob-canonical-hreflang-plan` |
| Planning merge | Merged to `main` |
| Implementation branch | `feature/aseob-canonical-hreflang` |
| Validation log | `docs/plans/ASEOB_CANONICAL_HREFLANG_VALIDATION_LOG.md` |
| Merge / tag | Not yet — independent review required; recommended tag `a-seob-canonical-hreflang-complete` |
| Baseline | `a1e91f4429428cac166db5e72c892734b4587b5c` |
