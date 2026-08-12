# TSC.1 Implementation Evidence

**Milestone:** TSC.1 — First-Class Taxonomy Terms  
**Status:** **COMPLETE** on `main` (merge `4d21536f07f414f84a8b30501e25d5995aff11ff`)  
**Branch:** `feature/tsc1-first-class-taxonomy-terms` (merged)  
**Frozen plan:** [TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md](TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md)  
**Baseline:** [TSC1_IMPLEMENTATION_BASELINE.md](TSC1_IMPLEMENTATION_BASELINE.md)  
**Validation:** [TSC1_VALIDATION_LOG.md](TSC1_VALIDATION_LOG.md)  
**ADR:** [0021-first-class-taxonomy-term-identity-and-lazy-adoption.md](../adr/0021-first-class-taxonomy-term-identity-and-lazy-adoption.md) (**Accepted**)  
**Version:** 1.3.0  
**TARGET:** 7 (STATE A — no migration)  
**Browser scaffold:** [acceptance/tsc1-browser/](../../acceptance/tsc1-browser/) (local/non-CI)

## Work packages

| WP | Status | Evidence |
|---|---|---|
| TSC1.0 | COMPLETE | `Tsc1AdoptionCharacterizationTest`, `Tsc1AdoptionRaceTest`, `Tsc1TermAdoptionIntegrationTest` |
| TSC1.1 | COMPLETE | `SOURCE_TERM`, `TermSurfaceAdapter`, `AdmittedTaxonomies`, registry + `extract_segments` |
| TSC1.2 | COMPLETE | `TermExtractor`, coordinator term flush, `delete_term` dirty |
| TSC1.3 | COMPLETE | `with_term_compat_authority`, `adopt_row_to_identity`, `TermAdoptionService`, `TermTranslationResolver` |
| TSC1.4 | COMPLETE | OTL capability flags, bulk surface auth, content adopt, axis authority lock |
| TSC1.5 | COMPLETE | Jobs auth for terms, worker/processor term path, adopt-before-persist |
| TSC1.6 | COMPLETE | `TermVisitorOverlay`, FE bridge resolver, Woo description guards |
| TSC1.7 | COMPLETE | Rank Math term keys via resolver/adoption; `pa_*` via AdmittedTaxonomies |
| TSC1.8 | COMPLETE | PluginGuard TSC.1 invariants; this evidence; browser README |

## TT1–TT40

| ID | Disposition | Evidence |
|---|---|---|
| TT1 | PASS | `Store::SOURCE_TERM`; characterization + PluginGuard |
| TT2 | PASS | TermSurfaceAdapter / adopt identity uses term_id + taxonomy subtype |
| TT3 | PASS | AdmittedTaxonomies code-owned; PluginGuard bans filters |
| TT4 | PASS | TermExtractor FIELD_NAME / FIELD_DESCRIPTION |
| TT5 | PASS | No slug field; characterization / extractor |
| TT6 | PASS | TermAdoptionService lazy; no eager catalog |
| TT7 | PASS | `ensure_native_before_content_write` / `native_write_identity` |
| TT8 | PASS | Axis via `mutate_under_term_compat_authority`; no adopt |
| TT9 | PASS | `adopt_column_map` validity matrix |
| TT10 | PASS | Native `segment_hash` recomputed; race test |
| TT11 | PASS | Review hash consistency / reset in adopt_column_map |
| TT12 | PASS | Race test preserves publish axis |
| TT13 | PASS | Native authority after adopt |
| TT14 | PASS | Resolver + Store resolve_authority native-first |
| TT15 | PASS | Hosted `ignored` + empty error_code; race/integration |
| TT16 | PASS | Sole TermTranslationResolver; PluginGuard |
| TT17 | PASS | Resolver read-only; PluginGuard |
| TT18 | PASS | Characterization: adopt bypasses save_translation |
| TT19 | PASS | Shared `with_term_compat_authority` |
| TT20 | PASS | Coordinator term dirty / invalidate |
| TT21 | PASS | Delete → orphan path via surface/coordinator |
| TT22 | PASS | Rank Math key retention race test |
| TT23 | PASS | TermSurfaceAdapter visibility facts |
| TT24 | PASS | Jobs/OTL edit_term authorization |
| TT25 | PASS | OTL inspect via TermSurfaceAdapter capabilities |
| TT26 | PASS | TranslationService / Review / Publication wiring (`TermTranslationResolver`) |
| TT27 | PASS | Jobs term processor + adopt-before-persist |
| TT28 | PASS | Existing hash concurrency retained |
| TT29 | PASS | Publication on authoritative row (`Tsc1PublicationAuthorityTest`) |
| TT30 | PASS | TermVisitorOverlay seam table only |
| TT31 | PASS | PluginGuard bans get_term hooks |
| TT32 | PASS | AdmittedTaxonomies pa_* |
| TT33 | PASS | Attribute labels banned in overlay/PluginGuard |
| TT34 | PASS | No all-term scan; registry O(1) |
| TT35 | PASS | edit_term / capability paths |
| TT36 | PASS | ADR-0021 Accepted; TARGET 7 |
| TT37 | PASS | mutate remaps to native; `PublicationService` + `Tsc1PublicationAuthorityTest` / `Tsc1TermAdoptionIntegrationTest` |
| TT38 | PASS | Authority revalidation under lock (`mutate_under_term_compat_authority` in Review + Publication) |
| TT39 | PASS | Adopt under same lock as axis; publication axis uses Store term authority |
| TT40 | PASS | No second publication/policy engine |

## AC1–AC58

| ID | Disposition | Notes |
|---|---|---|
| AC1–AC12 | PASS | Store adopt + race unit tests |
| AC13–AC17 | PASS | Lifecycle preservation + ignored retirement |
| AC18–AC20 | PASS | Content / Jobs / retranslate adopt paths |
| AC21–AC31 | PASS | Axis-only + authority lock; AC22/AC26–AC31: `PublicationService` `term_ref`/`authoritative_address` + `mutate_under_term_compat_authority` on publish/unpublish; `Tsc1PublicationAuthorityTest` |
| AC32 | PASS | WP_Error rollback tests |
| AC33–AC36 | PASS | Resolver + PluginGuard alias ban |
| AC37–AC42 | PASS | TermVisitorOverlay + FE guards |
| AC43–AC46 | PASS | OTL wiring (list/edit/review/bulk surface auth) |
| AC47–AC49 | PASS | Jobs term path + orphan short-circuit |
| AC50–AC51 | PASS | Rank Math keys + pa_* values; labels deferred |
| AC52–AC54 | PASS | PluginGuard; TARGET 7; no migration |
| AC55 | PASS | TSC.0 tests retained in suite |
| AC56 | SCAFFOLD | `acceptance/tsc1-browser/` (non-CI) |
| AC57 | PASS | Classic Workspace post_id REST unchanged |
| AC58 | PASS | PluginGuard no SOURCE_MENU/WIDGET |

Deferred/unsupported plan items (archive title, breadcrumbs, attribute labels, widgets) are **not** marked PASS as product features.

## Schema / ADR

- TARGET **7** unchanged  
- Version **1.3.0** unchanged  
- No schema migration  
- ADR-0021 Accepted  

## Limitations / debt

- Alias sunset / optional CLI backfill not required  
- `get_the_archive_title` / breadcrumbs Deferred  
- Attribute taxonomy labels remain TSC.3  
- Classic Workspace `post_id` REST stays post-only  
- Exactly-once not stronger than InnoDB txn + idempotent retry  
- Browser acceptance is scaffolded, not automated in CI  

## Next after merge/closure

Do not start TSC.2 until a separate planning freeze.
