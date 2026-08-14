# MSEO.0 Implementation Evidence

**Branch:** `feature/mseo0-localized-url-foundation`  
**Baseline:** `074a02b2834703415d49e59e5d3dfa454c3004dd` (frozen main)  
**Implementation baseline:** `47a419173` (`docs/plans/MSEO0_IMPLEMENTATION_BASELINE.md`)  
**ADR:** [0023-localized-url-overlay-architecture.md](../adr/0023-localized-url-overlay-architecture.md) (**Accepted**)  
**STATE:** B · **TARGET:** 7 → **8** · **Version:** 1.4.0 (unchanged)  
**Review:** **MSEO.0 IMPLEMENTATION REVIEW: PASS**

## R1–R7 refinements

| Ref | Evidence |
|---|---|
| R1 | `Schema::create_slug_routes()` — `activated_at DATETIME NULL`, `route_status DEFAULT 'inactive'`, route `slug_origin DEFAULT 'generated'`; translations `slug_origin DEFAULT ''` |
| R2 | `SlugRouteRepository::save(RouteRecord)` derives hashes; no public path+hash pair API |
| R3 | `PathHash::hex()` + SQL `UNHEX(%s)`; integration NUL-byte round-trip in `MseoRepositoryTest` |
| R4 | `PathCanonicalizer::canonicalize(string $path): CanonicalPath` — path only; no `sanitize_title()` |
| R5 | `aiml_slug_reindex_frontier` UNIQUE `parent_frontier`; `checkpoint_json`; coalescing in `ReindexFrontierRepository` |
| R6 | TARGET literal audit below |
| R7 | `EffectiveUrlService(Settings $settings)` only — not wired in `Plugin.php` |

## WP0–WP10 mapping

| WP | Deliverable | Files / tests |
|---|---|---|
| WP0 | Characterization | Preflight; baseline doc |
| WP1 | TARGET 8 schema | `Schema.php`, `Migrator.php` · `MseoSchemaTest` |
| WP2 | PathHash | `PathHash.php` · `PathHashTest` |
| WP3 | PathCanonicalizer | `PathCanonicalizer.php`, `CanonicalPath.php` · `PathCanonicalizerTest` |
| WP4 | Repositories | `SlugRouteRepository.php`, `RouteHistoryRepository.php`, `ReindexFrontierRepository.php` · `MseoRepositoryTest` |
| WP5 | EffectiveUrlService | `EffectiveUrlService.php` · `EffectiveUrlServiceTest` |
| WP6 | Settings | `Settings.php` · `SettingsSanitizeTest` |
| WP7 | PluginGuard | `PluginGuardTest::test_mseo0_inert_foundation_boundaries`, `Mseo0DeferredGuardTest` |
| WP8 | TARGET audit | See §TARGET literal audit |
| WP9 | Integration tests | `MseoSchemaTest`, `MseoRepositoryTest`; full suite green |
| WP10 | Validation | PHPCS, unit 928, integration 802, quality baseline PASS, ZIP 1.4.0 TARGET 8 |

## M0AC1–M0AC20

| AC | Status | Evidence |
|---|---|---|
| M0AC1 | PASS | `Migrator::TARGET === 8` |
| M0AC2 | PASS | `MseoSchemaTest::test_upgrade_from_target_seven_is_idempotent` |
| M0AC3 | PASS | Bootstrap migrate → TARGET 8 |
| M0AC4 | PASS | Double `maybe_migrate()` idempotent |
| M0AC5 | PASS | `slug_origin` column default `''` |
| M0AC6 | PASS | UNIQUE `object_language` |
| M0AC7 | PASS | BINARY(32) SHA-256 |
| M0AC8 | PASS | Full-path verify; fail closed on mismatch |
| M0AC9 | PASS | Malformed encoding rejected |
| M0AC10 | PASS | `localized_urls_state` default `off` |
| M0AC11 | PASS | No SettingsPage control |
| M0AC12 | PASS | Router unchanged (guard + deferred tests) |
| M0AC13 | PASS | home_url prefix-only (regression suite) |
| M0AC14 | PASS | No rewrite rules (PluginGuard) |
| M0AC15 | PASS | No post_name/term slug mutation |
| M0AC16 | PASS | SB11/canonical/hreflang/sitemap regression green |
| M0AC17 | PASS | No provider calls in `src/Routing/` |
| M0AC18 | PASS | `AseoaDeferredSlugGuardTest` evolved, green |
| M0AC19 | PASS | `Schema::all_tables()` drop-safe order |
| M0AC20 | PASS | Full test suites green |

## TARGET literal audit (R6)

**Updated to canonical (8 / `Migrator::TARGET`):** `TranslationMemorySchemaTest`, `PublicationSchemaTest`, `ReviewSchemaTest`, `JobsSchemaTest`, `GlossarySchemaTest`, `PublicationSeoWooAcceptanceTest`, ASEO `*DeferredGuardTest`, `AseoaDeferredSlugGuardTest`, `MseoSchemaTest`, `Mseo0DeferredGuardTest`, `PluginGuardTest::test_tsc1_*`, `PluginGuardTest::test_mseo0_*`

**Historical retained (comments / fixtures):** OTL/TSC PluginGuard milestone comments "shipped at TARGET 7"; `MseoSchemaTest` upgrade fixture starts at option `7`; release docs unchanged

**Unrelated unchanged:** `Tsc3AttributeLabelTest` numeric 7, QA `EMPTY_TARGET` constants

## Zero public URL behavior change

- No Router/EffectiveUrlService wiring
- No redirects, rewrite rules, canonical/hreflang/sitemap/switcher changes
- Full integration regression suite: **802 tests PASS**

## Validation results

| Gate | Result |
|---|---|
| PHPCS | PASS (warnings only on PreparedSQL annotations in repositories) |
| Unit | **928** tests, **3005** assertions, 2 skipped |
| Integration | **802** tests, **32360** assertions, 2 skipped |
| Quality baseline | PASS (60 cases) |
| ZIP audit | PASS — `ai-multilingual-1.4.0.zip`, version **1.4.0**, TARGET **8** |

## Independent review

Reviewed code/SQL against MSEO.0 plan and ADR-0023. No blocking defects found.

**MSEO.0 IMPLEMENTATION REVIEW: PASS**

## Limitations / debt

- MSEO.1 candidate lifecycle not started
- No localized URL admin UI until MSEO.2
- EffectiveUrlService passthrough only
- Frontier traversal engine deferred to MSEO.3

**MSEO.1 NOT STARTED**
