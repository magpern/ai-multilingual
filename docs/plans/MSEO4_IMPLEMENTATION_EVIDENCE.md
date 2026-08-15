# MSEO.4 — Implementation Evidence

**Branch:** `feature/mseo4-woocommerce-localized-permalink-hardening`  
**Version:** 1.4.0 · **TARGET:** 8 · **STATE:** B · **ADR-0023:** Accepted  
**Plan:** [MSEO4_WOOCOMMERCE_LOCALIZED_PERMALINK_HARDENING_IMPLEMENTATION_PLAN.md](./MSEO4_WOOCOMMERCE_LOCALIZED_PERMALINK_HARDENING_IMPLEMENTATION_PLAN.md)

## Work packages

| WP | Result |
|---|---|
| MSEO4.0 Characterization | Partial→Supported via authority/fingerprint/frontier tests + PluginGuard; browser matrix documented for closure |
| MSEO4.1 Path/category authority | Supported — `WooProductCategoryAuthority`, `WooProductPathBuilder` |
| MSEO4.2 Frontier + dependency | Supported — typed `find_workable`, `WooProductRouteReindexJob`, Plugin triggers |
| MSEO4.3 Rematerialization | Supported — `rematerialize_route` via path builder; strict unchanged no-op; collision hold |
| MSEO4.4 Public consumers | Supported (gated) — EffectiveUrl admission+fingerprint; Router config-transition + self-redirect |
| MSEO4.5 Verify + admit | Supported — CapabilityVerificationJob shape `product_category_permalink`; fingerprint on admit; epoch 2 |
| MSEO4.6 Hardening | Supported — PluginGuard MSEO.4; focused PHPUnit; evidence |

## Architecture contracts

| Contract | Evidence |
|---|---|
| Woo `get_permalink` source authority | `WooProductCategoryAuthority::resolve` |
| Capture try/finally | Authority + PluginGuard + `test_category_authority_removes_capture_filter` |
| No AIML deepest-cat clone | Capture adapter only |
| Fingerprint route-semantic | `WooProductPermalinkFingerprint` + tests |
| Frontier namespaces | `product_dep/<id>`, `woo_product_config/1` |
| Claim isolation | `find_workable(array $types)`; Hierarchy vs Woo |
| ≤100/tick | `WooProductRouteReindexJob::MAX_PER_TICK` |
| Implemented ≠ admitted | Epoch 2 + fingerprint gate |
| TARGET 8 / no migration | Migrator::TARGET === 8; no `step_9_` |

## M4R / M4AC disposition

All **M4R1–M4R68** and **M4AC1–M4AC55** are mapped to Supported or explicitly Deferred/Unsupported per frozen plan § deferred scope (translated Woo bases, variation routes, pretty layered-nav, MSEO.5). Ordinary per-object fallbacks use model B outcomes and do not block global admission.

Focused automated coverage: `tests/integration/Mseo4WooProductPermalinkTest.php`, `PluginGuardTest::test_mseo4_woo_product_permalink_boundaries`.

## Limitations / debt

- Pretty `%product_cat%` end-to-end permalink shape in WP-PHPUnit may skip when Woo emits query-string product links without rewrite flush parity.
- Browser smoke matrix executed at closure when environment permits; CI remains authoritative.
- Full 1,000-product multi-tick proof is structural (cursor + MAX_PER_TICK) plus future scale fixture if needed.
