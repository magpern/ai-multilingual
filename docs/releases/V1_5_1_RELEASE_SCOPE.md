# AI Multilingual v1.5.1 — Release Scope Audit

**Status:** PREPARATION  
**Date:** 2026-08-15  
**Preparation branch:** `release/v1.5.1-preparation`  
**Baseline main HEAD:** `b321bf36cb0affcf3d03f5e4da858b473179eff4`  
**Previous intentional release:** `v1.5.0` @ `03a3a09a7ee4e1a0d7624582dcfe07af90ce89d5`  
**Schema:** Migrator `TARGET = 8` (**unchanged** — no migration)  
**Decision:** **RELEASE VERSION DECISION: 1.5.1** (patch corrective)

## A. Corrective capabilities shipped since v1.5.0

| Defect | Correction |
|---|---|
| **D1** | Bounded `term_link` re-entry; `OutboundLocalizationSuspender` for raw source-path reads; CURRENT_LOCALIZED GET completion restored for Gate B class |
| **D2** | SB11 object identity under CURRENT_LOCALIZED; hreflang ↔ EffectiveUrl agreement |
| **D3a** | Shared SB11 path; og:url ↔ EffectiveUrl where proven divergent |
| **D3b** | Disposition **A** (same D1 family); no separate Woo feature expansion |

Implementation: PR [#43](https://github.com/magpern/ai-multilingual/pull/43) → merge `3ec082f7858d44af33ed95008e3c694c7fdb1570`  
Closure: [V151_LOCALIZED_URL_CORRECTNESS_STABILIZATION_CLOSURE.md](../plans/V151_LOCALIZED_URL_CORRECTNESS_STABILIZATION_CLOSURE.md)  
Evidence: [V151_IMPLEMENTATION_EVIDENCE.md](../plans/V151_IMPLEMENTATION_EVIDENCE.md)

### Regression hardening (Supported)

Source / CURRENT_LOCALIZED / hierarchy / one-hop history / canonical / hreflang / x-default / switcher / sitemap XHTML Model A / Woo localized PDP / LU OFF / no redirect loops.

## B. Schema / persistence

| Item | Status |
|---|---|
| `Migrator::TARGET` | **8** |
| New migration in v1.5.1 | **None** |
| Route / history format | Unchanged |
| Settings defaults | Unchanged |

## C. Must NOT claim as shipped

| Item | Disposition |
|---|---|
| New localized URL capability | Not this release |
| New SEO architecture / Model A redesign | Not this release |
| New Woo permalink feature | Not this release |
| Program B / MSEO.6 | Not started / does not exist |
| Translated rewrite bases | Deferred |
| Woo endpoint name localization | Deferred |
| Distinct variation routes | Unsupported |
| Pretty layered-nav | Deferred |
| Extension API 1.1 | Out of scope |
| Taxonomy operator-completeness UI/API | Out of scope |
| PRODUCTION / DEV deployment in this task | Not authorized |

## D. Upgrade implications (v1.5.0 → v1.5.1)

1. Install `ai-multilingual-1.5.1.zip` over previous plugin directory.  
2. `maybe_migrate()` is a no-op at TARGET 8.  
3. Confirm `aiml_db_version` remains **8**.  
4. Existing active routes/history remain valid.  
5. Localized URLs state/admission unchanged.

## E. V151AC mapping

| AC | Status in this preparation |
|---|---|
| V151AC1–20 | Closed by corrective implementation (AC3 = future DEV GET proof) |
| **V151AC21** | **Target of this preparation** — build/audit `ai-multilingual-1.5.1.zip` |
| **V151AC22** | **NOT EXECUTED** — requires published GitHub Release ZIP on DEV |

## F. Authoritative version sources for 1.5.1

| Source | Value |
|---|---|
| Plugin header `Version:` | 1.5.1 |
| `AIML_VERSION` | 1.5.1 |
| `readme.txt` Stable tag | 1.5.1 |
| Package name | `ai-multilingual-1.5.1.zip` |
| Tag (future) | `v1.5.1` (not created in this preparation) |
