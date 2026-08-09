# A.SEOd — OpenGraph / Twitter — Validation Log

**Milestone:** A.SEOd OpenGraph / Twitter Metadata  
**Implementation branch:** `feature/aseod-opengraph`  
**Plan:** [ASEOD_OPENGRAPH_IMPLEMENTATION_PLAN.md](ASEOD_OPENGRAPH_IMPLEMENTATION_PLAN.md)  
**Evidence:** [aseod-evidence/](aseod-evidence/)  
**Planning freeze on main:** merge `49d1ab1ef`  
**Planning closure:** `3738c5d5f`  
**Implementation baseline HEAD:** `3738c5d5f`  
**Review-ready feature HEAD:** see git tip of `feature/aseod-opengraph`

**Supported:** SD1, SD2, SD3, SD5, SD6, SD7, SD8, SD11  
**Partially Supported:** explicit Facebook/Twitter text overrides  
**Deferred:** SD4, SD9, SD10, SD12  
**Out of scope:** sitemap/robots (A.SEOe), diagnostics (A.SEOf), canonical/hreflang/SEO title ownership

---

## ASEOD.0 — Baseline

**Status:** PASS

| Item | Result |
|---|---|
| Plan Architecture Frozen on main | **Pass** (`49d1ab1ef` / closure `3738c5d5f`) |
| A.SEOa / A.SEOb / A.SEOc Complete | **Pass** |
| TARGET | **6** |
| SB11 | Present / unchanged |
| Rank Math | **1.0.275** |
| Pre-impl AIML OG/Twitter hooks | **None** |

---

## ASEOD.1 — Ownership / admission lock

**Status:** PASS — extended `RankMathIntegration`; fields `facebook_title|facebook_description|twitter_title|twitter_description`; official `rank_math/opengraph/*` seams

---

## ASEOD.2 — OpenGraph text (SD1/SD2)

**Status:** PASS — `HOOK_OG_TITLE` / `HOOK_OG_DESCRIPTION`; reuse A.SEOc SEO identity when Facebook meta empty; Partial explicit Facebook fields extracted

Live: SV product `bpc-157` → `og:title=BPC-157 forskningspeptid | Biopentra`

---

## ASEOD.3 — URL / locale / alternates (SD3/SD5/SD6)

**Status:** PASS — `HOOK_OG_URL` / `HOOK_OG_LOCALE` reinforce; `og:locale:alternate` via Facebook action + SB11  
**Fix:** public social hooks register on default language (`register_public_social_hooks`) so EN emits `sv_SE` alternate

Live EN: `og:locale=en_US` + `og:locale:alternate=sv_SE`  
Live SV: `og:locale=sv_SE` + `og:locale:alternate=en_US`

---

## ASEOD.4 — Social images (SD4)

**Status:** Deferred — no implementation (as frozen)

---

## ASEOD.5 — Twitter (SD7/SD8; SD9/SD10 Deferred)

**Status:** PASS — Twitter title/description overlays reuse Facebook/SEO path when `twitter_use_facebook` default; card/image Deferred

---

## ASEOD.6 — Preview / lifecycle (SD11)

**Status:** PASS — SB11 `for_public_request()` excludes preview; inactive Rank Math skips hooks; missing Store → native; never fatal

---

## ASEOD.7 — Full acceptance

**Status:** PASS

| Gate | Result |
|---|---|
| Unit | **594** tests / **1610** assertions (2 skipped) |
| Integration | **574** tests / **12375** assertions (2 skipped) including AseodOpenGraphTest |
| PluginGuard | **17** / **8972** |
| PHPCS (touched) | **PASS** (prefix warnings on Rank Math hook names only) |
| `git diff --check` | **PASS** |
| Live page EN/SV locale+alternate | **PASS** |
| Live product SV translated OG title | **PASS** (`bpc-157`) |
| Hreflang regression | **PASS** |
| Duplicate `og:title` / `twitter:title` | **1** each (Rank Math only; AIML-added duplicates = **0**) |
| FP / leakage | **0** / **0** |
| TARGET / Store schema | **6** / unchanged |
| SB11 / A.SEOc title hooks | Unchanged |
| Performance | Observed only — no budgets invented |

Env note: `/sv/` home 301-loop remains (pre-existing); acceptance used non-home SV URLs.

---

## ASEOD.8 — Closure

**Status:** **PASS — Complete** — merged to `main`; tag `a-seod-opengraph-complete`

**Final dispositions (unchanged from freeze):**

| Disposition | IDs |
|---|---|
| Supported | SD1, SD2, SD3, SD5, SD6, SD7, SD8, SD11 |
| Partially Supported | Explicit Facebook/Twitter text overrides |
| Deferred | SD4, SD9, SD10, SD12 |

**Known limitation (pre-existing, not A.SEOd):** `/sv/` (and `/sv`) front-page requests 301-loop to `https://dev.biopentra.eu/sv/` with the same redirect count on `main` without A.SEOd and with A.SEOd mounted. A.SEOd introduces **0** new redirect loops (Router/Seo unchanged; OG hooks run after redirect resolution). Track as existing technical debt outside A.SEOd.

**Tag:** `a-seod-opengraph-complete`

**Next:** A.SEOe planning/implementation decision only. **A.SEOe has not been started.**
