# MSEO.2 Closure — Public Localized URL Routing & SEO Graph Activation

| Field | Value |
|---|---|
| Initial main HEAD | `88ac74cc3b1320af5359b190b32cacaf79f92bff` |
| Freeze / materialization SHA | `feb2658da878ef955a5d44cb36995f98393c0bf2` |
| Authoritative plan | [MSEO2_PUBLIC_LOCALIZED_URL_ROUTING_SEO_GRAPH_IMPLEMENTATION_PLAN.md](MSEO2_PUBLIC_LOCALIZED_URL_ROUTING_SEO_GRAPH_IMPLEMENTATION_PLAN.md) |
| Implementation baseline | [MSEO2_IMPLEMENTATION_BASELINE.md](MSEO2_IMPLEMENTATION_BASELINE.md) |
| Implementation evidence | [MSEO2_IMPLEMENTATION_EVIDENCE.md](MSEO2_IMPLEMENTATION_EVIDENCE.md) |
| Implementation branch | `feature/mseo2-public-localized-url-routing` |
| Baseline SHA | `f32610c45` |
| Feature HEAD (reviewed) | `7e3b6c4d3` |
| Feature PR | https://github.com/magpern/ai-multilingual/pull/36 |
| Merge SHA | `50ace1999ca0c2e20796add0e0d1b193821e23d4` |
| ADR-0023 | Accepted |
| STATE | B |
| Version | 1.4.0 (unchanged) |
| TARGET | 8 (unchanged; no migration) |
| Schema | TARGET 8 only |
| Fresh main CI | GREEN — https://github.com/magpern/ai-multilingual/actions/runs/31840198618 (`50ace1999`) |
| Tag / release / deploy | **none** |
| MSEO.3 | **NOT STARTED** |

## Work packages

| WP | Result |
|---|---|
| MSEO2.0 Characterization + foundation | PASS |
| MSEO2.1 Inbound routing + redirects | PASS |
| MSEO2.2 EffectiveUrl + home_url + SB11 | PASS |
| MSEO2.3 Canonical / hreflang / sitemap Model A | PASS |
| MSEO2.4 Activation verification job | PASS |
| MSEO2.5 Settings UI / state machine | PASS |
| MSEO2.6 PluginGuard / hardening | PASS |

## Contracts delivered

- `RouteRecognitionContext`: NONE / CURRENT_LOCALIZED / HISTORICAL_LOCALIZED / SOURCE_PATH (request-local)
- CURRENT_LOCALIZED ON → 200, no AIML self-redirect
- SOURCE_PATH ON → exactly one 301 via `filter_redirect_canonical`
- CURRENT_LOCALIZED OFF/ACTIVATING/FAILED → exactly one early 302
- History → early-terminal 301/302
- Inactive current → not PathRecognized
- `EffectiveUrlService` sole outbound localization authority
- `home_url` exact active `source_path` admission + denylist + anti-recursion
- Shared `ObjectLanguagePublicEligibility::is_discoverable`
- Sitemap Model A (`<loc>` unchanged; xhtml alternates when discoverable)
- Activation non-mutating taxonomy ADMITTED / SKIPPED_* / INVALID_DATA / CONFLICT / SYSTEM_ERROR
- Admin Localized URLs toggle only after safe stack

## Matrices

- **M2R1–M2R54:** PASS (supported); deferred hierarchy/terms/Woo-category → MSEO.3/4
- **M2AC1–M2AC55:** PASS (see evidence)

## Validation

| Gate | Result |
|---|---|
| Local PHPCS | PASS |
| Local unit | 929 PASS (2 skipped) |
| Local integration | 852 PASS (2 skipped) |
| Fresh main CI | GREEN — https://github.com/magpern/ai-multilingual/actions/runs/31840198618 |
| Quality / baseline | PASS |
| ZIP audit | PASS |
| Independent review | **MSEO.2 IMPLEMENTATION REVIEW: PASS** |
| Feature PR CI | GREEN (phpcs, unit, integration, quality, build) |

## Review defects → fixes

1. Hreflang emptied by discoverable-only gate → SEO advertisement omit policy (SA7 when OFF / no route; omit active+!bundle)
2. Sitemap alternate policy divergence → `for_path(..., true)`
3. Preview localized via `home_url` → `prefix_url_without_localization`
4. SB11 post resolve via filtered `home_url` → `raw_home()`
5. Broad Throwable catch / Router ctor regressions → PluginGuard-safe + `make_router()`

## Limitations / debt

- Hierarchy / terms / Woo category permalink localization deferred (MSEO.3/4)
- Browser smoke local/non-CI per convention
- History OFF source-path helper is page-URI based for posts

## Final state checklist

- [x] Plan frozen/materialized
- [x] MSEO2.0–MSEO2.6 complete
- [x] M2R / M2AC accounted
- [x] Public localized URLs for supported shapes
- [x] Redirect contracts
- [x] Discoverability / SEO graph
- [x] Activation non-mutating + UI
- [x] PluginGuard / PHPCS / unit / integration / quality / ZIP
- [x] Review PASS
- [x] PR CI green + merge
- [x] Fresh main CI green
- [x] Closure pushed; main clean == origin
- [x] Version 1.4.0 / TARGET 8 / no migration / no tag/release/deploy
- [x] MSEO.3 NOT STARTED

**Next step:** MSEO.3 planning only when authorized — do not start here.
