# A.SEO — Parent SEO Architecture — Implementation Plan

**Status:** **Complete** — waves A.SEOa–A.SEOf complete/merged/tagged; family closed (`a-seof-seo-diagnostics-complete`)
**Milestone family:** Program A — **A.SEO** Visitor SEO adapters
**Plan freeze:** Canonical SEO architecture for waves **A.SEOa–A.SEOf**; overlay-not-duplication; reuse Store / Workspace / Review / TM / Glossary / Jobs / Integration API v1 / LanguageContext / Diagnostics / PluginIdentity; no parallel SEO subsystem; no HTML scraping; TARGET **6**
**ADR assessment:** **No new ADR required at plan freeze** if waves stay within ADR-0001 / ADR-0002 / ADR-0007 / ADR-0008 / ADR-0013 / ADR-0016 / ADR-0017 / ADR-0018 + Integration API v1. A wave that needs a new identity family, Store redesign, schema redesign, second translation pipeline, or reopening ADR-0002 (translated rewrite bases) must open a focused ADR **before** coding — not silently.
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — Program A — §6.3 (wave boundaries refined by this plan)
**Canonical dependency matrix:** [A_SEO_DEPENDENCY_MATRIX.md](A_SEO_DEPENDENCY_MATRIX.md)
**Planning branch:** `feature/aseo-parent-plan` (merged)
**Implementation branch:** create **per wave** after dedicated wave plan freeze (first child planning: `feature/aseoa-slugs-permalinks-plan`)
**Baseline (plan authoring):** `main` @ `48985be3395c8e9baa99260d80395e044584a18d`
**Freeze merge:** `main` @ plan-freeze merge `merge: complete A.SEO parent architecture plan freeze`
**Depends on:** P1; A.R1/A.2/A.3; A.R2/A.4; A.1; A.0; A.8 — complete/tagged where applicable; ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 **Accepted**; schema TARGET **6**; Integration API v1 unchanged
**Related:** [INTEGRATION_API_V1.md](../INTEGRATION_API_V1.md); [adr/0002-prefix-strip-routing.md](../adr/0002-prefix-strip-routing.md); [adr/0008-language-state-model.md](../adr/0008-language-state-model.md); [adr/0017-plugin-integration-framework-ownership-and-identity.md](../adr/0017-plugin-integration-framework-ownership-and-identity.md); [PRODUCT_PRIORITIES.md](../PRODUCT_PRIORITIES.md); [aseo-evidence/](aseo-evidence/)

**Operational success:** Merchants can expose a correctly language-aware, crawlable, Rank Math–cooperative SEO surface for published languages through the existing AIML platform, without cloning posts, inventing a second translation pipeline, or scraping HTML.

**This plan is the family architecture contract for A.SEO (waves A.SEOa–A.SEOf).** Do not implement production code on the planning branch. First coding starts only after architecture freeze on `main` and a dedicated per-wave implementation branch. This document freezes milestone boundaries and ownership — not detailed work packages beyond those boundaries.

---

## 1. Purpose

Define the **canonical SEO architecture** that every later SEO implementation milestone must follow.

A.SEO does **not**:

- redesign WordPress, WooCommerce, or Rank Math
- own foreign SEO persistence as a translation store
- scrape rendered HTML for titles, meta, canonicals, or sitemaps
- reopen frozen ADRs for convenience
- renumber Program A milestones
- implement SEO in this planning milestone

A.SEO **does**:

- freeze ownership for URL, metadata, discovery, and validation surfaces
- freeze wave boundaries **A.SEOa–A.SEOf**
- freeze platform reuse and stop conditions
- freeze a shared validation philosophy
- point later waves at the dependency matrix as the sequencing authority

---

## 2. Preconditions (verified at plan authoring)

| Precondition | Status |
|---|---|
| P1 Platform Stabilization complete | **Pass** |
| A.R1 / A.2 / A.3 Elementor path complete | **Pass** |
| A.R2 / A.4 Nested Gutenberg complete | **Pass** |
| A.1 Integration API v1 complete | **Pass** (`a1-plugin-integration-framework-complete`) |
| A.0 Gutenberg Leaf Expansion complete | **Pass** (`a0-gutenberg-leaf-expansion-complete`) |
| A.8 Fluent Forms first bridge complete | **Pass** (`a8-fluentforms-contact-integration-complete`) |
| ADR-0001 **Accepted** | **Pass** |
| ADR-0002 **Accepted** | **Pass** |
| ADR-0007 **Accepted** | **Pass** |
| ADR-0008 **Accepted** | **Pass** |
| ADR-0013 **Accepted** | **Pass** |
| ADR-0016 **Accepted** | **Pass** |
| ADR-0017 **Accepted** | **Pass** |
| ADR-0018 **Accepted** | **Pass** |
| Migrator `TARGET` = **6** | **Pass** |
| Integration API v1 unchanged | **Pass** |
| No existing `docs/plans/ASEO*` / `A_SEO*` plan | **Pass** |
| `main` clean @ `48985be33…` | **Pass** |

If any precondition regresses before a wave starts coding: **STOP**.

---

## 3. Goals

1. Freeze the A.SEO family as six architecture waves (**A.SEOa–A.SEOf**) with clear ownership and boundaries.
2. Keep WordPress / WooCommerce / Rank Math as foreign owners of their persistence and emission surfaces; AIML overlays only.
3. Reuse Store, Workspace, Review, TM, Glossary, Jobs, Diagnostics, PluginIdentity, and LanguageContext unchanged.
4. Preserve prefix-strip routing (ADR-0002) and the three-state language model (ADR-0008).
5. Fail closed: missing/unsupported/ambiguous SEO surface → source / safe non-index behavior as defined per wave — never invent URLs or metadata.
6. Prevent a parallel SEO subsystem, second translation pipeline, or HTML-scraping strategy.
7. Make later wave plans inherit this architecture without redefining URL or ownership contracts.

---

## 4. Frozen contracts (carry forward — do not reopen)

| Contract | Role |
|---|---|
| ADR-0001 | Overlay architecture / canonical ownership boundaries |
| ADR-0002 | Prefix-strip routing; no rewrite-rule duplication; translated rewrite bases reopen this ADR |
| ADR-0007 | Hash semantics — freshness/integrity, not identity |
| ADR-0008 | Three-state language model; preview excluded from hreflang/sitemaps; no fallback chains until SEO policy exists |
| ADR-0013 | Gutenberg segment identity (`b:`) |
| ADR-0016 | Elementor identity and ownership (`e:`) |
| ADR-0017 | Plugin Integration Framework ownership + `p:` identity |
| ADR-0018 | Woo order transactional language context (not visitor SEO; pattern only) |
| Integration API v1 | Typed plugin integrations; `PluginIdentity` |
| Schema TARGET | **6** — no bump for A.SEO family plan |

Preserve subsystems: Store, Workspace, Suggestions, Review, TM, Glossary, Jobs, Gutenberg extract/render, Elementor extract/overlay, Fluent Forms A.8 bridge, Router / LanguageContext.

**Forbidden family-wide:**

- second translation pipeline
- Store / schema redesign
- new identity family without ADR
- HTML scraping / unscoped output buffering / DOM rewrite as primary strategy
- fuzzy identity / path identity / URL guessing
- mutating `wp_posts` / `wp_postmeta` / Rank Math / Woo tables as a translation mechanism
- parallel SEO product outside AIML platform reuse
- duplicate ownership of the same SEO string under two identities
- reopening ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 for convenience

---

## 5. Ownership model (frozen)

Only the **real owner** may produce translation units for a surface. AIML never annexes foreign persistence.

| Party | Owns | AIML role |
|---|---|---|
| **WordPress core** | `post_name` / term slug persistence, rewrite rules, permalink generation, `redirect_canonical` baseline, core robots behavior | Overlay translated slug values where admitted; language-aware URL generation/lookup; redirect policy; never rewrite WP tables as translation store |
| **WooCommerce** | Product permalink structures, product taxonomy rewrite structures, shop page / endpoint URL shapes | Reuse WP/Woo ownership; overlay admitted visitor SEO-related strings only via existing integration paths — no Woo persistence mutation |
| **Rank Math** | SEO title / meta description persistence and emission when active; much schema / social / sitemap emission when configured | Consume/cooperate via official filters + Integration API admission; overlay Store values; never scrape Rank Math HTML; never treat Rank Math meta as AIML source of truth for translations |
| **Theme / Blocksy** | Theme chrome only | Admit only if ownership is deterministic; otherwise unsupported |
| **Elementor** | `_elementor_data` document content (ADR-0016) | No SEO URL ownership; document body remains `e:` — do not re-admit as SEO units |
| **AIML** | Translation overlays, Store rows, Review/TM/Glossary/Jobs orchestration, language prefix routing (ADR-0002), language-aware URL generation/lookup, redirect policy for translated slugs, hreflang/canonical **policy emission** where WP/SEO plugin does not own the contract, preview indexability policy, SEO diagnostics | Never a second SEO engine |

### Ownership rules

1. Visible on a page ≠ ownership.
2. Preference order for identity reuse: existing post fields → `b:` → `e:` → `p:` — do not re-admit the same string under a second identity.
3. Shared-definition / site-global SEO chrome without a stable external definition ID → **Deferred / Unsupported** (do not invent a Store host).
4. Local failure → source value + continue; no fatals; no heuristic rematch.
5. Rank Math, when active, is the foreign owner of title/meta emission — A.SEOc is the admission wave.

---

## 6. Platform reuse (frozen)

Must use unchanged:

```text
Store → Workspace → Suggestions → Review → TM → Glossary → Jobs
(+ Diagnostics, PluginIdentity, LanguageContext / LanguageResolver)
```

| Component | SEO reuse rule |
|---|---|
| **Store** | Sole overlay persistence for admitted SEO translation units |
| **Workspace** | Sole operator edit path; additive metadata only |
| **Review / TM / Glossary / Jobs** | Existing pipelines only — no SEO-specific Jobs/TM |
| **Diagnostics** | Bounded counters/status; no bodies/secrets; no second diagnostics product |
| **PluginIdentity** | Rank Math / plugin-owned SEO fields use `p:` via framework serializer when admitted |
| **LanguageContext** | Request language state for URL generation, overlays, and indexability gates |

No SEO-specific Store, Review queue, TM, or Jobs pipeline.

---

## 7. Implementation waves (frozen)

This plan **refines** the roadmap A.SEO line into six waves. Authoritative boundaries:

### A.SEOa — Slugs and permalink translation

| | |
|---|---|
| **Purpose** | Freeze multilingual URL identity: translated slugs, permalink generation, redirect policy, collision handling under ADR-0002 prefix-strip routing |
| **Ownership** | WordPress owns slug persistence / rewrites / permalink generation; Woo owns product/taxonomy permalink structures; AIML owns translated slug overlays, language-aware URL generation/lookup, redirect policy, diagnostics |
| **Dependencies** | Parent A.SEO plan; ADR-0001 / 0002 / 0007 / 0008; Store `FORMAT_SLUG` discipline; TARGET 6 |
| **Implementation boundary** | Slug/permalink/redirect contracts only. Translated rewrite bases remain **Deferred / ADR-required** per ADR-0002 |
| **Validation philosophy** | EN/SV URL correctness; no mixed-language URLs; redirect/404 safety; no chains; no fuzzy matching; Woo permalink compatibility |
| **Out of scope** | Canonical tags, hreflang, Rank Math meta, OG/Twitter, sitemaps, robots, SEO diagnostics UI |

### A.SEOb — Canonical URLs, hreflang, language relationships

| | |
|---|---|
| **Purpose** | Emit correct canonical URLs and reciprocal hreflang (incl. `x-default` policy) for published languages |
| **Ownership** | AIML owns language-relationship emission policy; WordPress/Rank Math may emit competing tags — cooperation rules frozen in wave plan; preview languages excluded (ADR-0008) |
| **Dependencies** | **A.SEOa** (URL identity); ADR-0008; LanguageContext |
| **Implementation boundary** | Document-level link relationships and canonical URL correctness only — not social meta or sitemaps |
| **Validation philosophy** | Canonical consistency with language-aware URLs; hreflang reciprocity; no preview leakage; no cross-language redirect guessing |
| **Out of scope** | Slug redesign, Rank Math title/meta, OG/Twitter, XML sitemaps, robots.txt body policy beyond canonical/hreflang interaction |

### A.SEOc — Rank Math integration (titles, meta, schema cooperation)

| | |
|---|---|
| **Purpose** | Admit Rank Math–owned SEO titles, meta descriptions, and schema cooperation through Integration API / official filters |
| **Ownership** | Rank Math owns persistence/emission; AIML overlays Store values; PluginIdentity for `p:` keys when required |
| **Dependencies** | A.1 / ADR-0017; **A.SEOa** URL identity; **A.SEOb** recommended before claiming full SERP readiness (titles without correct canonical/hreflang are incomplete) |
| **Implementation boundary** | Title, meta description, and schema **cooperation** only — not Rank Math as a second CMS |
| **Validation philosophy** | Rank Math active/inactive paths; no `document_title_parts`-only assumption; FP=0 / leakage=0; source fallback |
| **Out of scope** | OG/Twitter-specific wave (A.SEOd may reuse Rank Math hooks), sitemap wave, slug wave |

### A.SEOd — OpenGraph, Twitter, social metadata

| | |
|---|---|
| **Purpose** | Language-correct social metadata (OG/Twitter) without scraping |
| **Ownership** | Prefer Rank Math / WP emission owners when active; AIML overlays admitted fields only |
| **Dependencies** | **A.SEOa**; **A.SEOc** when Rank Math owns social tags on the site |
| **Implementation boundary** | Social meta tags only |
| **Validation philosophy** | Locale/language correctness; no wrong-language images/titles; no duplicate conflicting tags beyond admitted policy |
| **Out of scope** | XML sitemaps, robots, Rich Results beyond social tags, slug/canonical redesign |

### A.SEOe — XML sitemaps, robots, indexability, discovery

| | |
|---|---|
| **Purpose** | Crawl discovery: language-aware sitemaps/alternates, robots/indexability, preview exclusion |
| **Ownership** | Rank Math / WP sitemap emitters own generation when active; AIML supplies language URL policy and indexability gates |
| **Dependencies** | **A.SEOa**, **A.SEOb**; ADR-0008 preview exclusion; Rank Math cooperation from A.SEOc where Rank Math owns sitemaps |
| **Implementation boundary** | Discovery and indexability only |
| **Validation philosophy** | Sitemap validity; published-only inclusion; robots correctness; crawlability; GSC readiness signals |
| **Out of scope** | Title/meta content translation (A.SEOc), SEO diagnostics product (A.SEOf) |

### A.SEOf — SEO diagnostics, validation, health, verification

| | |
|---|---|
| **Purpose** | Bounded SEO health/diagnostics and verification tooling over admitted surfaces |
| **Ownership** | AIML Diagnostics conventions; no second diagnostics product |
| **Dependencies** | **A.SEOa–A.SEOe** contracts exist (may validate incrementally, but family closure requires prior waves’ Supported surfaces) |
| **Implementation boundary** | Diagnostics/validation/health only — does not redefine SEO ownership |
| **Validation philosophy** | False-positive control; duplicate URL/metadata detection; leakage detection; Rich Results / GSC readiness checks as non-blocking advisories unless frozen otherwise |
| **Out of scope** | New SEO emitters; Store redesign; scraping-based auditors as primary architecture |

---

## 8. Dependency and sequencing (summary)

Authoritative detail: [A_SEO_DEPENDENCY_MATRIX.md](A_SEO_DEPENDENCY_MATRIX.md).

**Required implementation order:**

```text
A.SEOa → A.SEOb → A.SEOc → A.SEOd → A.SEOe → A.SEOf
```

| Rule | Meaning |
|---|---|
| A.SEOa first | No later wave may invent URL identity |
| A.SEOb before discovery claims | Canonical/hreflang before sitemap/indexability completeness |
| A.SEOc before Rank Math–owned social/sitemap assumptions | Social/sitemap waves cooperate with Rank Math ownership |
| A.SEOf last for family closure | Diagnostics verify frozen contracts; they do not define them |

Product sequencing note: [`PRODUCT_PRIORITIES.md`](../PRODUCT_PRIORITIES.md) places **A.6** before **A.SEO** coding. This architecture plan may freeze earlier; implementation still follows product priority unless that document changes.

---

## 9. Admission philosophy (frozen)

**No blanket SEO support.**

Every admitted SEO surface must record:

| Gate | Evidence |
|---|---|
| Ownership | Foreign owner + ownership class |
| Identity | Deterministic key via existing serializer families only |
| Extraction | Official WP / Woo / Rank Math APIs — allowlisted |
| Overlay | Official hooks/filters — no scrape |
| Lifecycle | missing / inactive / version / delete / rename / language removal |
| Diagnostics | Bounded counters; no bodies/secrets |
| Workspace / Review / TM / Glossary / Jobs | Existing pipelines only |
| Browser / crawl | EN/SV; FP=0; leakage=0; source fallback |

Disposition: **Supported** / **Experimental** / **Deferred** / **Unsupported**.

---

## 10. Validation philosophy (family — reused by every wave)

Architecture-level requirements inherited by A.SEOa–A.SEOf:

| Category | Requirement |
|---|---|
| Languages | EN/SV fixtures as default Biopentra pair; published vs preview distinction |
| URLs | Canonical correctness vs language-aware permalinks; no mixed-language URLs |
| hreflang | Reciprocity; `x-default` policy explicit; preview absent |
| Safety | 404 safety; redirect safety; no redirect chains; no cross-language redirects unless explicitly admitted |
| Duplicates | Duplicate URL detection; duplicate metadata detection |
| Quality | Language leakage = 0 on admitted surfaces; false-positive control |
| Discovery | Crawlability; sitemap validity; robots validation |
| Ecosystem | Google Search Console readiness; Rich Results compatibility (advisory unless wave freezes stronger) |
| Compatibility | WooCommerce URL structures; Rank Math active/inactive paths |
| Negatives | No HTML scraping; no fuzzy matching; no path identity; no URL guessing; no second URL/SEO system |

Wave plans add surface-specific checks; they must not weaken this family set.

---

## 11. Architectural acceptance criteria

These are **architecture** criteria for the family freeze — not implementation acceptance.

1. A.SEO is defined as waves A.SEOa–A.SEOf with frozen boundaries in this document.
2. Dependency matrix is canonical for implementation order.
3. ADR-0001 overlay model is preserved — no post/term duplication for translation.
4. ADR-0002 prefix-strip routing is preserved — no silent rewrite-base translation.
5. Translated rewrite bases are Deferred / ADR-required, not Supported by this parent freeze.
6. ADR-0007 hash semantics remain freshness/integrity only.
7. ADR-0008 preview languages are excluded from hreflang and sitemaps.
8. ADR-0008 fallback chains remain disallowed until an explicit SEO policy ADR/wave admits them.
9. ADR-0013 `b:` identity is not reused for SEO meta that Elementor/Gutenberg does not own.
10. ADR-0016 Elementor ownership is unchanged; Elementor has no SEO URL ownership.
11. ADR-0017 Integration API v1 remains the Rank Math admission path when plugin-owned fields need `p:`.
12. ADR-0018 is not a visitor SEO contract.
13. Schema TARGET remains **6** for the family plan.
14. Integration API v1 public contracts are unchanged by this plan.
15. Store is the sole overlay persistence for admitted SEO translation units.
16. Workspace is the sole operator edit path for those units.
17. Review / TM / Glossary / Jobs remain the sole pipeline — no SEO fork.
18. Diagnostics remain bounded and reuse existing conventions.
19. PluginIdentity is mandatory for any new `p:` SEO keys.
20. LanguageContext / LanguageResolver separation remains intact.
21. WordPress owns slug persistence and rewrite/permalink generation.
22. WooCommerce owns product permalink and taxonomy rewrite structures.
23. Rank Math owns title/meta emission when active.
24. AIML owns language-aware URL generation/lookup and translated-slug redirect policy.
25. Theme/Blocksy SEO strings are unsupported unless ownership is proven deterministic.
26. Only the real owner may produce translation units for a surface.
27. No duplicate ownership of the same SEO string under two identities.
28. No parallel SEO subsystem outside AIML platform reuse.
29. No HTML scraping as primary SEO strategy.
30. No second translation pipeline.
31. No Store redesign.
32. No schema redesign.
33. No new identity family without a focused ADR.
34. No fuzzy URL matching.
35. No path identity as Store identity.
36. No URL guessing / heuristic cross-language redirects.
37. A.SEOa freezes URL identity before any later wave may emit SEO tags that depend on URLs.
38. A.SEOb freezes canonical/hreflang before A.SEOe discovery completeness claims.
39. A.SEOc freezes Rank Math title/meta/schema cooperation.
40. A.SEOd freezes social metadata only.
41. A.SEOe freezes sitemap/robots/indexability/discovery only.
42. A.SEOf freezes diagnostics/validation/health only and may not redefine emitters.
43. Each wave documents purpose, ownership, dependencies, implementation boundary, validation philosophy, and out-of-scope.
44. Later waves must not redefine URL ownership, slug identity, permalink rules, or redirect behavior frozen by A.SEOa.
45. Fail-closed behavior is mandatory on unsupported/ambiguous SEO surfaces.
46. Published vs preview indexability is an explicit family policy (preview non-public).
47. EN/SV validation is the default architectural test pair for Biopentra.
48. WooCommerce compatibility is a family regression concern for URL waves.
49. Rank Math active and inactive paths are both first-class compatibility concerns.
50. Stop conditions in §12 are binding for all child plans.
51. This planning milestone contains no production code.
52. Roadmap pointers reference this plan without program renumbering.

---

## 12. Stop conditions

**STOP** family or wave work (and recommend a focused ADR before coding) if architecture would require:

- new identity family
- Store redesign
- schema redesign / TARGET bump
- second translation pipeline
- HTML scraping as primary mechanism
- duplicate ownership
- unsupported shared-definition ownership forced into Store
- breaking Integration API v1
- reopening ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 for convenience
- translated rewrite bases without an ADR that supersedes ADR-0002
- a parallel SEO subsystem

Document blockers honestly in the wave plan; do not weaken this parent contract.

---

## 13. Out of scope (this planning milestone)

- Any `src/` / tests / schema / ADR edits
- Per-wave detailed work packages beyond milestone boundaries (child plans own those)
- A.6 visitor chrome implementation
- Age Gate / CookieYes / other non-SEO integrations
- Program B/C/D/E work
- Production merge/tag/release

---

## 14. Documentation / roadmap (this planning task)

| Deliverable | Path |
|---|---|
| Parent plan | `docs/plans/ASEO_PARENT_IMPLEMENTATION_PLAN.md` (this file) |
| Dependency matrix | `docs/plans/A_SEO_DEPENDENCY_MATRIX.md` |
| Evidence | `docs/plans/aseo-evidence/` |
| Roadmap pointers | `docs/plans/POST_V1_PLATFORM_ROADMAP.md`, `docs/ROADMAP.md` |

---

## 15. Sequencing after plan freeze

1. Merge this planning branch to `main` (separate explicit merge step — not part of this branch’s push).
2. Author **A.SEOa** planning branch from that `main` (`feature/aseoa-slugs-permalinks-plan`).
3. After each wave’s plan freezes, open a dedicated implementation branch.
4. Do not start A.SEO coding before product priority allows (A.6 before A.SEO unless priorities change).

---

## 16. Risks

| Risk | Mitigation |
|---|---|
| Rank Math owns `<title>` so core title filters never run | A.SEOc must target Rank Math filters; evidence in `aseo-evidence/` |
| `redirect_canonical` suppressed for prefixed URLs today | A.SEOa/A.SEOb must replace suppression with correct language-aware policy |
| ADR-0002 forbids translated rewrite bases | Keep Deferred; ADR if product requires `/sv/produkt/` |
| Site-global SEO chrome without shared-definition host | Defer / Unsupported — Age Gate lesson |
| Later waves redefine URLs | Dependency matrix + AC #44 |

---

## 17. ADR assessment

**No new ADR required at parent freeze.**

Carry forward ADR-0001, ADR-0002, ADR-0007, ADR-0008, ADR-0013, ADR-0016, ADR-0017, ADR-0018.

Trigger a focused ADR before coding if a wave needs translated rewrite bases, a new identity family, Store/schema redesign, or a new shared-definition Store host for site-global SEO strings.

---

## Document control

| Item | Value |
|---|---|
| Canonical path | `docs/plans/ASEO_PARENT_IMPLEMENTATION_PLAN.md` |
| Kind | Family architecture / planning freeze |
| Companion | `docs/plans/A_SEO_DEPENDENCY_MATRIX.md` |
| Evidence | `docs/plans/aseo-evidence/` |
| Planning branch | `feature/aseo-parent-plan` |
| Implementation | Not started |
