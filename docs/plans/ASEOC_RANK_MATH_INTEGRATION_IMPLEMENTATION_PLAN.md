# A.SEOc — Rank Math Integration — Implementation Plan

**Status:** **Implementation Complete — Ready for Independent Review** — on `feature/aseoc-rankmath`; Supported SC1–SC6/SC10–SC14; Partially Supported SC7–SC9; **not** merged / **not** tagged
**Milestone:** Program A — **A.SEOc** (third wave of A.SEO)  
**Plan freeze:** Evidence-driven admissions SC1–SC14; Rank Math remains foreign owner; AIML overlays via official filters only; SB11 consumed unchanged; TARGET **6**; Supported = **SC1–SC6, SC10–SC14**; Partially Supported = **SC7–SC9**  
**ADR assessment:** **No new ADR required** for the Supported / Partially Supported set if Implementation uses Integration API v1 + PluginIdentity `p:rankmath:…` + existing Store overlays. Do not reopen ADR-0001 / 0002 / 0008 / 0017. Do not change A.SEOa URL contracts or A.SEOb SB11.  
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — §6.3 / A.SEO family  
**Parent architecture:** [ASEO_PARENT_IMPLEMENTATION_PLAN.md](ASEO_PARENT_IMPLEMENTATION_PLAN.md) (authoritative; not redesigned)  
**Dependency matrix:** [A_SEO_DEPENDENCY_MATRIX.md](A_SEO_DEPENDENCY_MATRIX.md)  
**Evidence:** [aseoc-evidence/](aseoc-evidence/)  
**Planning branch:** `feature/aseoc-rankmath-plan`  
**Implementation branch:** create only after this plan freezes on `main` — `feature/aseoc-rankmath`  
**Baseline (plan authoring):** `main` @ `488e62f930bce4a08cb22059e8d963ec4a805d23`  
**Depends on:** A.SEOa **Complete** (`a-seoa-slugs-permalinks-complete`); A.SEOb **Complete** (`a-seob-canonical-hreflang-complete`; SB11); A.1 / ADR-0017; ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 **Accepted**; Integration API v1; TARGET **6**

**Operational success (Supported):** When Rank Math is active, published-language documents expose language-correct SEO titles and meta descriptions for admitted explicit Rank Math fields (posts/pages/products/terms) via official Rank Math filters; template-only titles/descriptions inherit already-translated content tokens without duplicate identity; bounded schema textual properties may align to the same sources; SB11 / canonical / hreflang unchanged; Rank Math missing/inactive degrades safely; preview metadata never becomes public indexable variants.

**This plan is the frozen implementation contract for A.SEOc.** Do not widen Supported admissions without new evidence + ADR where gated. Do not open an implementation branch until this plan is independently reviewed and merged to `main`.

---

## 1. Purpose

Freeze how AIML cooperates with Rank Math for multilingual SEO **titles**, **meta descriptions**, **bounded schema text**, and **compatibility lifecycle** — without annexing Rank Math, scraping HTML, or redesigning A.SEOa/A.SEOb URL/relationship contracts.

A.SEOc does **not**:

- redesign canonical URLs, hreflang, or SB11 (A.SEOb)
- implement translated leaf slugs (A.SEOa Deferred)
- implement OpenGraph / Twitter overlays (A.SEOd)
- implement sitemaps / robots / indexability product (A.SEOe)
- implement SEO diagnostics UI (A.SEOf)
- assume SC candidates are Supported before evidence

A.SEOc **does**:

- inventory Rank Math version, modules, persistence, filters, tokens, schema, Woo/tax surfaces
- freeze Supported / Partially Supported / Deferred / Unsupported for SC1–SC14
- freeze deterministic title/description/token/schema policies
- define work packages ASEOC.0–ASEOC.8 for the admitted set

---

## 2. Preconditions (verified at plan authoring)

| Precondition | Status |
|---|---|
| Working tree clean; `main` == `origin/main` | **Pass** (`488e62f93`) |
| A.SEO parent plan on `main` | **Pass** |
| `A_SEO_DEPENDENCY_MATRIX.md` on `main` | **Pass** |
| A.SEOa Complete + tag `a-seoa-slugs-permalinks-complete` | **Pass** |
| A.SEOb Complete + tag `a-seob-canonical-hreflang-complete` | **Pass** |
| SB11 `LanguageRelationshipService` on `main` | **Pass** |
| TARGET = **6** | **Pass** |
| ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 Accepted | **Pass** |
| Integration API v1 present | **Pass** |
| Rank Math active on live inventory host | **Pass** (1.0.275) |

If any precondition regresses before coding: **STOP**.

---

## 3. Frozen contracts (carry forward — do not reopen)

| Contract | Rule |
|---|---|
| Parent A.SEO plan + dependency matrix | Authoritative family boundaries |
| A.SEOa | URL identity = SA7 + SA10; Deferred leaf slugs untouched |
| A.SEOb | Canonical / hreflang / SB11 unchanged; AIML remains hreflang owner |
| ADR-0001 | Overlay only |
| ADR-0002 | Prefix-strip routing; translated rewrite bases Deferred |
| ADR-0007 | Hash ≠ identity |
| ADR-0008 | Preview capability-gated; no fallback chains; preview excluded from public SEO discovery |
| ADR-0013 / 0016 / 0017 | Identity families unchanged; `p:` via PluginIdentity when needed |
| Integration API v1 | Unchanged |
| Store / Workspace / Review / TM / Glossary / Jobs / Diagnostics / LanguageContext / PreviewService | Reuse |
| TARGET | **6** — no bump |

**Forbidden:**

- new identity family beyond ADR-0017 `p:`
- Rank Math meta/options as AIML translation persistence
- HTML scraping / final-head rewriting
- second SEO emission pipeline competing with Rank Math
- SB11 / A.SEOa URL / A.SEOb canonical redesign
- Store/schema redesign or TARGET bump
- A.SEOd–A.SEOf scope creep

---

## 4. Hard rails

- Official Rank Math filters/APIs only (`rank_math/frontend/title`, `…/description`, schema entity filters, etc.)
- Rank Math remains foreign owner of configuration, source metadata, token expansion, and emission pipeline
- AIML owns Store overlays, language selection, Workspace/TM/Jobs workflow, bounded diagnostics signals
- Prefer candidate-local deferral over architectural redesign
- Sitewide `noindex` may omit HTML canonical; validate title/description via hooks/head text honestly

---

## 5. Ownership model (frozen)

### Rank Math owns

- SEO configuration and templates (`rank-math-options-titles`, modules)
- Source metadata (`rank_math_title`, `rank_math_description`, term equivalents)
- Template/token expansion (`Helper::replace_vars` / `Replacer`)
- Frontend emission (`pre_get_document_title`, head, JSON-LD)
- Schema generation where the rich-snippet module is active

### AIML owns

- Translations of **admitted** Rank Math-owned source strings in AIML Store
- LanguageContext-gated overlay application
- Integration registration / lifecycle compatibility
- Workspace extraction/suggestion/review for admitted fields
- Consumption of SB11 unchanged

### Must not

- Annex Rank Math tables as TM
- Emit duplicate `<title>` / meta description tags
- Assume `document_title_parts` works when Rank Math is active (it does not)

---

## 6. Deterministic title / description policy (frozen)

**Critical question answered by evidence:** Rank Math frontend filters run on **already token-expanded** strings. Core `document_title_parts` is bypassed.

**Policy:**

1. **Explicit Rank Math fields** (non-empty `rank_math_title` / `rank_math_description` on posts/products; equivalent term meta) are the primary Supported translation sources.  
2. Source identity is the **stable stored field string**. Prefer fields with **no** Rank Math `%…%` variables. Token-bearing custom fields are candidate-local **Deferred/Partial** unless implementation can prove safe expansion without unstable identity.  
3. Overlay applies on `rank_math/frontend/title` and `rank_math/frontend/description` when LanguageContext is translated and a Store hit exists.  
4. **Template-only** documents (meta absent): Rank Math expands `%title%` / `%excerpt%` / `%term%` / … — AIML must **not** create a second SEO identity for the interpolated result. Content-token correctness is cooperation (SC7/SC8), not duplicate translation.  
5. Empty Store / missing Rank Math / inactive module → **native Rank Math/WP behavior** (safe fallback).  
6. Never translate final SERP strings solely because they appear in HTML head if they are unstable composites of runtime tokens.

---

## 7. Template / token policy (SC7/SC8 — Partially Supported)

| Token class | Action |
|---|---|
| Content-owned (`%title%`, `%excerpt%`, `%term%`, `%term_description%`, `%wc_shortdesc%`) | No second identity; ensure expansion sees translated values where those surfaces already overlay |
| Separators / literals (`%sep%`) | Leave |
| Branding / machine (`%sitename%`, `%sitedesc%`, `%wc_price%`, `%wc_sku%`) | Do not admit via SEO title/description identity in A.SEOc |
| `%seo_title%` / `%seo_description%` | Avoid recursive double overlay; Rank Math helper semantics preserved |

Do not translate option template strings themselves as Store units.

---

## 8. Schema policy (SC9 — Partially Supported)

Admit only textual properties that:

- are visitor-language dependent  
- align to admitted SEO title/description sources (or identical Rank Math snippet name/desc derived from them)  
- are filterable via `rank_math/snippet/rich_snippet_entity` (preferred) and/or careful `rank_math/json_ld` mutation  

**Never translate:** IDs, URLs, prices, ratings, SKUs, dates, identifiers, opaque plugin nodes.  
URLs/`@id` remain A.SEOa/A.SEOb. OG/Twitter → A.SEOd. Sitemaps → A.SEOe.  
`inLanguage` already tracks AIML — do not break it.

---

## 9. SB11 consumption (SC11)

Consume `LanguageRelationshipService` unchanged for language relationship / preview exclusion context as needed.

Do not reimplement alternates, canonical, or hreflang. If a candidate requires SB11 changes: **STOP that candidate**.

---

## 10. Identity strategy (frozen preference)

**Preferred:** Integration API v1 + `PluginIdentity` keys shaped like:

`p:rankmath:{owner_type}:{owner_id}:{field}`

Examples (illustrative, not pre-frozen exact vocabulary beyond ADR-0017 rules):

- `p:rankmath:post:{ID}:title`  
- `p:rankmath:post:{ID}:description`  
- `p:rankmath:term:{term_id}:title`  
- `p:rankmath:term:{term_id}:description`  

Products use `post` owner_type with product IDs (CPT `product`) unless implementation evidence shows a clearer owner_type already used by Woo integrations — **no new identity family**.

**Reuse existing content units** when Rank Math only echoes already-owned `%title%` / `%excerpt%` values.

**No duplicate identity** for the same semantic value.

Exact key spelling is finalized in ASEOC.1 against PluginIdentity length/charset rules; planning does not invent a second serializer.

---

## 11. Preview policy (SC12)

Respect ADR-0008, SA10, SB9:

- Translator preview metadata only within authorized preview contract  
- Preview languages never become publicly indexable metadata variants  
- Public SEO overlays use published languages only  

---

## 12. Compatibility / lifecycle (SC10 / SC13)

| State | Behavior |
|---|---|
| Rank Math missing / inactive | No Rank Math filters; existing WP title path remains; never fatal |
| Unsupported / unexpected Rank Math version | Degrade to native; diagnostics signal later (A.SEOf) |
| Required filter missing | Skip overlay |
| Module disabled (e.g. rich-snippet) | Skip schema partials; titles/descriptions still attempt if frontend paper exists |
| Integration disabled | Native Rank Math |
| Explicit field empty | Template/token path; no explicit SEO Store unit |
| Generated template only | SC7/SC8 cooperation; no explicit-field overlay |
| Field / content title / taxonomy / product changed | Existing stale/hash conventions (ADR-0007); source remains Rank Math meta |
| Plugin disable/reactivate | Store retained; overlays resume when Rank Math + integration active |
| Sitewide noindex (`blog_public=0`) | Title/description overlays still apply; canonical HTML may be omitted — not a failure |

**Unsafe state:** return source / Rank Math native. **Never fatal.**

---

## 13. Admission matrix (frozen)

See [aseoc-evidence/admission-matrix.md](aseoc-evidence/admission-matrix.md).

| Disposition | IDs |
|---|---|
| **Supported** | SC1, SC2, SC3, SC4, SC5, SC6, SC10, SC11, SC12, SC13, SC14 |
| **Partially Supported** | SC7, SC8, SC9 |
| Deferred / out of wave | OG/Twitter (A.SEOd), sitemaps/robots product (A.SEOe), diagnostics UI (A.SEOf), translated templates as identity, machine schema values |

---

## 14. Work packages (ASEOC.0–ASEOC.8)

### ASEOC.0 — Baseline + Rank Math inventory

| | |
|---|---|
| **Objective** | Lock live Rank Math version/modules/options/meta samples into validation log |
| **Scope** | Docs + inventory confirmation only |
| **Deps** | Plan freeze on `main` |
| **Likely files** | validation log; evidence pointers |
| **Tests** | None required beyond inventory scripts/notes |
| **Live evidence** | Version 1.0.275; modules; EN/SV title gap |
| **Rollback** | Revert docs |
| **Stop** | Rank Math absent and no alternate official SEO owner path for claimed surfaces |
| **Commit** | `docs(seo): establish A.SEOc baseline` |

### ASEOC.1 — Ownership / admission freeze

| | |
|---|---|
| **Objective** | Confirm SC dispositions; freeze identity key patterns; register integration scaffold plan |
| **Scope** | Integration id `rankmath` (or equivalent), PluginIdentity fields, extractor allowlist |
| **Deps** | ASEOC.0 |
| **Likely files** | `src/Integration/RankMath/*` (future), Plugin wiring |
| **Tests** | Identity round-trip unit tests |
| **Stop** | Requires new identity family or Store redesign |
| **Commit** | `feat(seo): lock A.SEOc Rank Math ownership admissions` |

### ASEOC.2 — Title contracts (SC1/SC3/SC5)

| | |
|---|---|
| **Objective** | Extract/overlay explicit Rank Math titles for post/page/product/term |
| **Scope** | `rank_math/frontend/title`; Store units for explicit meta only |
| **Deps** | ASEOC.1 |
| **Likely files** | Rank Math bridge; Extractor/Workspace registration |
| **Tests** | EN/SV title overlay; template-only no duplicate identity; Rank Math inactive path |
| **Live evidence** | Product filled title; fixture template title |
| **Rollback** | Disable integration |
| **Stop** | Only workable via HTML scrape |
| **Commit** | `feat(seo): implement A.SEOc Rank Math title overlays` |

### ASEOC.3 — Description contracts (SC2/SC4/SC6)

| | |
|---|---|
| **Objective** | Same as titles for descriptions |
| **Scope** | `rank_math/frontend/description` |
| **Deps** | ASEOC.1 |
| **Likely files** | Same bridge |
| **Tests** | Explicit vs `%excerpt%` paths |
| **Stop** | Annexation of Rank Math meta required |
| **Commit** | `feat(seo): implement A.SEOc Rank Math description overlays` |

### ASEOC.4 — Template/token + SB11 cooperation (SC7/SC8/SC11)

| | |
|---|---|
| **Objective** | Guards and minimal cooperation so content tokens inherit translations; SB11 unchanged |
| **Scope** | Token policy tests; optional `rank_math/vars/*` only if `%title%` inheritance fails evidence |
| **Deps** | ASEOC.2–3 |
| **Tests** | No duplicate identity for template-only; SB11 API unchanged |
| **Stop** | Requires SB11 change |
| **Commit** | `feat(seo): cooperate A.SEOc Rank Math tokens with SB11` |

### ASEOC.5 — Schema admissions (SC9)

| | |
|---|---|
| **Objective** | Bounded name/description schema text alignment |
| **Scope** | Entity filters only; machine values untouched |
| **Deps** | ASEOC.2–3 |
| **Tests** | Schema name/description follow overlays; prices/URLs untouched |
| **Stop** | Opaque graph requires redesign |
| **Commit** | `feat(seo): implement A.SEOc Rank Math schema text overlays` |

### ASEOC.6 — Platform / lifecycle / compatibility (SC10/SC12/SC13)

| | |
|---|---|
| **Objective** | Missing Rank Math, preview gates, stale/fallback, noindex honesty |
| **Scope** | Lifecycle tests + PreviewService respect |
| **Deps** | ASEOC.2–5 |
| **Tests** | Never fatal; preview not public; empty Store → native |
| **Commit** | `feat(seo): harden A.SEOc Rank Math lifecycle` |

### ASEOC.7 — Full acceptance / regression / performance (SC14)

| | |
|---|---|
| **Objective** | Full matrix green |
| **Scope** | Unit, integration, PluginGuard, PHPCS, live EN/SV head, A.SEOa/b + A.6/A.7/A.8 regression |
| **Deps** | ASEOC.6 |
| **Validation** | FP=0; leakage=0; duplicate ownership=0; duplicate title tags=0 |
| **Stop** | Regression fail |
| **Commit** | `test(seo): accept A.SEOc Supported surfaces` |

### ASEOC.8 — Documentation / admission closure

| | |
|---|---|
| **Objective** | Validation log PASS; roadmap pointer; merge-ready |
| **Scope** | Docs status only |
| **Deps** | ASEOC.7 |
| **Commit** | `docs(seo): close A.SEOc Rank Math integration` |

---

## 15. Architectural acceptance criteria

### Ownership (1–8)

1. Rank Math remains foreign owner of SEO configuration and source metadata.  
2. AIML does not annex Rank Math postmeta/termmeta/options as translation persistence.  
3. AIML does not emit a second competing `<title>` or meta description tag.  
4. Overlays use official Rank Math filters/APIs only.  
5. No HTML scraping of Rank Math head.  
6. No final rendered-head rewriting.  
7. Canonical ownership remains A.SEOb + Rank Math cooperation unchanged.  
8. Hreflang ownership remains AIML A.SEOb unchanged.

### Architecture (9–20)

9. TARGET remains **6**.  
10. Store schema unchanged (no Rank-Math-specific tables).  
11. Integration API v1 unchanged.  
12. No new identity family; only ADR-0017 `p:` keys if needed.  
13. SB11 consumed unchanged.  
14. A.SEOa SA7/SA10 URL contracts unchanged.  
15. No second SEO pipeline / Rank Math rewrite.  
16. No second router.  
17. No translated rewrite bases.  
18. No URL-history DB / redirect registry.  
19. Workspace / TM / Glossary / Jobs / Review reused.  
20. Diagnostics UI not required in A.SEOc (A.SEOf).

### Titles / descriptions (21–36)

21. SC1 Supported for explicit post/page SEO titles.  
22. SC2 Supported for explicit post/page SEO descriptions.  
23. SC3 Supported for explicit product SEO titles.  
24. SC4 Supported for explicit product SEO descriptions.  
25. SC5 Supported for explicit taxonomy SEO titles.  
26. SC6 Supported for explicit taxonomy SEO descriptions.  
27. EN/SV acceptance required for Supported explicit-field documents.  
28. Template-only documents do not create duplicate SEO identities for interpolated strings.  
29. Token expansion order remains Rank Math’s (expand then filter overlay for explicit fields).  
30. `%title%` / `%excerpt%` / `%term%` content tokens are not double-translated.  
31. Token-bearing custom Rank Math fields without proven stable identity are deferred candidate-locally.  
32. Empty Store falls back to Rank Math native output.  
33. Source Rank Math field updates follow existing stale/hash conventions.  
34. Rank Math inactive → WP/AIML existing title paths remain safe (never fatal).  
35. `document_title_parts`-only implementations are insufficient when Rank Math is active.  
36. Sanitization preserves Rank Math-safe plain text for title/description overlays.

### Schema (37–42)

37. SC9 admits only bounded textual name/description-like properties.  
38. Machine values (price, SKU, rating, dates, IDs) remain untouched.  
39. Schema URLs/`@id` remain A.SEOa/A.SEOb concerns.  
40. No broad “schema translated” claim.  
41. `inLanguage` language-awareness preserved.  
42. Schema overlays must not duplicate conflicting text beyond admitted policy.

### Preview / quality (43–50)

43. Preview metadata respects ADR-0008 / SA10 / SB9.  
44. Preview languages are not public SEO metadata variants.  
45. FP = 0 on Supported surfaces.  
46. Language leakage = 0.  
47. Duplicate metadata ownership = 0.  
48. Duplicate title/meta tag emission = 0 where Rank Math owns emission.  
49. Sitewide noindex does not falsely fail title/description validation.  
50. Public overlays use published languages only.

### Regression / validation (51–58)

51. A.SEOa permalink/preview regressions pass.  
52. A.SEOb canonical/hreflang/SB11 regressions pass.  
53. Gutenberg leaf overlays still resolve.  
54. Elementor admitted overlays still resolve.  
55. Woo A.7* visitor overlays still resolve.  
56. Fluent Forms A.8 overlays still resolve.  
57. A.6 chrome regressions pass.  
58. Unit + integration + PluginGuard + PHPCS + live head + Rank Math hook/API validation required for closure.

### Compatibility (59–60)

59. Missing filter/module/integration disabled → native Rank Math/WP, never fatal.  
60. A.SEOd/A.SEOe/A.SEOf remain out of implementation scope for this wave.

---

## 16. Stop conditions

### Candidate-local defer if

- no official Rank Math seam  
- unstable generated identity (token-heavy custom fields)  
- duplicate ownership with existing content units  
- schema node opaque/dynamic  
- safe sanitization cannot be proven  

### Milestone architecture STOP if meaningful A.SEOc support requires

- Store redesign / TARGET bump  
- Integration API redesign  
- new SEO persistence subsystem  
- changing SB11  
- changing A.SEOa URL contract  
- changing A.SEOb canonical/hreflang ownership  
- HTML scraping  
- final rendered-head rewriting  
- mutating Rank Math source metadata as AIML translation storage  

---

## 17. ADR assessment

**No new ADR required** for the frozen Supported / Partially Supported set under Integration API v1 + PluginIdentity + Rank Math official filters + existing Store.

If implementation discovers a required public ownership/persistence contract beyond ADR-0017, document a focused ADR need and **STOP** coding that surface until authorized. Do not author that ADR in the planning task.

---

## 18. Out of scope (reminder)

- A.SEOd OpenGraph / Twitter  
- A.SEOe sitemaps / robots / indexability product  
- A.SEOf diagnostics UI  
- Translated leafs / rewrite bases / URL history  
- Product priority reordering  

---

## 19. Architecture verdict

**Implementation Complete — Ready for Independent Review.**

Supported {SC1–SC6, SC10–SC14} and Partially Supported {SC7–SC9} are implemented under Integration API v1 + PluginIdentity `p:rankmath:…` + official Rank Math filters. SB11 unchanged. A.SEOd has not been started.

---

## Document control

| Item | Value |
|---|---|
| Canonical path | `docs/plans/ASEOC_RANK_MATH_INTEGRATION_IMPLEMENTATION_PLAN.md` |
| Evidence | `docs/plans/aseoc-evidence/` |
| Planning branch | `feature/aseoc-rankmath-plan` |
| Implementation branch | `feature/aseoc-rankmath` (not created) |
| Validation log | create at implementation time |
| Baseline | `488e62f930bce4a08cb22059e8d963ec4a805d23` |
