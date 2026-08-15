# P1 G4 / Rank Math Model A Characterization — Final Report

**Status:** **COMPLETE**  
**Date:** 2026-08-15  
**Baseline / reconciled main:** `81e688f2652733c58d96a36116d9b1164194be2a`  
**Freeze commit:** `052bc96f77e62edd2837dc8868f547d64d5ee384`  
**Version:** **1.5.1** · **TARGET:** **8** · **Migration:** NONE  
**P0:** COMPLETE  
**Verdict:** **NO SUPPORTED-CONTRACT DEFECT**  
**G4b probe:** **EXPECTED OMIT** (`blog_public=0`)

---

## 1. Repository identity

| Item | Value |
|---|---|
| Repository HEAD reviewed | `81e688f2652733c58d96a36116d9b1164194be2a` |
| Version | 1.5.1 |
| TARGET | 8 |
| Tag `v1.5.1` | `6298df08b3b1456e4875ecdb860b71506d5ae313` (unchanged) |
| P0 status | COMPLETE |
| Production-code drift after P0 | None (docs-only after merge) |

---

## 2. Frozen Model A contract

| Surface | Contract |
|---|---|
| Primary sitemap `<loc>` | Rank Math **default/source** only |
| Localized primary locs | **Not** Model A |
| Sitemap xhtml | AIML overlay when Rank Math emits `<url>` **and** `blog_public` **and** ≥2 public languages **and** SB11 SEO set ≥2 |
| Canonical emission | Rank Math / WP |
| Canonical value | AIML filters when invoked |
| Hreflang | AIML / SB11 / EffectiveUrl |
| x-default | Source/default absolute URL |

### Ownership

| Artifact | Owner |
|---|---|
| Sitemap XML / primary `<loc>` | Rank Math |
| `xhtml:link` / sitemap x-default | AIML `RankMathSitemapOverlay` → SB11 → EffectiveUrl |
| Document hreflang | AIML `DocumentSeoHead` |
| Canonical tag | Rank Math / WP (+ AIML value filter) |

### Compatibility matrix

| Surface | Status |
|---|---|
| Meta text overlays | SUPPORTED |
| Document hreflang | SUPPORTED |
| Canonical value correction | SUPPORTED |
| Canonical tag emission | PARTIAL |
| og:url / locale | SUPPORTED |
| Primary loc localization | UNSUPPORTED (Model A) |
| Sitemap xhtml | SUPPORTED (requires RM entry + overlay gates) |
| Competing providers / loc rewrite | UNSUPPORTED |

### Object-type matrix

Same Model A for overlay types: page, post, product, product_cat, product_tag, category, post_tag, author.

---

## 3. DEV identity (read-only)

| Check | Result |
|---|---|
| `siteurl` | `https://dev.biopentra.eu` |
| `home` | `https://dev.biopentra.eu` |
| HTTP home | 200 |
| `blog_public` | **0** |
| LU state | `on` |
| Production touched | **0** |
| DEV mutations | **0** |

---

## 4. Rank Math sitemap inspection (HTTP GET)

| Endpoint | HTTP | Notes |
|---|---|---|
| `/sitemap_index.xml` | 200 | page, product, product_cat |
| `/page-sitemap.xml` | 200 | **49** `<loc>`; **0** `xhtml:link`; **no** `xmlns:xhtml` |
| `/product-sitemap.xml` | 200 | **0** `xhtml:link`; **no** `xmlns:xhtml` |
| `/sv/...` primary locs | **0** | correct Model A |

Gate B fixtures with active routes remain **absent** from page/product sitemaps (Rank Math inclusion ownership; unchanged PRODUCT GAP observation, not needed once a suitable included entry was found).

---

## 5. G4b controlled probe

### Selected existing `<loc>`

`https://dev.biopentra.eu/a4-nested-gutenberg-fixture/`

### AIML object identity

| Field | Value |
|---|---|
| source_type | post |
| post_type | page |
| source_id | 6419 |
| status | publish |
| path | `/a4-nested-gutenberg-fixture/` |

### Target-language / discoverability

| Field | Value |
|---|---|
| Languages | `en` (default, published), `sv` (published) |
| Active routes for 6419 | **0** |
| Overlay emission gate | `should_emit_alternates()` requires `blog_public` → **false** |

### Actual XML (bounded excerpt)

```xml
<url>
  <loc>https://dev.biopentra.eu/a4-nested-gutenberg-fixture/</loc>
  <lastmod>2026-08-07T18:59:17+00:00</lastmod>
  <image:image>...</image:image>
</url>
```

No `xhtml:link`. No site-wide `xmlns:xhtml`.

### EffectiveUrl / x-default on this entry

Not applicable for emission check: overlay correctly suppressed. Site-wide active routes exist (5 total, Gate B / dogfood objects) but are **not** Rank Math-included; they were not used as the probe subject.

### G4b classification

**EXPECTED OMIT**

Rank Math includes the object. Frozen Model A / ASEOE honesty requires **no** xhtml enrichment when `blog_public=0` (`RankMathSitemapOverlay::should_emit_alternates`). Absence of xhtml on this included entry (and site-wide) is **correct**, not a defect.

Secondary note: this particular object also has no active `sv` route, so even with `blog_public=1` it would not qualify for a discoverable localized alternate.

---

## 6. Classifications

| ID | Classification |
|---|---|
| **G4a** | **EXPECTED CONTRACT** (no localized primary locs) |
| **G4b** | **EXPECTED OMIT** (`blog_public=0`) |
| **G4c** | Contract intact (suite + overlay path); live identity compare N/A under omit |
| Canonical sparse-tag | **EXPECTED / OWNER ABSENT** (unchanged; AIML does not own emission) |

---

## 7. Automated coverage vs residual gaps

| Covered | Gap |
|---|---|
| Model A single `<loc>` + xhtml when gates pass (`AseoeSitemapTest`) | Live `blog_public=1` + RM-included + discoverable object sample (optional later; **not** required to freeze CASE A after EXPECTED OMIT) |
| V151 EffectiveUrl identity for SEO consumers | Gate B fixtures still absent from Rank Math inclusion (Rank Math ownership) |

---

## 8. Final verdict

**P1 G4 CHARACTERIZATION: NO SUPPORTED-CONTRACT DEFECT**

| Impact | Result |
|---|---|
| Schema | NONE |
| TARGET | NONE (remains 8) |
| Architecture | NONE — Model A not reopened |
| Corrective boundary | N/A |
| P2 disposition | **PROMOTE** as next planning candidate |
| Release | **NOT AUTHORIZED** (`MILESTONE CLOSURE != RELEASE CLOSURE`) |
| Implementation | **NONE** |
| Production | **UNTOUCHED** |
| DEV mutation | **0** |

---

## 9. Exact next task

Separately authorize **P2 Jobs / stale operator literacy planning**.
