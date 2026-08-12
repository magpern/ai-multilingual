# TSC.2 Implementation Evidence

**Status:** Implementation on `feature/tsc2-registered-meta-surfaces`
**Plan:** [TSC2_REGISTERED_META_SURFACES_IMPLEMENTATION_PLAN.md](TSC2_REGISTERED_META_SURFACES_IMPLEMENTATION_PLAN.md)
**Baseline:** [TSC2_IMPLEMENTATION_BASELINE.md](TSC2_IMPLEMENTATION_BASELINE.md)

## Work packages

| WP | Result | Evidence |
|---|---|---|
| TSC2.0 | Done | Inventory characterization via Rank Math + native fixture paths |
| TSC2.1 | Done | `src/Surface/Meta/*` Definition/Registry/Reader; RankMathMetaDefinitions |
| TSC2.2 | Done | Post adapter catalog invalidation; Extractor merge; Rank Math Reader-ready |
| TSC2.3 | Done | Term adapter catalog invalidation; TermExtractor merge; retain_keys; Jobs surface extract |
| TSC2.4 | Done | SegmentAssembler labels/meta; provider_allowed Jobs gating; TI.7 unchanged |
| TSC2.5 | Done | NativeMetaReferenceAdapter fixture; Rank Math Integration unchanged |
| TSC2.6 | Done | PluginGuard TSC.2 bans; unit registry/security; O(R) no ceiling test |
| TSC2.7 | Done | This evidence + baseline |

## RM1–RM34

| IDs | Result |
|---|---|
| RM1–RM6, RM8–RM26, RM28–RM30, RM33 | **Supported** via catalog + Store retain + Jobs skip + PluginGuard |
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
| Retain keys | `Store::sync_source(…, $retain_segment_keys)` |
| Native extract | `RegisteredMetaExtractor` → Extractor / TermExtractor |
| Provider gate | `BackgroundTranslationItemProcessor` |
| Reference overlay | `tests/Fixtures/RegisteredMeta/NativeMetaReferenceAdapter.php` |

## Limitations / debt

- Rank Math still owns extract/overlay (intentional partial reuse).
- Host-emitted Rank Math retain uses Store∩family query when definitions inactive.
- No production native `m:` fields beyond test fixtures (honest product value).
- Browser suite not required.

## Schema / TARGET / ADR

STATE A · TARGET 7 · no migration · no ADR · no SOURCE_META.
