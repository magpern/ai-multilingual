# TSC.4 Implementation Evidence

**Status:** **COMPLETE** on `main` — merge `c4a1e465f1d49a9c59f18083816e3f4ca92dc397`  
**Branch (merged):** `feature/tsc4-gutenberg-coverage-expansion`  
**Authoritative plan:** [TSC4_GUTENBERG_COVERAGE_EXPANSION_IMPLEMENTATION_PLAN.md](TSC4_GUTENBERG_COVERAGE_EXPANSION_IMPLEMENTATION_PLAN.md)  
**Baseline:** [TSC4_IMPLEMENTATION_BASELINE.md](TSC4_IMPLEMENTATION_BASELINE.md)  
**Validation log:** [TSC4_VALIDATION_LOG.md](TSC4_VALIDATION_LOG.md)

## Work packages

| WP | Status | Evidence |
|---|---|---|
| TSC4.0 | COMPLETE | `Tsc4RecursiveCoverageTest`, `NestedGutenbergAdmissionTest` (existing), `Tsc4BlockFieldPairAuthorityTest` |
| TSC4.1 | COMPLETE | `BlockTranslationLookup`, `BlockStructuralAttributeGuard`, `BlockRenderer`, frontend render tests |
| TSC4.2 | COMPLETE | `Tsc4SyncSourceGranularityTest`, `Tsc4BlockTranslationLookupTest`, existing OTL/Jobs paths unchanged |
| TSC4.3 | COMPLETE | `PluginGuardTest::assert_tsc4_invariants`, `Tsc4PerformanceRegressionTest` |
| TSC4.4 | COMPLETE | This evidence pack, validation log, CHANGELOG, `acceptance/tsc4-browser/README.md` |

## Primary implementation files

| File | Role |
|---|---|
| `src/Translation/BlockTranslationLookup.php` | Grammar-wide field lookup (`Contract::is_supported_field`) |
| `src/Block/BlockStructuralAttributeGuard.php` | Fail-closed structural attribute equality after apply |
| `src/Translation/BlockRenderer.php` | Guard integration + revert on structural mismatch |
| `src/Block/BlockRenderLogger.php` | `EVENT_STRUCTURAL_REJECTED` |

## Tests

| Suite | Path |
|---|---|
| Structural guard unit | `tests/unit/Block/BlockStructuralAttributeGuardTest.php` |
| Recursion characterization | `tests/unit/Block/Tsc4RecursiveCoverageTest.php` |
| Pair authority / AC21–AC22 | `tests/unit/Translation/Tsc4BlockFieldPairAuthorityTest.php` |
| Lookup widening | `tests/integration/Tsc4BlockTranslationLookupTest.php` |
| Frontend five fields | `tests/integration/Tsc4BlockFieldRenderTest.php` |
| Stale granularity A1 | `tests/integration/Tsc4SyncSourceGranularityTest.php` |
| Performance | `tests/unit/Block/Tsc4PerformanceRegressionTest.php` |
| PluginGuard | `tests/integration/PluginGuardTest.php` (`assert_tsc4_invariants`) |

## Validation commands / results

```text
PHPCS — PASS
Unit — PASS (889 tests, 2 skipped)
Integration — PASS (750 tests, 2 skipped)
PluginGuard — PASS (incl. TSC.4 invariants)
Quality + baseline — PASS
Build/ZIP — PASS (ai-multilingual-1.3.0.zip)

Feature CI: https://github.com/magpern/ai-multilingual/actions/runs/31735374223 — SUCCESS
Fresh main CI: https://github.com/magpern/ai-multilingual/actions/runs/31735532380 — SUCCESS
```

## GB1–GB25 / AC1–AC22

All Supported requirements implemented per frozen plan. Deferred/Unsupported boundaries unchanged (navigation, query, reusable blocks, FSE, html/shortcode/embed, Elementor, public API). Email/code carry-forward unchanged.

## Known limitations / debt

- Bounded browser smoke is local/non-CI (`acceptance/tsc4-browser/README.md`)
- `core/code` translation-quality risk remains documented carry-forward
- Rich-text `wp_kses_post` equality remains partial at sanitizer layer; structural guard covers attribute bypass only
- No version bump / release authorization as part of TSC.4 closure

## Freeze reminders

STATE A · TARGET 7 · no ADR · version 1.3.0 · no tag · TSC.5/TSC.6 NOT STARTED
