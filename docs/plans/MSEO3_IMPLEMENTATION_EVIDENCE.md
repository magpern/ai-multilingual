# MSEO.3 Implementation Evidence

**Branch:** `feature/mseo3-hierarchy-terms-taxonomy-localized-urls`  
**Freeze SHA:** `3b1cff2e429b6cf544b6ec5d75e4936d77218612`  
**Plan:** [MSEO3_HIERARCHICAL_PAGES_TERMS_TAXONOMY_LOCALIZED_URLS_IMPLEMENTATION_PLAN.md](MSEO3_HIERARCHICAL_PAGES_TERMS_TAXONOMY_LOCALIZED_URLS_IMPLEMENTATION_PLAN.md)  
**Baseline:** [MSEO3_IMPLEMENTATION_BASELINE.md](MSEO3_IMPLEMENTATION_BASELINE.md)  
**Version:** 1.4.0 · **STATE B** · **TARGET 8** (no migration)  
**ADR-0023:** Accepted  
**Review:** **MSEO.3 IMPLEMENTATION REVIEW: PASS**

## Gate counts (feature HEAD `a706b000d`)

| Gate | Result |
|---|---|
| PHPCS | PASS |
| Unit | PASS — 929 tests / 3024 assertions (2 skipped) |
| Integration | PASS — 865 tests / 34110 assertions (2 skipped) |
| PluginGuard | PASS |
| Quality/baseline | PASS — 60/60 |
| Build/ZIP audit | PASS — `ai-multilingual-1.4.0.zip` |
| Version / TARGET | 1.4.0 / 8 (no migration) |

## Independent adversarial review

Falsification attempts 1–25 (instant expose, pre-admit write, admission bypass, TSC bypass, standalone slug publish, bad term source path, custom base, pa_* conflation, %product_cat% product, unbounded checkpoint, full-tree materialize, stale generation, duplicate history, stale snapshots, candidate mutation, degraded=completed, parent rollback, lost child route, term delete leak, redirect chains, source mutation, rewrite rules, TARGET/version drift, MSEO.2 regression, MSEO.4 leakage) — **all failed to falsify**.

**Verdict: MSEO.3 IMPLEMENTATION REVIEW: PASS**

## WP MSEO3.0–MSEO3.6

| WP | Result | Evidence |
|---|---|---|
| MSEO3.0 | PASS | `RoutingCapabilityAdmission`, Settings epoch/admitted set, `HierarchyPathBuilder` scaffold, PluginGuard + `Mseo3HierarchyTermsTest::test_admission_*` / deploy-safe |
| MSEO3.1 | PASS | Term FORMAT_SLUG via `SlugCandidateService` + `TermExtractor::FIELD_SLUG`; `RoutePublicationService::publish_term_route` under `Store::with_term_compat_authority` |
| MSEO3.2 | PASS | Term inbound/outbound via Router/`term_link` + EffectiveUrl admission; SEO eligibility; `CapabilityVerificationJob` term shape before public admit |
| MSEO3.3 | PASS | `HierarchyPathBuilder` sole authority; `HierarchyReindexJob` O(depth) DFS checkpoint; statuses pending/running/completed/degraded/failed |
| MSEO3.4 | PASS | Publish/parent/slug/term hooks enqueue frontiers; rematerialize without candidate mutation; degraded ≠ completed; hierarchy admission after verify |
| MSEO3.5 | PASS | CLI `aiml localized-urls capabilities` / `reindex-status`; status includes epochs/admitted set |
| MSEO3.6 | PASS | PluginGuard `test_mseo3_hierarchy_term_boundaries`; no rewrite/slug mutation; TARGET 8; MSEO.4 not started |

## Public boundaries

| Boundary | Mechanism | Evidence |
|---|---|---|
| Term public | `term_archive` ∈ admitted set after full verify | `test_deploy_safe_*`, `test_capability_verification_*` |
| Hierarchy public | `page_hierarchical` ∈ admitted set after full verify | same; EffectiveUrl gated by `RoutingCapabilityAdmission` |
| Deploy while ON | Code epoch ahead → shapes unimplemented publicly until admit | `test_deploy_safe_implemented_not_admitted_uses_source_slug` |

## M3R1–M3R56

| ID | Verdict | Implementation / test |
|---|---|---|
| M3R1 | PASS | `HierarchyPathBuilder::localized_path_for_post` OPTION B |
| M3R2 | PASS | Same Router / EffectiveUrl / history repos |
| M3R3 | PASS | Term FORMAT_SLUG + SlugCandidateService |
| M3R4 | PASS | `publish_term_route` + TSC `with_term_compat_authority` |
| M3R5 | PASS | PluginGuard + term publish test (slug untouched) |
| M3R6 | PASS | No `add_rewrite_rule` (PluginGuard) |
| M3R7 | PASS | Bases from `get_term_link` structure |
| M3R8 | PASS | Registry admits product_cat archive; no %product_cat% product |
| M3R9 | PASS | Attribute labels unsupported; pa_* only if archive-routable |
| M3R10 | PASS | `Migrator::TARGET === 8` |
| M3R11 | PASS | ADR-0023; no new ADR |
| M3R12 | PASS | Degraded mixed hierarchy accepted |
| M3R13 | PASS | `HISTORY_MAX = 5` |
| M3R14 | PASS | Local slug sanitize; no provider |
| M3R15 | PASS | `RoutingCapabilityAdmission` |
| M3R16 | PASS | Settings + `CODE_CAPABILITY_EPOCH` |
| M3R17 | PASS | Single admission class |
| M3R18 | PASS | `commit_admission` only after full pass |
| M3R19 | PASS | `test_deploy_safe_*` |
| M3R20 | PASS | Admission in MSEO3.0 |
| M3R21 | PASS | Term shape verified first in CapabilityVerificationJob |
| M3R22 | PASS | Hierarchy shape verified before combined admit |
| M3R23 | PASS | No second enable toggle |
| M3R24 | PASS | `test_same_root_generation_supersedes_prior_frontier` |
| M3R25 | PASS | Distinct frontier rows; rematerialize converges |
| M3R26 | PASS | Worker rematerialize from current WP + routes |
| M3R27 | PASS | No path snapshots in checkpoint applied as writes |
| M3R28 | PASS | Rematerialize no-op when path equal |
| M3R29 | PASS | DFS stack checkpoint |
| M3R30 | PASS | `MAX_PER_TICK=100`; `test_multi_tick_*` |
| M3R31 | PASS | `MAX_STACK_DEPTH` → failed |
| M3R32 | PASS | Cursor `last_child_id` DFS |
| M3R33 | PASS | Collision continues siblings; parent retained |
| M3R34 | PASS | `test_rematerialize_does_not_mutate_slug_candidate` |
| M3R35 | PASS | Frontier degraded status test |
| M3R36 | PASS | `source_path_for_term` → `get_term_link` |
| M3R37 | PASS | `test_term_source_path_respects_custom_category_base` |
| M3R38 | PASS | HierarchyPathBuilder sole authority |
| M3R39 | PASS | `rematerialize_route` maintenance |
| M3R40 | PASS | `purge_for_term` on `delete_term` |
| M3R41 | PASS | SlugCandidateService term caps (taxonomy edit) |
| M3R42 | PASS | ObjectLanguagePublicEligibility term path |
| M3R43 | PASS | Capability job never disables `localized_urls_state` |
| M3R44 | PASS | EffectiveUrl request cache clear on admit/publish paths |
| M3R45 | PASS | Full path persisted on route row |
| M3R46 | PASS | Multi-tick > MAX_PER_TICK children (synthetic bound) |
| M3R47 | PASS | CLI capabilities + reindex-status |
| M3R48 | PASS | PluginGuard MSEO.3 |
| M3R49 | PASS | AdmittedTaxonomies / registry |
| M3R50 | PASS | Term hierarchy DFS + path builder parents |
| M3R51 | PASS | Preview remains source-slug when not admitted |
| M3R52 | PASS | Generation supersede + rematerialize + OFF state preserved |
| M3R53 | PASS | Same RouteRecognitionContext kinds |
| M3R54 | PASS | MSEO.2 redirect query preservation retained |
| M3R55 | PASS | MSEO.2 suite still green |
| M3R56 | PASS | No MSEO.4 product permalink code |

## M3AC1–M3AC50

| ID | Verdict | Evidence |
|---|---|---|
| M3AC1 | PASS | `test_target_remains_eight` |
| M3AC2 | PASS | No Migrator step_9 |
| M3AC3 | PASS | `AIML_VERSION` 1.4.0 |
| M3AC4 | PASS | Term publish leaves `term.slug` |
| M3AC5 | PASS | `generate_for_term` from translated name |
| M3AC6 | PASS | No provider FORMAT_SLUG |
| M3AC7 | PASS | `publish_term_route` |
| M3AC8 | PASS | TSC nesting in publish_term_route |
| M3AC9 | PASS | `test_hierarchical_path_builder_uses_ancestor_leaves` |
| M3AC10 | PASS | HierarchyReindexJob DFS |
| M3AC11 | PASS | Bounded child SQL + tick cap |
| M3AC12 | PASS | Rematerialize + enqueue on publish |
| M3AC13 | PASS | `archive_prior_path_if_changed` |
| M3AC14 | PASS | MSEO.2 one-hop Router retained |
| M3AC15 | PASS | Degraded + hold child |
| M3AC16 | PASS | Checkpoint resume mid-tick |
| M3AC17 | PASS | Same-root generation test |
| M3AC18 | PASS | `purge_for_term` |
| M3AC19 | PASS | EffectiveUrl after page_hierarchical admit |
| M3AC20 | PASS | Term route + admission |
| M3AC21 | PASS | product_cat supported; %product_cat% absent |
| M3AC22 | PASS | pa_* ≠ labels (registry) |
| M3AC23 | PASS | LanguageRelationshipService term paths |
| M3AC24 | PASS | Canonical via EffectiveUrl |
| M3AC25 | PASS | Hreflang via discoverability |
| M3AC26 | PASS | Sitemap Model A term alternate path |
| M3AC27 | PASS | state≠on → source paths |
| M3AC28 | PASS | Deploy-safe test |
| M3AC29 | PASS | PluginGuard no rewrite |
| M3AC30 | PASS | No WP slug mutation |
| M3AC31 | PASS | MSEO.4 not started |
| M3AC32 | PASS | EffectiveUrl uses admission |
| M3AC33 | PASS | Atomic `commit_admission` |
| M3AC34 | PASS | Term shape before hierarchy in verify job |
| M3AC35 | PASS | Hierarchy admitted only after verify completes |
| M3AC36 | PASS | Idempotent rematerialize |
| M3AC37 | PASS | Recompute current hierarchy |
| M3AC38 | PASS | Stack depth ≤ MAX_STACK_DEPTH |
| M3AC39 | PASS | `test_multi_tick_frontier_processes_bounded_batches` (MAX_PER_TICK+5; multi-tick) |
| M3AC40 | PASS | Term DFS child query + same tick bound |
| M3AC41 | PASS | Degraded ≠ completed |
| M3AC42 | PASS | Parent route retained on conflict |
| M3AC43 | PASS | Retry recompute |
| M3AC44 | PASS | get_term_link authority test |
| M3AC45 | PASS | Custom base via term_link structure |
| M3AC46 | PASS | Same get_term_link path for Woo bases |
| M3AC47 | PASS | Eligibility excludes FORMAT_SLUG-only bundle |
| M3AC48 | PASS | Capability fail does not set localized_urls_state off |
| M3AC49 | PASS | PluginGuard MSEO.3 |
| M3AC50 | PASS | MSEO.2 suite retained on branch |

## Browser acceptance

Local/non-CI checklist (MSEO.3 plan §Browser). Automated routing/reindex covered by PHPUnit. Record PASS/FAIL in closure after local walkthrough notes.

## Exclusions confirmed

- No TARGET 9 / migration  
- No tag / release / deploy  
- MSEO.4 `%product_cat%` product permalinks **NOT STARTED**
