# A.SEOf Evidence — Admission Matrix

**Status:** Planning freeze — evidence-driven dispositions
**Baseline:** `main` @ `fbc719a78`
**Rule:** Every SF1–SF15 item started as **Candidate**. Disposition only after evidence.

---

## Final dispositions

| ID | Topic | Disposition | Evidence basis | ADR gate |
|---|---|---|---|---|
| SF1 | SEO health summary | **Supported** | Aggregate SF13 check results; BlockHealth summary precedent | None new |
| SF2 | Canonical validation | **Supported** | SB11 + DocumentSeoHead / Rank Math canonical seams; honesty when HTML omitted | A.SEOb |
| SF3 | Hreflang reciprocity validation | **Supported** | SB11 public set + DocumentSeoHead emission | SB11 |
| SF4 | Language-relationship validation | **Supported** | Direct SB11 contract checks | SB11 / ADR-0008 |
| SF5 | OpenGraph / Twitter validation | **Supported** | Rank Math OG hooks + SB11 alternates; Deferred image/card not claimed | A.SEOd |
| SF6 | Sitemap / discovery validation | **Supported** | Rank Math owner + overlay honesty; no SE11 invent | A.SEOe |
| SF7 | Robots / indexability validation | **Supported** | Read WP/RM/Woo; never force indexable; blog_public honesty | A.SEOe |
| SF8 | Preview leakage detection | **Supported** | ADR-0008 + SB11 `include_preview=false`; live leakage 0 | ADR-0008 |
| SF9 | Redirect-loop / routing health | **Supported** | Bounded detect/report; `/sv/` home loop case; no Router fix | None new |
| SF10 | Duplicate/conflicting ownership detection | **Supported** | Ownership-aware; dual title foreign attribution | None new |
| SF11 | WooCommerce SEO validation | **Supported** | Product/product_cat via upstream admitted surfaces | A.SEOc–e |
| SF12 | Rank Math compatibility / availability | **Supported** | Existing Integration compatibility status | Integration API v1 |
| SF13 | Machine-readable diagnostics contract | **Supported** | Read-only snapshot/DTO; no persistence; ≠ SE11/SD12 | None new |
| SF14 | Admin-facing SEO diagnostics UI | **Supported** | Thin Multilingual UI over SF13/SF1 only (UI rule) | None new |
| SF15 | External search-engine verification readiness | **Partially Supported** | Advisory checklist only; API/credentials/submit Deferred | Prefer deferral |

---

## SF14 UI rule (frozen)

Admin UI is presentation-only over SF13/SF1. No independent SEO evaluation, crawling, health state, or scoring semantics. CLI/REST/admin share one result model.

---

## Explicitly not Supported

| Topic | Disposition | Why |
|---|---|---|
| Invented SE11 SitemapDiscovery | Deferred / forbidden | A.SEOe freeze |
| Invented SD12 SocialMeta | Deferred / forbidden | A.SEOd freeze |
| SEO Jobs / persistent crawl store | Deferred | No architecture; prefer BlockHealth-style sync |
| Search Console API / auto-submit | Deferred | SF15 Partial |
| Fixing `/sv/` home 301 loop | Out of wave | Router/front-page debt |
| Annexing dual `<title>` ownership | Unsupported | Foreign pre-existing |
| Second SEO emitter pipeline | Unsupported | Parent hard boundary |

---

## Supported set (frozen for implementation authorization)

**Supported:** SF1–SF14
**Partially Supported:** SF15 (advisory readiness only)
**Deferred portions:** SF15 API/credentials/submit; SE11/SD12 remain Deferred upstream
