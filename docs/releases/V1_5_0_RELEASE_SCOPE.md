# AI Multilingual v1.5.0 — Release Scope Audit

**Status:** PREPARATION  
**Date:** 2026-08-15  
**Preparation branch:** `release/v1.5.0-preparation`  
**Baseline main HEAD:** `3e3e9d7510bf8a00caab462647870a6cd512f54e`  
**Previous intentional release:** `v1.4.0` @ `ee49cc906babfd34b67fd0998f1eb7553a03358f`  
**Schema:** Migrator `TARGET = 8` (**unchanged** — no migration in this release)  
**Decision:** **RELEASE VERSION DECISION: 1.5.0**

## A. Product capabilities shipped since v1.4.0

### Multilingual SEO & Localized URLs (MSEO.0–MSEO.5) — program closeout release

| Milestone | Shipped |
|---|---|
| **MSEO.0** | TARGET 8 foundation; PathCanonicalizer; PathHash; repositories; EffectiveUrl scaffold; ADR-0023 |
| **MSEO.1** | Slug candidates; active routes; eligibility; Workspace slug; RoutePublicationService |
| **MSEO.2** | Activatable localized URLs; recognition; history; SEO graph; activation machine; flat/plain objects |
| **MSEO.3** | Hierarchical pages/terms; frontier reindex; capability admission |
| **MSEO.4** | Woo `%product_cat%` permalink hardening; fingerprint; product_dep frontiers |
| **MSEO.5** | Program PluginGuard; regression pack; browser acceptance checklist; release/dogfood (Gates B–D) |

### Already in v1.4.0 (still claimed)

- TSC Complete (Extension API v1)  
- TIQ Complete · OTL Complete  

## B. Schema / persistence

| Item | Status |
|---|---|
| `Migrator::TARGET` | **8** (unchanged in this release) |
| New migration in v1.5.0 | **None** |
| Localized URL tables | Introduced at MSEO.0 (TARGET 8); already on sites that ran MSEO.0+ |

## C. Must NOT claim as shipped

| Item | Disposition |
|---|---|
| Translated rewrite bases | Deferred |
| Woo endpoint name localization | Deferred |
| Distinct variation routes | Unsupported |
| Pretty layered-nav | Deferred |
| Provider slug generation | Unsupported |
| Competing sitemap generator | Unsupported |
| Fuzzy URL matching | Unsupported |
| Custom CPT/taxonomy general admission | Deferred |
| Localized-slug preview | Deferred |
| PRODUCTION DEPLOYMENT | Not part of this release |

## D. Upgrade implications (v1.4.0 → v1.5.0)

1. Install `ai-multilingual-1.5.0.zip` over previous plugin directory.  
2. Activate / visit wp-admin so `maybe_migrate()` runs (no-op at TARGET 8 if already 8).  
3. Confirm `aiml_db_version` remains **8**.  
4. Localized URLs remain **OFF** until administrator enables them.  
5. Publication defaults unchanged (gate OFF, mode manual).  

## Authoritative version sources for 1.5.0

| Source | Value |
|---|---|
| Plugin header `Version:` | 1.5.0 |
| `AIML_VERSION` | 1.5.0 |
| `readme.txt` Stable tag | 1.5.0 |
| Package name | `ai-multilingual-1.5.0.zip` |
| Tag | `v1.5.0` |
