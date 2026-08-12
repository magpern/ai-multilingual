# TSC.2 Implementation Evidence

**Status:** **COMPLETE** on `main` (merge `53470811a92147f4141395f4da63b8d04fea3b46`)
**Plan:** [TSC2_REGISTERED_META_SURFACES_IMPLEMENTATION_PLAN.md](TSC2_REGISTERED_META_SURFACES_IMPLEMENTATION_PLAN.md)
**Baseline:** [TSC2_IMPLEMENTATION_BASELINE.md](TSC2_IMPLEMENTATION_BASELINE.md)
**Validation:** [TSC2_VALIDATION_LOG.md](TSC2_VALIDATION_LOG.md)

## Work packages

| WP | Result | Evidence |
|---|---|---|
| TSC2.0 | Done | Inventory characterization via Rank Math + native fixture paths |
| TSC2.1 | Done | `src/Surface/Meta/*` Definition/Registry/Reader; RankMathMetaDefinitions |
| TSC2.2 | Done | Post adapter catalog invalidation; Extractor merge; Rank Math Integration retains `p:` extract/overlay (catalog owns six-key + activation) |
| TSC2.3 | Done | Term adapter catalog invalidation; TermExtractor merge; retain_keys; Jobs surface extract |
| TSC2.4 | Done | SegmentAssembler labels/meta; provider_allowed in Jobs + TranslationService; TI.7 unchanged |
| TSC2.5 | Done | NativeMetaReferenceAdapter fixture; Rank Math Integration extract/overlay/sitemap unchanged |
| TSC2.6 | Done | PluginGuard TSC.2 bans; Woo economic key reject; unit registry/security; O(R) no ceiling test |
| TSC2.7 | Done | This evidence + baseline |

## RM1–RM34

| IDs | Result |
|---|---|
| RM1–RM6, RM8–RM26, RM28–RM30, RM33 | **Supported** via catalog + Store retain + Jobs/TranslationService provider gate + PluginGuard |
| RM7 | **Deferred** (structured paths) |
| RM27, RM31–RM32 | **Unsupported** (forbidden / TSC.4–5) |
| RM34 | **Deferred** (TSC.3+/TSC.6) |

## AC1–AC32

Covered by unit `RegisteredMetaRegistryTest`, integration `Tsc2RegisteredMetaLifecycleTest`, PluginGuard TSC.2 assertions, existing Rank Math / term Jobs regression paths. Deferred/Unsupported ACs asserted as non-implemented (no Gutenberg/Elementor/public API/SOURCE_META).

## Key paths

| Concern | Path |
|---|---|
| Catalog | `src/Surface/Meta/RegisteredMetaRegistry.php` |
| Rank Math keys | `src/Surface/Meta/RankMathMetaDefinitions.php` |
| Rank Math activation | `RankMathIntegration::allows_extract_operation()` / `probe_allows_operation()` |
| Retain keys | `Store::sync_source(…, $retain_segment_keys)` |
| Native extract | `RegisteredMetaExtractor` → Extractor / TermExtractor |
| Provider gate | `BackgroundTranslationItemProcessor` + `TranslationService` |
| Reference overlay | `tests/Fixtures/RegisteredMeta/NativeMetaReferenceAdapter.php` |

## Limitations / debt

- Rank Math Integration still owns extract/overlay/literal/sitemap (intentional); catalog owns the six-key list + activation aligned with extract eligibility.
- Host-emitted Rank Math retain uses Store∩family query when definitions inactive.
- No production native `m:` fields beyond test fixtures (honest product value).
- `RegisteredMetaInvalidation` helper exists; adapters register hooks directly.
- Browser suite not required.

## Independent review repairs

- Rank Math catalog activation mirrors Integration extract eligibility (not merely class/constant presence).
- `provider_allowed` enforced in `TranslationService` (sync/batch) in addition to Jobs ItemProcessor.
- Woo economic meta keys rejected at registry bootstrap; PluginGuard AC30 coverage.

## Schema / TARGET / ADR

STATE A · TARGET 7 · no migration · no ADR · no SOURCE_META.
