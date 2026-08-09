# A.SEOe — XML Sitemaps / Robots — Validation Log

**Milestone:** A.SEOe XML Sitemaps / Robots / Indexability
**Implementation branch:** `feature/aseoe-sitemaps`
**Plan:** [ASEOE_SITEMAPS_IMPLEMENTATION_PLAN.md](ASEOE_SITEMAPS_IMPLEMENTATION_PLAN.md)
**Evidence:** [aseoe-evidence/](aseoe-evidence/)
**Planning freeze on main:** merge `d9bcb56a8`
**Planning closure:** `cce180fdd`
**Implementation baseline HEAD:** `cce180fdd`
**Review-ready feature HEAD:** see git tip of `feature/aseoe-sitemaps`

**Supported:** SE1, SE2, SE3, SE4, SE5, SE6, SE7, SE8, SE9, SE12
**Deferred:** SE10, SE11
**Out of scope:** A.SEOf diagnostics; multilingual media ownership; invented SitemapDiscovery contract

---

## ASEOE.0 — Baseline + live sitemap inventory

**Status:** PASS

| Item | Result |
|---|---|
| Plan Architecture Frozen on main | **Pass** (merge `d9bcb56a8` / closure `cce180fdd`) |
| A.SEOa–A.SEOd Complete | **Pass** (tag `a-seod-opengraph-complete`) |
| TARGET | **6** |
| SB11 | Present / unchanged |
| Rank Math | **1.0.275**; sitemap module active |
| Sitemap owner | Rank Math `/sitemap_index.xml` |
| Index children | page, product, product_cat |
| Pre-impl language locs / xhtml:link | **Absent** (default-language locs only) |
| robots.txt | WP+WC+RM `Sitemap:` → Rank Math index |
| AIML sitemap/robots src | **None** pre-impl |
| Pre-existing `/sv/` home 301-loop | Recorded (not A.SEOe scope) |
| Live `blog_public` | **0** (Discourage search engines) |

### Admission lock (authoritative)

| ID | Disposition |
|---|---|
| SE1–SE9, SE12 | Supported |
| SE10, SE11 | Deferred |

---

## ASEOE.1 — Ownership / admissions freeze

**Status:** PASS — extended `rankmath` Integration API consumer with `RankMathSitemapOverlay`; no second provider; SE10/SE11 Deferred guards in tests

---

## ASEOE.2 — Sitemap integration contracts (SE1/SE8)

**Status:** PASS — official hooks only: `rank_math/sitemap/{type}_urlset`, `rank_math/sitemap/url`, `rank_math/sitemap/entry`, `rank_math/sitemap/include_noindex`; registered at Plugin boot before Rank Math `parse_query`

---

## ASEOE.3 — Language URL / alternate contracts (SE2/SE3/SE4/SE5)

**Status:** PASS

**Emission shape (frozen in implementation):**

- Rank Math keeps singular default-language `<loc>` (no per-language duplicate locs)
- When `blog_public=1` and ≥2 public languages: add `xmlns:xhtml` + SB11 `xhtml:link` (+ `x-default`)
- Preview excluded via `for_path(..., false)`

CLI evidence (live hooks + temporary `pre_option_blog_public=1`, option remains `0`):

- `xmlns:xhtml` present on urlset filter output
- EN + SV + x-default xhtml links for `/a4-nested-gutenberg-fixture/`
- `/de/` absent

HTTP observational (stored `blog_public=0`): xhtml absent — honesty gate PASS

---

## ASEOE.4 — Robots / indexability contracts (SE6/SE7)

**Status:** PASS

- Does not replace `robots.txt`; Rank Math `Sitemap:` line retained
- `blog_public=0` → no AIML discovery enrichment (HTTP confirmed)
- `include_noindex` never forced true
- Never invents new sitemap entries

---

## ASEOE.5 — Woo / media / namespace admissions (SE9; SE10 Deferred)

**Status:** PASS — `product` / `product_cat` urlset filters registered; same URL overlay path; SE10 Deferred (no Media Library mutation; image NS left to Rank Math)

---

## ASEOE.6 — Platform / lifecycle / compatibility

**Status:** PASS — inactive Rank Math skips registration; missing SB11 skips; idempotent register; never fatal

---

## ASEOE.7 — Acceptance / crawl / regression / performance (SE12)

**Status:** PASS

| Gate | Result |
|---|---|
| Unit | **PASS** — 599 tests, 1625 assertions (2 skipped) |
| Integration | **PASS** — 589 tests, 12496 assertions (2 skipped); includes Aseoe* + PluginGuard |
| PluginGuard | **PASS** (integration suite) |
| PHPCS (touched PHP) | **PASS** (warnings only on foreign Rank Math hook names) |
| Live index owner | Rank Math singular index (3 children) |
| Live HTTP xhtml @ blog_public=0 | Absent (honesty) |
| Live CLI emission @ blog_public override | xhtml EN/SV/x-default; no DE |
| robots.txt Sitemap | `https://dev.biopentra.eu/sitemap_index.xml` |
| `/wp-sitemap.xml` | 301 → Rank Math index |
| FP / leakage | **0** |
| Duplicate index | **0** |
| TARGET | **6** |
| SB11 / A.SEOa–d | Unchanged (regression suite green) |
| Performance | Observed only — no AIML sitemap cache subsystem; one SB11 `for_path` per url filter |

### Live notes

- Pre-existing `/sv/` home 301 self-loop: not attributed to A.SEOe; not fixed here
- Public HTTP sitemaps remain language-default until `blog_public=1` (intentional honesty)

---

## ASEOE.8 — Documentation closure

**Status:** PASS — this log; roadmap pointer to review-ready implementation; dispositions unchanged

---

## Final SE1–SE12 dispositions

| ID | Disposition | Result |
|---|---|---|
| SE1 | Supported | Singular Rank Math generation preserved |
| SE2 | Supported | Language URLs via xhtml:link href (SB11) |
| SE3 | Supported | Reciprocal xhtml:link + x-default |
| SE4 | Supported | Published/routable only |
| SE5 | Supported | Preview excluded |
| SE6 | Supported | robots stack preserved |
| SE7 | Supported | blog_public / noindex honesty |
| SE8 | Supported | Official RM filters only |
| SE9 | Supported | product / product_cat overlays |
| SE10 | Deferred | Untouched |
| SE11 | Deferred | No SitemapDiscovery invented |
| SE12 | Supported | Automated + observational validation |

---

## Closure

Implementation complete on `feature/aseoe-sitemaps` — **review-ready**. Not merged. Not tagged. A.SEOf not started.

**Recommended tag (after independent review/merge):** `a-seoe-sitemaps-complete`
