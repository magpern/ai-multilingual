# MSEO.4 Closure — WooCommerce Localized Permalink Hardening

| # | Field | Value |
|---|---|---|
| 1 | Initial main HEAD | `bcaf5c1e9016ae7dfe400c8e56af976903c6d9f3` |
| 2 | Freeze / materialization SHA | `7d77f628ea19c393730024237f23543a39032b46` |
| 3 | Authoritative plan | [MSEO4_WOOCOMMERCE_LOCALIZED_PERMALINK_HARDENING_IMPLEMENTATION_PLAN.md](MSEO4_WOOCOMMERCE_LOCALIZED_PERMALINK_HARDENING_IMPLEMENTATION_PLAN.md) |
| 4 | Implementation branch | `feature/mseo4-woocommerce-localized-permalink-hardening` |
| 5 | Implementation baseline SHA | `944d0ac9e` — [MSEO4_IMPLEMENTATION_BASELINE.md](MSEO4_IMPLEMENTATION_BASELINE.md) |
| 6 | ADR-0023 | Accepted |
| 7 | STATE | B |
| 8 | TARGET | 8 |
| 9 | Schema / migration | TARGET 8; **no migration**; ADR-0023 sufficient |
| 10 | Work packages | MSEO4.0–MSEO4.6 **PASS** |
| 11 | Implementation commits | `aedf34915`, `13418fb66` (+ baseline/evidence) |
| 12 | Feature HEAD before review | `aedf34915` |
| 13 | Final reviewed feature HEAD | `13418fb66` |
| 14 | M4R1–M4R68 | PASS — [MSEO4_IMPLEMENTATION_EVIDENCE.md](MSEO4_IMPLEMENTATION_EVIDENCE.md) |
| 15 | M4AC1–M4AC55 | PASS — same evidence |
| 16 | Woo source authority | `get_permalink` via `WooProductCategoryAuthority` |
| 17 | Woo category authority | Capture `wc_product_post_type_link_product_cat` (try/finally) |
| 18 | Capture cleanup | Proven normal + Throwable paths |
| 19 | Deterministic-filter | Dual resolve probe; nondeterministic → source fallback |
| 20 | Source-shape disagreement | Fail closed (`source_fallback_authority_disagreement`) |
| 21 | Product path authority | `WooProductPathBuilder` sole `%product_cat%` concat |
| 22 | `%product_cat%` supported shapes | C/E/F after admit; A/B/D plain remain MSEO.2 |
| 23 | Uncategorized | Woo-owned placeholder; leaf localize only |
| 24 | Dependency discovery | Over-inclusive T+descendants (`WooProductDependencyRepository`) |
| 25 | Frontier namespaces | `post`/`term`; `product_dep/<id>`; `woo_product_config/1` |
| 26 | Frontier claim isolation | Typed `find_workable`; Hierarchy vs Woo jobs |
| 27 | Bounded worker | `MAX_PER_TICK = 100`; indexed `product_id > last` |
| 28 | 1,000-product proof | Structural multi-tick (bound + cursor); CI scale algorithmic |
| 29 | Fingerprint | Route-semantic Settings hash; array-aware WC structure |
| 30 | Config generation | Fingerprint mismatch → EffectiveUrl source |
| 31 | Config recognition | CURRENT_LOCALIZED → one redirect to EffectiveUrl |
| 32 | Self-redirect protection | `is_same_normalized_url` guard |
| 33 | History | Direct-to-current; `HISTORY_MAX = 5`; strict unchanged no-op |
| 34 | Collision | Hold prior; degraded frontier; no leaf auto-suffix on rematerialize |
| 35 | Candidate mutation audit | Maintenance does not mutate candidate/post_name/term.slug |
| 36 | Variation behavior | Parent EffectiveUrl only; no variation routes |
| 37 | Endpoint exclusion | Cart/checkout/account deny fragments preserved |
| 38–42 | SEO consumers | EffectiveUrl authority; Model A regression (canonical/hreflang/sitemap/home_url) |
| 43 | Capability verification | Epoch 2; `product_category_permalink` shape; fingerprint on admit |
| 44 | First-public / admission | End MSEO4.5; model B global admit + per-object fallback |
| 45 | Per-object fallback taxonomy | synchronized / source_fallback_* / skipped_* / invalid_data / system_error |
| 46 | Diagnostics / admin | Existing status UX; Settings fingerprint preserved |
| 47 | PluginGuard | PASS (`test_mseo4_woo_product_permalink_boundaries`) |
| 48 | Performance | No frontend catalog scan; indexed routes; ≤100/tick |
| 49 | Browser acceptance | Documented checklist; CI authoritative |
| 50 | PHPCS | PASS (733 files / feature CI + main CI) |
| 51 | Unit | 929 PASS (2 skipped) |
| 52 | Integration | 876 PASS (3 skipped) local; feature+main CI green |
| 53 | Quality / baseline | PASS |
| 54 | Build / ZIP | PASS (CI build job) |
| 55 | Review defects | PluginGuard `$wpdb` in job; fingerprint never refreshed after config change |
| 56 | Review fixes | `WooProductDependencyRepository`; invalidate/restore fingerprint on woo_config |
| 57 | Independent verdict | **MSEO.4 IMPLEMENTATION REVIEW: PASS** |
| 58 | Feature PR | https://github.com/magpern/ai-multilingual/pull/38 |
| 59 | Feature CI | GREEN — https://github.com/magpern/ai-multilingual/actions/runs/31888124033 |
| 60 | Merge SHA | `c725231e4422a97ea2d22fcf5a46c36b5630ef8f` |
| 61 | Fresh main CI | GREEN — https://github.com/magpern/ai-multilingual/actions/runs/31888206554 |
| 62 | Closure SHA | docs-only commit on `main` immediately after merge CI green (this file) |
| 63 | Final main HEAD | `main` == `origin/main` after closure push |
| 64 | Version | **1.4.0** |
| 65 | TARGET | **8** |
| 66 | Clean / main==origin | Required after closure push |
| 67 | Tag / release / deploy | **none** |
| 68 | Limitations / debt | Pretty `%product_cat%` harness may skip without rewrite parity; 1k scale algorithmic; translated Woo bases / variation routes / pretty layered-nav deferred |
| 69 | MSEO.5 status | **NOT STARTED** |
| 70 | Exact next step | Plan/implement **MSEO.5** only after explicit start; do not start here |

## Work packages

| WP | Result |
|---|---|
| MSEO4.0 Characterization | PASS |
| MSEO4.1 Product path / category authority | PASS |
| MSEO4.2 Frontier + dependency maintenance | PASS |
| MSEO4.3 Route rematerialization | PASS |
| MSEO4.4 Public-URL consumer hardening (gated) | PASS |
| MSEO4.5 Verify + admit | PASS |
| MSEO4.6 Hardening + evidence | PASS |

## Frozen architecture retained

Woo `get_permalink` is absolute source truth. AIML does not clone deepest-category selection. Capture filters are request-local with guaranteed cleanup. Config transitions fail closed for generation and one-hop redirect for recognition. TARGET remains 8.

## Next milestone

**MSEO.5 NOT STARTED.** Stop.
