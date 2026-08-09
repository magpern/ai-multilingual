# AI Multilingual v1.1.0 — Release Scope Audit

**Date:** 2026-08-09  
**Preparation branch:** `release/v1.1.0-preparation`  
**Baseline main HEAD:** `7a30a33e867463a58ce7eaa1540624a3690c69fc`  
**Schema:** Migrator `TARGET = 6` (unchanged)  
**Decision:** TARGET RELEASE VERSION = **1.1.0** (first intentional/public release)

## Historical version/tag findings

| Artifact | Finding |
|---|---|
| Tag `v1.0.0` (2026-08-06) | Exists; GitHub Release “Latest” points here |
| Tag `v0.1.0` | Exists |
| Intentional public release | **None prior to 1.1.0** — historical tags/metadata must not be read as a completed public launch |
| Tag `v1.1.0` | **Absent** (must remain absent until a separate publish task) |

Post-`v1.0.0` closure tags included in this package (repository evidence):

- `a8-fluentforms-contact-integration-complete`
- `a7a-woocommerce-product-catalog-complete`
- `a7b-woocommerce-archive-chrome-complete`
- `a7c-woocommerce-customer-journey-complete`
- `a7d-woocommerce-customer-emails-complete`
- `a6-wordpress-visitor-chrome-complete`
- `a-seoa-slugs-permalinks-complete` … `a-seof-seo-diagnostics-complete`
- CI & Release Baseline Recovery merged on main (`f900a2f1b…`, docs close `7a30a33e8…`)

No subsequent product milestone has been started after A.SEOf / CI recovery.

## Implemented (Supported) — included in 1.1.0

### Platform core (historical 1.0.0 package, still present)

- Gutenberg leaf translation Store + overlays
- Translator Workspace, TM, Glossary, Review, Background Jobs
- Limited Rollout / GA controls
- OpenAI Chat Completions provider
- REST / WP-CLI / diagnostics surfaces from the platform track

### Visitor / integration (after historical 1.0.0)

| Wave | Tag / evidence | Supported admissions (summary) |
|---|---|---|
| A.8 Fluent Forms | `a8-fluentforms-contact-integration-complete` | Contact form chrome via Integration API v1 |
| A.7a Product & Catalog | `a7a-woocommerce-product-catalog-complete` | Product/catalog overlays (attributes, taxonomies, descriptions per plan) |
| A.7b Archive Chrome | `a7b-woocommerce-archive-chrome-complete` | B1/B2 catalog orderby label overlays |
| A.7c Customer Journey | `a7c-woocommerce-customer-journey-complete` | Checkout / My Account chrome (Supported CJ set) |
| A.7d Customer Emails | `a7d-woocommerce-customer-emails-complete` | CE1–CE6 / CE9–CE10 subject+heading; ADR-0018 |
| A.6 Visitor Chrome | `a6-wordpress-visitor-chrome-complete` | N1 custom nav menu item titles |
| A.SEOa | `a-seoa-slugs-permalinks-complete` | **SA7**, **SA10** only |
| A.SEOb | `a-seob-canonical-hreflang-complete` | SB1–SB11 canonical/hreflang + relationship service |
| A.SEOc | `a-seoc-rankmath-complete` | SC1–SC6 / SC10–SC14 Rank Math overlays |
| A.SEOd | `a-seod-opengraph-complete` | SD1–SD3 / SD5–SD8 / SD11 OG/Twitter text |
| A.SEOe | `a-seoe-sitemaps-complete` | SE1–SE9 / SE12 Rank Math sitemap overlays |
| A.SEOf | `a-seof-seo-diagnostics-complete` | SF1–SF14 bounded diagnostics |
| CI/release baseline | `docs/CI_RELEASE_BASELINE.md` | Green full-repo gates + audited ZIP |

## Partially supported

| Area | Status |
|---|---|
| A.SEOc SC7–SC9 | Partially Supported (validation log) |
| A.SEOd Facebook/Twitter **explicit text** overrides | Partially Supported |
| A.SEOf SF15 | Partially Supported — advisory readiness only; **no** Search Console API automation |

## Deferred / known limitations (current)

Do **not** claim these as shipped:

| Limitation | Evidence |
|---|---|
| Translated leaf slugs (posts/pages/products/terms) + uniqueness/history | A.SEOa SA1–SA6 / SA8–SA9 **Deferred** |
| Translated rewrite bases | A.SEOa Deferred (ADR-0002) |
| Social image / card surfaces SD4 / SD9 / SD10 / SD12 | A.SEOd Deferred |
| Sitemap media / reusable SitemapDiscovery (SE10 / SE11) | A.SEOe Deferred |
| `blog_public=0` suppresses public SEO discovery enrichment honestly | A.SEOe / A.SEOf validation logs |
| Pre-existing `/sv/` front-page 301 self-loop | A.SEOf live finding (`error/self_loop`, router ownership) — not fixed in A.SEOf |
| Pre-existing duplicate product `<title>` (Rank Math / theme) | A.SEOc / A.SEOf — AIML does not emit an extra title tag |
| Diagnostics are bounded, not a site-wide crawler | A.SEOf plan/validation |
| Elementor body translation; nested container block identity | Carry-forward platform limits |
| A.7c Deferred CJ1/CJ2/CJ5; A.7d Deferred CE7/CE8 (+ body gettext etc.) | A.7c / A.7d validation logs |
| A.6 Deferred widget/theme chrome (D1/D2 etc.) | A.6 validation log |
| Host WP-CLI `wp aiml seo status` “restful” conflict | Observed on WP-CLI 2.12 / PHP 8.4; help + service scan still work |

## Upgrade / schema

- `Migrator::TARGET` remains **6**
- No schema step added for the 1.1.0 version bump
- Activation / `admin_init` `maybe_migrate()` remain additive/idempotent
- Store data compatible; no destructive migration; no option reset introduced for release prep

## Public contracts (compatible)

Frozen contracts remain ownership-preserving for 1.1.0:

- Integration API v1
- Store / PluginIdentity
- Router / LanguageContext
- SB11 `LanguageRelationshipService`
- WooCommerce / Rank Math foreign ownership (overlays only)

## Authoritative version sources for 1.1.0

| Source | Value |
|---|---|
| Plugin header `Version:` | 1.1.0 |
| `AIML_VERSION` | 1.1.0 |
| `readme.txt` Stable tag | 1.1.0 |
| Artifact name | `ai-multilingual-1.1.0.zip` |
| Composer package version field | unset (name only) |

## Remaining `1.0.0` references — classification

| Location | Class |
|---|---|
| `CHANGELOG.md` `[1.0.0]` section / `docs/releases/v1.0.0.md` | **historical — leave** |
| `readme.txt` changelog `= 1.0.0 =` | **historical — leave** |
| Closure tags / plan validation logs mentioning 1.0.0 baselines | **historical — leave** |
| `tests/Fixtures/ReferenceIntegration` / `ReferenceIntegrationTest` version strings | **fixture — leave** |
| `WooCommerceIntegration` hook `@since 1.0.0` on foreign-hook docblock | **historical annotation — leave** |
| Plugin header / `AIML_VERSION` / Stable tag | **current — must be 1.1.0** (updated) |
