# A.SEOd — OpenGraph / Twitter Metadata — Implementation Plan

**Status:** **Architecture Frozen (planning)** — implementation not started  
**Milestone:** Program A — **A.SEOd** (fourth wave of A.SEO)  
**Plan freeze:** Evidence-driven admissions SD1–SD12; Rank Math remains foreign owner of OG/Twitter emission; AIML overlays via official Rank Math hooks only; SB11 + A.SEOc consumed unchanged; TARGET **6**; Supported = **SD1, SD2, SD3, SD5, SD6, SD7, SD8, SD11**; Partially Supported = **explicit Facebook/Twitter text overrides**; Deferred = **SD4, SD9, SD10, SD12**  
**ADR assessment:** **No new ADR required** for the Supported / Partially Supported set if Implementation uses Integration API v1 + PluginIdentity `p:rankmath:…` + existing Store overlays + SB11. Do not reopen ADR-0001 / 0002 / 0008 / 0017. Do not change A.SEOa URL contracts, A.SEOb SB11, or A.SEOc title/meta ownership.  
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — §6.3 / A.SEO family  
**Parent architecture:** [ASEO_PARENT_IMPLEMENTATION_PLAN.md](ASEO_PARENT_IMPLEMENTATION_PLAN.md) (authoritative; not redesigned)  
**Dependency matrix:** [A_SEO_DEPENDENCY_MATRIX.md](A_SEO_DEPENDENCY_MATRIX.md)  
**Evidence:** [aseod-evidence/](aseod-evidence/)  
**Planning branch:** `feature/aseod-opengraph-plan`  
**Implementation branch:** create only after this plan freezes on `main` — `feature/aseod-opengraph`  
**Baseline (plan authoring):** `main` @ `e4cd9ab36743e2d35da04040cbb5c6b1ece7b6d5`  
**Depends on:** A.SEOa **Complete**; A.SEOb **Complete** (SB11); A.SEOc **Complete** (`a-seoc-rankmath-complete`); A.1 / ADR-0017; ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 **Accepted**; Integration API v1; TARGET **6**

**Operational success (Supported):** When Rank Math is active, published-language documents expose language-correct OpenGraph and Twitter textual metadata for admitted surfaces via official Rank Math filters; `og:url` / `og:locale` remain language-correct via existing URL/locale contracts; `og:locale:alternate` lists published SB11 languages only; preview languages never appear as public social variants; Rank Math missing/inactive degrades safely; AIML never emits duplicate competing OG/Twitter tags.

**This plan is the frozen implementation contract for A.SEOd.** Do not widen Supported admissions without new evidence + ADR where gated. Do not open an implementation branch until this plan is independently reviewed and merged to `main`.

---

## 1. Purpose

Freeze how AIML cooperates with Rank Math for multilingual **OpenGraph**, **Twitter Cards**, and related social metadata — without scraping HTML, annexing Rank Math social meta, or redesigning A.SEOa/A.SEOb/A.SEOc contracts.

A.SEOd does **not**:

- redesign canonical URLs, hreflang, or SB11 (A.SEOb)
- take ownership of SEO titles or meta descriptions (A.SEOc)
- implement XML sitemaps / robots / indexability product (A.SEOe)
- implement SEO diagnostics UI (A.SEOf)
- invent a reusable social metadata contract when none exists (SD12)
- assume SD candidates are Supported before evidence

A.SEOd **does**:

- inventory OG/Twitter ownership across WP / Woo / Rank Math / theme / AIML
- freeze Supported / Partially Supported / Deferred / Unsupported for SD1–SD12
- define work packages ASEOD.0–ASEOD.8 for the admitted set

---

## 2. Preconditions (verified at plan authoring)

| Precondition | Status |
|---|---|
| Working tree clean; `main` == `origin/main` | **Pass** (`e4cd9ab36`) |
| A.SEO parent plan on `main` | **Pass** |
| `A_SEO_DEPENDENCY_MATRIX.md` on `main` | **Pass** |
| A.SEOa Complete + tag `a-seoa-slugs-permalinks-complete` | **Pass** |
| A.SEOb Complete + tag `a-seob-canonical-hreflang-complete` | **Pass** |
| A.SEOc Complete + tag `a-seoc-rankmath-complete` | **Pass** |
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
| A.SEOb | Canonical / hreflang / SB11 unchanged |
| A.SEOc | Rank Math title/meta/schema admissions unchanged; Integration API v1 |
| ADR-0001 | Overlay only |
| ADR-0002 | Prefix-strip routing |
| ADR-0007 | Hash ≠ identity |
| ADR-0008 | Preview capability-gated; preview excluded from public SEO discovery |
| ADR-0013 / 0016 / 0017 | Identity families unchanged; `p:` via PluginIdentity when needed |
| Integration API v1 | Unchanged |
| Store / Workspace / Review / TM / Glossary / Jobs / Diagnostics / LanguageContext / PreviewService / Router | Reuse |
| TARGET | **6** — no bump |

**Forbidden:**

- new identity family beyond ADR-0017 `p:`
- Rank Math social meta/options as AIML translation persistence annexation
- HTML scraping / final-head rewriting
- second SEO/social emission pipeline competing with Rank Math
- SB11 / A.SEOa URL / A.SEOb canonical / A.SEOc title redesign
- Store/schema redesign or TARGET bump
- A.SEOe/A.SEOf scope creep
- inventing SD12

---

## 4. Implementation boundary

A.SEOd owns only **social metadata emission** cooperation (OpenGraph / Twitter / related Rank Math social tags).

It must **not** modify:

- canonical
- hreflang
- titles (SEO document title / Rank Math SEO title ownership)
- meta descriptions (SEO meta description ownership)
- sitemap generation
- robots / indexability product
- diagnostics UI

Those remain owned by A.SEOb, A.SEOc, A.SEOe and A.SEOf respectively.

---

## 5. Hard rails

- Official Rank Math OpenGraph/Twitter hooks/APIs only
- Rank Math remains foreign owner of configuration, source social metadata, and emission pipeline
- AIML owns Store overlays, language selection, and SB11-driven locale alternates when admitted
- Prefer reuse of A.SEOc SEO title/description identities over duplicate Store units
- Prefer candidate-local deferral over architectural redesign
- Observational HTML is evidence-only — never the implementation mechanism

---

## 6. Ownership model (frozen)

### Rank Math owns

- OpenGraph / Twitter configuration and emission (`rank_math/opengraph/*`)
- Source social metadata (`rank_math_facebook_*`, `rank_math_twitter_*`)
- Image selection pipeline and machine product OG extras
- `twitter:card` type configuration

### AIML owns

- Translations of **admitted** social/SEO source strings in AIML Store
- LanguageContext-gated overlay application on official Rank Math filters
- `og:locale:alternate` emission via official Rank Math Facebook action using SB11 (Supported SD6)
- Preview exclusion for public social variants (SD11)

### Must not

- Scrape Rank Math HTML
- Emit duplicate `og:*` / `twitter:*` tags outside Rank Math `tag()` / admitted actions
- Mutate Media Library attachment metadata
- Rebuild URLs or language relationships

---

## 7. Social metadata lifecycle (frozen)

```text
Visitor language (Router / LanguageContext)
  → Rank Math OpenGraph/Twitter pipeline
      → Filters: rank_math/opengraph/{facebook|twitter}/{prop}
      → Paper fallback: rank_math/frontend/title|description|canonical
          → A.SEOc overlays (unchanged)
      → AIML A.SEOd overlays on admitted OG/Twitter filters
      → SD6: AIML emits og:locale:alternate via facebook action (SB11)
  → Output owned by Rank Math tag emitter (+ AIML alternate tags on same action)
```

Missing Store translation → native Rank Math value. Never fatal.

---

## 8. Deterministic text policy (SD1/SD2/SD7/SD8)

1. If explicit Facebook text meta present (literal, non-token) and Partially Supported identity exists → overlay that field  
2. Else if Twitter-specific text present and `twitter_use_facebook` is false → overlay Twitter field (Partial)  
3. Else reuse A.SEOc SEO `title` / `description` Store identity via OG/Twitter filters (Supported)  
4. Else native Rank Math / Paper  

Do not create a second identity for the same semantic Paper-path value.

---

## 9. URL / locale / alternates policy (SD3/SD5/SD6)

| ID | Policy |
|---|---|
| SD3 | Consume A.SEOa/A.SEOb URL identity via Rank Math canonical/`rank_math/opengraph/url`; reinforce current-language absolute URL if needed; never rebuild routing |
| SD5 | Rank Math remains `og:locale` emitter; locale follows `get_locale()` (Router). Optional reinforce via `og_locale` filter from LanguageContext. No competing locale tag emitter |
| SD6 | Emit `og:locale:alternate` for each published/routable SB11 language except current; Facebook locale format; no preview languages; no duplicates; no cross-language guessing |

---

## 10. Image / card policy (SD4/SD9/SD10 — Deferred)

No AIML image or card-type overlays in A.SEOd. Rank Math remains sole owner. Machine dimensions/MIME/price/availability untouched.

---

## 11. Preview policy (SD11)

Respect ADR-0008, SA10, SB9:

- Public social overlays use published languages only  
- `og:locale:alternate` never lists preview-only languages  
- Authorized preview stays within preview contract  

---

## 12. SD12 — reusable social contract

**Deferred.** No `SocialMeta` / OpenGraph contract exists in `src/`. Do not invent one.

If a future wave freezes a contract, it may depend only on A.SEOa, A.SEOb, A.SEOc, Router, LanguageContext, Store and Integration API v1. It must not depend on A.SEOe or A.SEOf, and must introduce no circular milestone dependency.

A.SEOd must not create architecture merely to make SD12 Supported.

---

## 13. Identity strategy (frozen preference)

| Case | Identity |
|---|---|
| Paper-path OG/Twitter text | Reuse `p:rankmath:{owner}:{id}:title\|description` (A.SEOc) |
| Explicit Facebook text (Partial) | `p:rankmath:{owner}:{id}:facebook_title\|facebook_description` |
| Explicit Twitter text when not using Facebook (Partial) | `p:rankmath:{owner}:{id}:twitter_title\|twitter_description` |
| URL / locale / alternates | No new Store identity — runtime from SB11 / Router / canonical |
| Images / card type | None (Deferred) |

Exact key spelling finalized in ASEOD.1 against PluginIdentity rules.

---

## 14. Compatibility / lifecycle

| State | Behavior |
|---|---|
| Rank Math missing / inactive | No OG overlays; never fatal |
| Required OG filter/action missing | Skip that surface |
| Integration disabled | Native Rank Math |
| Empty Store | Native Rank Math |
| Sitewide noindex | Social overlays still apply honestly; do not invent canonical |
| Preview request | SD11 gates |

**Unsafe state:** return Rank Math native. **Never fatal.**

---

## 15. Admission matrix (frozen)

See [aseod-evidence/admission-matrix.md](aseod-evidence/admission-matrix.md).

| Disposition | IDs |
|---|---|
| **Supported** | SD1, SD2, SD3, SD5, SD6, SD7, SD8, SD11 |
| **Partially Supported** | Explicit Facebook/Twitter text overrides (bounded) |
| **Deferred** | SD4, SD9, SD10, SD12 |

---

## 16. Work packages (ASEOD.0–ASEOD.8)

### ASEOD.0 — Baseline

| | |
|---|---|
| **Objective** | Lock Rank Math version, modules, live EN/SV social samples, SD matrix into validation log |
| **Scope** | Docs only |
| **Deps** | Plan freeze on `main` |
| **Commit** | `docs(seo): establish A.SEOd baseline` |

### ASEOD.1 — Ownership / admission lock

| | |
|---|---|
| **Objective** | Confirm SD dispositions; freeze identity fields; confirm official hooks still present |
| **Deps** | ASEOD.0 |
| **Stop** | Supported seam lost → defer that candidate only if stop policy allows; no redesign |
| **Commit** | `feat(seo): lock A.SEOd OpenGraph ownership admissions` |

### ASEOD.2 — OpenGraph text (SD1/SD2 + Partial FB text)

| | |
|---|---|
| **Objective** | Overlay `og:title` / `og:description` via official filters; reuse A.SEOc identities |
| **Deps** | ASEOD.1 |
| **Commit** | `feat(seo): implement A.SEOd OpenGraph text overlays` |

### ASEOD.3 — URL / locale / alternates (SD3/SD5/SD6)

| | |
|---|---|
| **Objective** | Reinforce `og:url` / `og:locale` as needed; emit `og:locale:alternate` from SB11 |
| **Deps** | ASEOD.1 |
| **Commit** | `feat(seo): implement A.SEOd OpenGraph URL locale alternates` |

### ASEOD.4 — Social images (SD4)

| | |
|---|---|
| **Objective** | Confirm Deferred; no image overlay code |
| **Deps** | ASEOD.1 |
| **Commit** | docs note only if needed — no production image feature |

### ASEOD.5 — Twitter (SD7/SD8 + Partial; SD9/SD10 Deferred)

| | |
|---|---|
| **Objective** | Overlay Twitter title/description reusing SEO/FB path; leave card/image Deferred |
| **Deps** | ASEOD.2 |
| **Commit** | `feat(seo): implement A.SEOd Twitter text overlays` |

### ASEOD.6 — Preview / lifecycle / compatibility (SD11)

| | |
|---|---|
| **Objective** | Preview exclusion; Rank Math missing/inactive; source fallback; noindex honesty |
| **Deps** | ASEOD.2–5 |
| **Commit** | `feat(seo): implement A.SEOd social preview lifecycle guards` |

### ASEOD.7 — Full acceptance

| | |
|---|---|
| **Objective** | Full unit/integration/PluginGuard/PHPCS; live EN/SV page+product; regression |
| **Deps** | ASEOD.6 |
| **Commit** | validation evidence updates |

### ASEOD.8 — Documentation closure

| | |
|---|---|
| **Objective** | Factual status on plan, validation log, roadmaps; final SD dispositions |
| **Deps** | ASEOD.7 |
| **Commit** | `docs(seo): close A.SEOd OpenGraph integration` |

---

## 17. Architectural acceptance criteria

1. TARGET remains **6**.  
2. Store schema unchanged.  
3. Integration API v1 unchanged.  
4. No new identity family beyond ADR-0017 `p:`.  
5. Rank Math remains foreign OG/Twitter owner.  
6. Official Rank Math hooks only — no HTML scrape.  
7. AIML-added duplicate `og:*` tags = 0.  
8. AIML-added duplicate `twitter:*` tags = 0.  
9. Canonical ownership unchanged (A.SEOb).  
10. Hreflang ownership unchanged (A.SEOb).  
11. SEO title/description ownership unchanged (A.SEOc).  
12. SB11 consumed unchanged.  
13. SD1 Supported behavior implemented or proven via cascade+filter.  
14. SD2 Supported behavior implemented or proven via cascade+filter.  
15. SD3 language-correct `og:url` on EN/SV acceptance URLs.  
16. SD4 remains Deferred (no image overlay).  
17. SD5 language-correct `og:locale` without competing emitter.  
18. SD6 `og:locale:alternate` for published SB11 languages only.  
19. SD7 Twitter title language-correct when admitted.  
20. SD8 Twitter description language-correct when admitted.  
21. SD9 remains Deferred.  
22. SD10 remains Deferred.  
23. SD11 preview languages absent from public alternates/overlays.  
24. SD12 remains Deferred — no invented social contract.  
25. Explicit FB text Partial only within frozen field identities.  
26. Explicit Twitter text Partial only when not using Facebook path.  
27. Missing translation → native Rank Math.  
28. Rank Math inactive → never fatal.  
29. Required hook missing → skip surface.  
30. Integration disabled → native Rank Math.  
31. No Media Library mutation.  
32. No social metadata persistence table.  
33. No second SEO pipeline.  
34. Router unchanged unless frozen plan requires locale reinforce only via existing filter.  
35. LanguageContext reused.  
36. PreviewService reused (no new preview product).  
37. Woo `product:*` machine tags untouched.  
38. Image dimensions/MIME untouched.  
39. FP = 0 on acceptance fixtures.  
40. Language leakage = 0.  
41. Duplicate semantic ownership = 0 for Paper-path title/description.  
42. Incorrect alternate locales = 0.  
43. EN→SV→EN→SV alternate stability.  
44. Page + product live acceptance for Supported surfaces.  
45. Unit suite green.  
46. Integration suite green.  
47. PluginGuard pass.  
48. PHPCS pass.  
49. `git diff --check` clean.  
50. A.SEOe/A.SEOf not started.  
51. Performance observed only — no invented budgets.  
52. Validation log records baseline + final dispositions.  
53. Roadmap pointers factual only — no milestone renumbering.  
54. Implementation boundary (social only) preserved.  
55. Sitewide noindex honesty (no invented canonical).  

---

## 18. Stop conditions

STOP implementation if:

- A Supported candidate loses its official Rank Math seam and cannot be deferred under this plan’s stop policy without redesign  
- Architecture requires Store/schema/TARGET bump or new identity family  
- HTML scrape or final-head rewrite becomes the only workable approach  
- A.SEOa/A.SEOb/A.SEOc contracts must be reopened  
- SD12 pressure forces inventing a social contract  

Ordinary defects: fix, test, continue.

---

## 19. ADR assessment

**No new ADR required** for Supported SD1–SD3, SD5–SD8, SD11 and bounded Partial explicit FB/Twitter text, provided Implementation stays on Integration API v1 + PluginIdentity `p:rankmath:…` + SB11 + existing Router/LanguageContext.

Do not reopen ADR-0001, 0002, 0008, 0017.

---

## 20. Out of scope (reminder)

- A.SEOe sitemaps/robots  
- A.SEOf diagnostics  
- Translated leaf slugs  
- SD4/SD9/SD10/SD12 implementation  
- PRODUCT_PRIORITIES edits unless factual status strictly requires  

---

## 21. Architecture verdict

A.SEOd is ready to freeze as **Architecture Frozen (planning)** with evidence-driven SD admissions. Implementation is authorized only after this plan merges to `main`. Implementation must not begin A.SEOe.

---

## Document control

| Version | Date | Notes |
|---|---|---|
| 0.1 | 2026-08-09 | Planning freeze authoring on `feature/aseod-opengraph-plan`; baseline `e4cd9ab36`; Rank Math 1.0.275 inventory; SD1–SD12 dispositions frozen for review |
