# MSEO.2 Implementation Evidence

**Branch:** `feature/mseo2-public-localized-url-routing`  
**Freeze SHA:** `feb2658da878ef955a5d44cb36995f98393c0bf2`  
**Plan:** [MSEO2_PUBLIC_LOCALIZED_URL_ROUTING_SEO_GRAPH_IMPLEMENTATION_PLAN.md](MSEO2_PUBLIC_LOCALIZED_URL_ROUTING_SEO_GRAPH_IMPLEMENTATION_PLAN.md)  
**Baseline:** [MSEO2_IMPLEMENTATION_BASELINE.md](MSEO2_IMPLEMENTATION_BASELINE.md)  
**Version:** 1.4.0 · **STATE B** · **TARGET 8** (no migration)  
**ADR-0023:** Accepted  
**Review:** **MSEO.2 IMPLEMENTATION REVIEW: PASS** (after review-fix loop)

## WP MSEO2.0–MSEO2.6

| WP | Result | Evidence |
|---|---|---|
| MSEO2.0 | PASS | Characterization via AimlTestCase helpers; Settings localized-url accessors; Woo plain-permalink detection; eligibility scaffolding |
| MSEO2.1 | PASS | `Router` + `RouteRecognitionContext`; CURRENT/HISTORY/SOURCE/OFF contracts; `filter_redirect_canonical` ownership |
| MSEO2.2 | PASS | `EffectiveUrlService` sole outbound authority; `home_url` exact source_path admission; SB11/Switcher |
| MSEO2.3 | PASS | Canonical via EffectiveUrl; hreflang/sitemap Model A via shared `is_discoverable` SEO advertisement policy |
| MSEO2.4 | PASS | `SlugRouteActivationJob` + `SlugRouteActivationVerifier` non-mutating taxonomy |
| MSEO2.5 | PASS | Settings UI Localized URLs Off/Activating/On/Failed; enable/disable/retry O(1) |
| MSEO2.6 | PASS | PluginGuard `test_mseo2_public_routing_boundaries`; TARGET 8; no rewrite/slug mutation |

## M2AC1–M2AC55 → evidence

| AC | Verdict | Location / test |
|---|---|---|
| M2AC1 | PASS | Settings default `localized_urls_state=off` |
| M2AC2 | PASS | SettingsPage `render_localized_urls_settings` after MSEO2.5 |
| M2AC3 | PASS | `LocalizedUrlsActivationService::request_enable` → activating |
| M2AC4 | PASS | Enable only updates settings + enqueues job |
| M2AC5 | PASS | `SlugRouteActivationJob::BATCH_SIZE` + checkpoint |
| M2AC6 | PASS | `test_activation_completes_to_on_with_admitted_routes` |
| M2AC7 | PASS | Failed state + retry path in activation service |
| M2AC8 | PASS | `test_current_localized_on_serves_without_redirect` |
| M2AC9 | PASS | Capability registry + hierarchical skip |
| M2AC10 | PASS | Active before history; `test_inactive_localized_path_is_not_recognized` |
| M2AC11 | PASS | `test_source_path_on_emits_one_localized_301` |
| M2AC12 | PASS | `test_history_on_emits_one_301_to_current_localized` |
| M2AC13 | PASS | `test_current_localized_off_emits_one_302_to_source_slug` |
| M2AC14 | PASS | Inactive ignored; no history invent on deactivate |
| M2AC15 | PASS | CURRENT no self-redirect; single canonical return |
| M2AC16 | PASS | OFF 302 preserves `utm=test` query |
| M2AC17 | PASS | No inbound fragment claims; outbound fragment only if input has it (`filter_home_url`) |
| M2AC18 | PASS | Switcher/hreflang/sitemap share `is_discoverable` boolean |
| M2AC19 | PASS | `current_canonical_url` + DocumentSeoHead |
| M2AC20 | PASS | `test_hreflang_omits_active_route_without_bundle` |
| M2AC21 | PASS | RankMathSitemapOverlay xhtml via `for_path(..., true)`; loc unchanged |
| M2AC22 | PASS | Rank Math absent still valid (hooks skip) |
| M2AC23 | PASS | `test_is_discoverable_true_with_partial_overlay_and_active_route` |
| M2AC24 | PASS | Store `is_publicly_overlay_eligible` does not exclude stale |
| M2AC25 | PASS | `has_overlay_bundle` skips `FIELD_SLUG` |
| M2AC26 | PASS | `test_is_discoverable_false_without_overlay_bundle` |
| M2AC27 | PASS | Discoverable requires active route |
| M2AC28 | PASS | `test_preview_remains_source_slug_when_generation_on` |
| M2AC29 | PASS | PluginGuard / no post_name writes in routing |
| M2AC30 | PASS | Term slug mutation absent; `supports_term` false |
| M2AC31 | PASS | No rewrite rules (AseoaDeferredSlugGuard + PluginGuard) |
| M2AC32 | PASS | Activation/job no provider slug generation |
| M2AC33 | PASS | `test_activation_does_not_mutate_routes` |
| M2AC34 | PASS | EffectiveUrl request-local cache keyed by language+path |
| M2AC35 | PASS | Migrator::TARGET=8 |
| M2AC36 | PASS | Version 1.4.0 |
| M2AC37 | PASS | EffectiveUrl early-exit when state ≠ on |
| M2AC38 | PASS | `find_active_by_localized_path` |
| M2AC39 | PASS | `test_plain_product_permalink_capability_detection` |
| M2AC40 | PASS | UI introduced only with activation stack (PluginGuard) |
| M2AC41 | PASS | `test_home_url_admission_negatives_and_anti_recursion` |
| M2AC42 | PASS | `admit_localized_path` exact source_path hit |
| M2AC43 | PASS | Already-localized early return + anti-recursion |
| M2AC44 | PASS | `test_skipped_unsupported_does_not_fail_activation` |
| M2AC45 | PASS | `test_skipped_not_public_does_not_fail_activation` |
| M2AC46 | PASS | `test_invalid_data_sets_failed` / corrupt hash |
| M2AC47 | PASS | `SYSTEM_ERROR` blocking constant; checkpoint resume |
| M2AC48 | PASS | is_discoverable language/source gates |
| M2AC49 | PASS | CURRENT_LOCALIZED ON 200 / no AIML redirect |
| M2AC50 | PASS | `test_current_localized_activating_and_failed_emit_one_302` |
| M2AC51 | PASS | same |
| M2AC52 | PASS | `test_history_off_emits_one_302_to_source_slug` |
| M2AC53 | PASS | CURRENT + `filter_redirect_canonical` false |
| M2AC54 | PASS | `test_recognition_context_unchanged_by_home_url` |
| M2AC55 | PASS | Request-local context; no DB persistence |

## M2R1–M2R54

All **Supported** requirements PASS via the AC mapping above. **Deferred:** hierarchy / terms / Woo category → MSEO.3/4.

## Review-fix findings

1. **Blocking:** hreflang `discoverable_only` emptied SA7 public set → SEO advertisement omit policy restored (localized only when discoverable; active+!bundle omit; else SA7; OFF keeps A.SEOb)
2. **Blocking:** sitemap used non-SEO `for_path` → aligned to `for_path(..., true)`
3. **Blocking:** Preview localized via `filter_home_url` → `prefix_url_without_localization`
4. **Blocking:** SB11 `url_to_postid(home_url(...))` → `raw_home()`
5. **Material:** expanded Mseo2 tests; SYSTEM_ERROR constant; removed broad Throwable catch (PluginGuard)
6. **Regression:** Router ctor arity broke A.SEOa/OTL4 tests → `make_router()`; A.SEOa deferred guard updated for MSEO.2 recognition ownership

## Validation (local)

| Gate | Result |
|---|---|
| PHPCS | PASS (full tree) |
| Unit | 929 tests / 3018 assertions PASS (2 skipped) |
| Integration | 852 tests / 33560 assertions PASS (2 skipped) |
| PluginGuard / Mseo2 / A.SEOb | PASS |
| Quality validate + baseline | PASS at feature HEAD |
| Build / ZIP audit | PASS at feature HEAD |

## Limitations / debt

- Hierarchy / terms / Woo category permalink localization deferred to MSEO.3/4
- Browser smoke remains local/non-CI per repository convention
- `SYSTEM_ERROR` is taxonomy-complete; unexpected AS failures surface via scheduler rather than broad catch
- History OFF source-path fallback uses page URI helper for posts

**MSEO.3 NOT STARTED**
