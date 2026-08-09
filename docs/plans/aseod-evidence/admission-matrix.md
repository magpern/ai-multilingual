# A.SEOd Evidence — Admission Matrix

**Status:** Planning freeze — evidence-driven dispositions  
**Baseline:** `main` @ `e4cd9ab36`  
**Rule:** Every SD1–SD12 item started as **Candidate**. Disposition only after evidence.

---

## Final dispositions

| ID | Topic | Disposition | Evidence basis | ADR gate |
|---|---|---|---|---|
| SD1 | OpenGraph title | **Supported** | Official `rank_math/opengraph/facebook/og_title`; Paper cascade reuses A.SEOc SEO title identity when `facebook_title` empty; AIML overlays via filter without duplicate tags | ADR-0017 `p:` reuse `title` |
| SD2 | OpenGraph description | **Supported** | Same for `og_description` / A.SEOc `description` identity | ADR-0017 |
| SD3 | OpenGraph URL | **Supported** | Rank Math `og:url` ← Paper canonical; A.SEOb already language-correct; AIML may reinforce via `rank_math/opengraph/url` using existing URL contracts — no URL rebuild | A.SEOa/A.SEOb |
| SD4 | OpenGraph image | **Deferred** | No safe language-specific image contract without Media Library mutation / new persistence | Prefer deferral |
| SD5 | OpenGraph locale | **Supported** | Rank Math emits `og:locale` from `get_locale()`; AIML Router already supplies language locale; verify + optional reinforce via `og_locale` filter from LanguageContext — no competing emitter | Existing Router |
| SD6 | OpenGraph locale alternates | **Supported** | Rank Math 1.0.275 does **not** emit `og:locale:alternate`; AIML adds via official `rank_math/opengraph/facebook` action using SB11 published/routable languages only | SB11 |
| SD7 | Twitter title | **Supported** | Default `twitter_use_facebook`; values follow FB→Paper→A.SEOc; overlay `rank_math/opengraph/twitter/twitter_title` reusing same SEO identity — no second semantic identity | ADR-0017 reuse |
| SD8 | Twitter description | **Supported** | Same as SD7 for description | ADR-0017 reuse |
| SD9 | Twitter image | **Deferred** | Same as SD4 | Prefer deferral |
| SD10 | Twitter card type | **Deferred** | Config/machine surface; not multilingual; Rank Math remains sole owner; no AIML overlay | None |
| SD11 | Preview exclusion | **Supported** | ADR-0008 / SA10 / SB9; public social overlays + alternates exclude preview languages | ADR-0008 |
| SD12 | Canonical reusable social metadata contract for A.SEOe/A.SEOf | **Deferred** | No existing SocialMeta/OpenGraph contract in `src/`; must not invent one this wave | Never invent |

---

## Partially Supported (bounded)

| Topic | Disposition | Boundary |
|---|---|---|
| Explicit `rank_math_facebook_title\|description` | **Partially Supported** | Overlay only when literal non-token meta present; identity `p:rankmath:{owner}:{id}:facebook_title\|facebook_description`; never annex meta as AIML DB |
| Explicit `rank_math_twitter_title\|description` when `twitter_use_facebook` false | **Partially Supported** | Same pattern with `twitter_title\|twitter_description` fields; when use_facebook true, reuse FB/SEO path only |

---

## Explicitly not Supported in A.SEOd

| Topic | Disposition | Why |
|---|---|---|
| Social image / alt translation | Deferred | SD4/SD9 |
| Twitter card type overlay | Deferred | SD10 |
| Invented reusable social contract | Deferred | SD12 |
| Canonical / hreflang / SEO title / meta description ownership | Out of wave | A.SEOb / A.SEOc |
| Sitemap / robots / diagnostics | Out of wave | A.SEOe / A.SEOf |
| HTML scrape / final-head rewrite | Unsupported | Family forbidden |
| Second OG emission pipeline | Unsupported | Ownership forbidden |
| Media Library mutation | Unsupported | Hard boundary |
| Store/schema/TARGET redesign | Unsupported | Hard rails |

---

## Supported set (frozen for implementation authorization)

**Supported:** SD1, SD2, SD3, SD5, SD6, SD7, SD8, SD11  
**Partially Supported (bounded):** explicit Facebook/Twitter text overrides (see table)  
**Deferred:** SD4, SD9, SD10, SD12  

Implementation may claim Partially Supported only within the frozen partial boundaries in the wave plan.
