# TSC.3 Implementation Evidence

**Status:** **COMPLETE** on `main` — merge `d7a7545d2b64ee188058ada8acfed8fefd5b1dea`  
**Branch (merged):** `feature/tsc3-woocommerce-extended-translation-surfaces`  
**Authoritative plan:** [TSC3_WOOCOMMERCE_EXTENDED_TRANSLATION_SURFACES_IMPLEMENTATION_PLAN.md](TSC3_WOOCOMMERCE_EXTENDED_TRANSLATION_SURFACES_IMPLEMENTATION_PLAN.md)  
**Baseline:** [TSC3_IMPLEMENTATION_BASELINE.md](TSC3_IMPLEMENTATION_BASELINE.md)  
**Validation log:** [TSC3_VALIDATION_LOG.md](TSC3_VALIDATION_LOG.md)

## Work packages

| WP | Status | Evidence |
|---|---|---|
| TSC3.0 | COMPLETE | Identity helpers, matrices preserved in plan; `AttributeLabelIdentity` |
| TSC3.1 | COMPLETE | Shop extract of global labels; product extract skips taxonomy; compat retain |
| TSC3.2 | COMPLETE | Exact overlay algorithm + visitor guards |
| TSC3.3 | COMPLETE | Attribute CRUD dirty shop; `Store::rehost_segments`; shop option hook |
| TSC3.4 | COMPLETE | `IntegrationSegmentAuthority` + `WooAttributeLabelAuthority`; OTL/Jobs/Publication facts |
| TSC3.5 | COMPLETE | Allowlisted email settings → checkout dirty; disposition remains PARTIAL |
| TSC3.6 | COMPLETE | Writer deny; PluginGuard; retain; no dual-write |
| TSC3.7 | COMPLETE | This evidence pack + tests + validation log |

## Primary implementation files

| File | Role |
|---|---|
| `src/Integration/WooCommerce/AttributeLabelIdentity.php` | Canonical/compat key grammar |
| `src/Integration/WooCommerce/WooCommerceIntegration.php` | Extract/overlay |
| `src/Integration/WooCommerce/WooCommerceInvalidation.php` | Invalidation + rehost trigger |
| `src/Integration/WooCommerce/WooAttributeLabelAuthority.php` | Facts (`manage_product_terms`) |
| `src/Integration/IntegrationSegmentAuthority.php` | Facts interface |
| `src/Integration/IntegrationSegmentAuthorityRegistry.php` | Registry |
| `src/Translation/Store.php` | `rehost_segments` |
| `src/Workspace/Operator/AllowedActionsResolver.php` | Writer deny + caps |
| `src/Workspace/Operator/OperationsBulkCoordinator.php` | Bulk write deny |
| `src/Workspace/WorkspaceService.php` | Persist write deny |
| `src/Workspace/SegmentAssembler.php` | Retain + can_edit honesty |
| `src/Surface/RequestLocalInvalidationCoordinator.php` | Retain merge |
| `src/Translation/Publication/PublicationService.php` | `is_row_source_public` |
| `src/Plugin.php` | Wiring |

## Tests

| Suite | Path |
|---|---|
| Unit identity/extract/overlay | `tests/unit/Integration/Tsc3AttributeLabelTest.php` |
| Unit rehost/email allowlist | `tests/unit/Translation/StoreRehostSegmentsTest.php` |
| Integration lifecycle | `tests/integration/Tsc3AttributeLabelLifecycleTest.php` |
| PluginGuard | `tests/integration/PluginGuardTest.php` (`assert_tsc3_invariants`) |

## Validation commands / results

```text
docker run --rm -v "$PWD":/app -w /app php:8.1-cli ./vendor/bin/phpunit -c phpunit.xml.dist
→ OK (871 tests, 2 skipped)

Feature CI: https://github.com/magpern/ai-multilingual/actions/runs/31644425107 — SUCCESS
Fresh main CI: https://github.com/magpern/ai-multilingual/actions/runs/31644551374 — SUCCESS
```

## WC1–WC40 / AC1–AC38

All Supported/Partial requirements implemented per frozen plan. Email stale remains **PARTIAL** (invoice paid + refunded full/partial keys uncovered). Local attribute values remain Deferred. Variation machine identity never translated.

## Known limitations / debt

- Compatibility taxonomy P5/P7 retained read-only (no auto-adoption)
- B1/B2 and checkout email host reassignment remain historical debt
- Email PARTIAL gaps unchanged by design
- Bounded browser smoke remains local/non-CI

## Freeze reminders

STATE A · TARGET 7 · no ADR · version 1.3.0 · no tag · TSC.4+ NOT STARTED
