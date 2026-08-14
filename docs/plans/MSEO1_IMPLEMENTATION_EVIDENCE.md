# MSEO.1 Implementation Evidence

**Branch:** `feature/mseo1-localized-slug-route-lifecycle`  
**Freeze SHA:** `cc35ed4ae69a4c04e14d6391758b4adc3729d32d`  
**Plan:** [MSEO1_LOCALIZED_SLUG_CANDIDATE_ACTIVE_ROUTE_IMPLEMENTATION_PLAN.md](MSEO1_LOCALIZED_SLUG_CANDIDATE_ACTIVE_ROUTE_IMPLEMENTATION_PLAN.md)  
**Version:** 1.4.0 · **STATE B** · **TARGET 8** (no migration)  
**ADR-0023:** Accepted  
**Review:** **MSEO.1 IMPLEMENTATION REVIEW: PASS** (after review-fix loop)

## WP MSEO1.0–MSEO1.6

| WP | Result | Evidence |
|---|---|---|
| MSEO1.0 | PASS | Extractor `FIELD_SLUG`; Store hydrate/preserve/`save_slug_candidate` |
| MSEO1.1 | PASS | `SlugCandidateService` generate/manual/clear + sanitize-drift reject |
| MSEO1.2 | PASS | Capability, eligibility, `CanonicalPathCollisionChecker` |
| MSEO1.3 | PASS | `RoutePublicationService` + under-route publish + history + idempotence |
| MSEO1.4 | PASS | REST slug GET/POST/DELETE + publish-route; generic FORMAT_SLUG publish rejected |
| MSEO1.5 | PASS | `post_updated` → `refresh_source_path`; trash/delete hooks; Jobs exclusion |
| MSEO1.6 | PASS | PluginGuard `test_mseo1_lifecycle_boundaries`; this evidence |

## M1AC1–M1AC36 → evidence

| AC | Verdict | Location / test |
|---|---|---|
| M1AC1 | PASS | Extractor + `test_extract_emits_post_name` |
| M1AC2 | PASS | `SlugCandidateService::generate` + `test_generate_from_title_no_provider` |
| M1AC3 | PASS | `save_manual` + `test_manual_save_origin_manual_generate_rejected` |
| M1AC4 | PASS | same |
| M1AC5 | PASS | Store preserve + `test_generic_save_translation_preserves_slug_origin` |
| M1AC6 | PASS | `save_slug_candidate` sole origin writer |
| M1AC7 | PASS | `test_edit_candidate_after_route_pending_route_unchanged` |
| M1AC8 | PASS | same |
| M1AC9 | PASS | `publish_route` + route boundary + `test_publish_route_atomic_*` |
| M1AC10 | PASS | `test_publish_route_atomic_while_localized_urls_state_off` |
| M1AC11 | PASS | `is_discoverable` false + EffectiveUrl unwired + PluginGuard |
| M1AC12 | PASS | history insert on replacement + history reuse test |
| M1AC13 | PASS | `test_history_max_and_same_object_reuse_red_blue_red` |
| M1AC14 | PASS | `test_manual_collision_returns_409_candidate_unchanged` |
| M1AC15 | PASS | `test_collision_adjusts_generated_to_foo_2_candidate_stays_foo` |
| M1AC16 | PASS | `ObjectLanguagePublicEligibility` |
| M1AC17 | PASS | `test_hierarchical_page_cannot_publish_route` |
| M1AC18 | PASS | history reuse test |
| M1AC19 | PASS | `test_foreign_history_reservation_blocks_publish` |
| M1AC20 | PASS | reconcile_own_history deletes hist before activate |
| M1AC21 | PASS | `test_refresh_source_path_updates_source_keeps_leaf` + Plugin hook |
| M1AC22 | PASS | shared route boundary / FOR UPDATE locks |
| M1AC23 | PASS | trash/purge tests + Plugin hooks |
| M1AC24 | PASS | REST `can_edit_post` = `aiml_translate` + `edit_post` |
| M1AC25 | PASS | `sync_view` + REST slug endpoints |
| M1AC26 | PASS | Jobs Missing/Stale + ItemProcessor FORMAT_SLUG skip |
| M1AC27 | PASS | PluginGuard + `test_publish_route_does_not_mutate_canonical_post_name` |
| M1AC28 | PASS | Migrator::TARGET=8; version 1.4.0 |
| M1AC29 | PASS | `test_clear_candidate_resets_origin_route_intact` |
| M1AC30 | PASS | `test_hierarchical_page_cannot_publish_route` |
| M1AC31 | PASS | `test_generic_publication_rejects_format_slug` |
| M1AC32 | PASS | under-route publish uses existing review/publication axes; review metadata independent |
| M1AC33 | PASS | fail-closed generic publish + route-only path |
| M1AC34 | PASS | collision-adjusted synchronized test |
| M1AC35 | PASS | `test_idempotent_republish_preserves_foo_2` |
| M1AC36 | PASS | non-slug PublicationService path unchanged (characterization via existing TI.7 suite) |

## M1R1–M1R36

All **Supported** requirements PASS via the AC mapping above. **Deferred:** M1R32 (terms/hierarchy/Woo category → MSEO.3/4).

## Review-fix findings

1. **Material:** `refresh_source_path` existed but was not signaled from `post_updated` → wired + tested  
2. **Material:** REST clear action missing → `DELETE .../slug` → `clear_slug_candidate`  
3. **Coverage:** added tests for manual 409 collision, foreign history, trash/purge, clear, discoverability, sanitize drift  
4. **Guards:** PluginGuard asserts refresh/trash/purge wiring + clear REST + under-route primitive

## Validation (local focused + prior full suite)

| Gate | Result |
|---|---|
| PHPCS (touched) | PASS |
| Focused integration | 22 tests / 321 assertions PASS (`Mseo1SlugLifecycleTest` + PluginGuard MSEO.1) |
| Prior full unit | 929 tests PASS |
| Prior full integration | 817 tests PASS |
| Quality / ZIP | PASS at prior feature HEAD; re-confirmed via PR CI after push |

## Public routing OFF

EffectiveUrlService unwired; no Router/home_url/SEO/rewrite changes; `is_discoverable` always false; PluginGuard MSEO.1 boundaries.

## Limitations / debt

- Term candidates deferred to MSEO.3  
- No public routing until MSEO.2  
- No admin enable UI  
- No bulk slug ops  
- Optional Workspace UI panel polish can continue in MSEO.2 operator UX  

**MSEO.2 NOT STARTED**
