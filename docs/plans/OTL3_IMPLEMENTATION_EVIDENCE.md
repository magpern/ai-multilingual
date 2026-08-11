# OTL.3 Implementation Evidence Map

**Branch:** `feature/otl3-publication-stale-workflow`
**Baseline main:** `6a64252a602bdea923a8c1c7b86e73441cdca666`
**Frozen plan:** [OTL3_PUBLICATION_STALE_WORKFLOW_IMPLEMENTATION_PLAN.md](OTL3_PUBLICATION_STALE_WORKFLOW_IMPLEMENTATION_PLAN.md)
**Feature HEAD (pre-independent-review):** `b79382c7322b6ab4073ce8a165066f6aad8aafaf`
**Version:** 1.2.0 · **TARGET:** 7 · **Schema:** unchanged · **ADR:** none new

## OTL3.0–OTL3.8 → evidence

| WP | Evidence |
|---|---|
| OTL3.0 | This map + baseline doc |
| OTL3.1 | `OperationsInspector` publish/unpublish; `workspace-api` publish/unpublish; TI.7 REST |
| OTL3.2 | `SettingsPage` gate/mode controls + confirmations; detail `publication_settings` |
| OTL3.3 | Stale banner + published+stale honesty in Inspector |
| OTL3.4 | `TranslationService` optional hash + pre-persist guard; `translateBatch` hashes; retranslate UI + confirmation |
| OTL3.5 | Dirty publication gate; conflict UX; provider-failure tests |
| OTL3.6 | Overlay-eligibility wording; Store facts; in-session `publication_result` |
| OTL3.7 | `acceptance/otl3-browser/`; PluginGuard; a11y labels/aria-live reuse |
| OTL3.8 | Tests + this map |

## Critical contracts

- Interactive sync retranslate carries `expected_translation_hash`
- Mandatory pre-persist `guard_expected_translation_hash` → 409 `aiml_translation_hash_mismatch`
- Jobs null-hash unchanged
- Provider race test: `Otl3RetranslateConcurrencyTest::test_provider_race_preserves_newer_target`
- Provider failure preserves prior publication
- No OTLPublicationPolicy; TI.7 sole authority
- Gate copy = overlay eligibility (not visibility guarantee)
- Settings: immediate gate enforcement; prospective auto mode; no sweep

## Local validation (pre-CI)

- PHPCS: PASS
- Unit: 780 tests PASS (2 skipped)
- JS unit: 82 PASS
- Integration (Otl3 + PluginGuard): PASS
- Quality baseline verify: PASS
- Playwright: local suite present (not CI-gated)
