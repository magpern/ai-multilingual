# TI.2 — Bounded Translation Context — Implementation Validation Log

**Status:** **Implementation complete — awaiting independent review** (unmerged)
**Implementation branch:** `feature/ti2-bounded-translation-context`
**Authoritative planning baseline (main):** `7bf8cff20f48970fa0a775b7b5ddc7158162289d`
**Frozen plan blob:** `fa10636fff6e4a691180bc1b9b3f0977207fd095`
**Official TQ.0 pack:** `tests/quality/baselines/baseline-v1.1.0/` (immutable)
**TI.2 candidate pack:** `tests/quality/baselines/_staging-ti2/`
**H1.0 / C1.0:** immutable
**TARGET:** 6
**TI.3:** not started

## Architecture lock

| Lock | Status |
|---|---|
| One brain: TranslationService → TranslationBatch (+ optional TranslationContext) → AIProviderInterface | **PASS** |
| Single TranslationContextBuilder in TranslationService | **PASS** |
| Sync/Jobs parity | **PASS** |
| No WooContext / SeoContext / JobsContext | **PASS** |
| Context not Store identity / not source_hash | **PASS** |
| TARGET 6 / no schema migration | **PASS** |
| No TM / TI.3 glossary intelligence / TI.4 QA | **PASS** |
| No live AI in normal CI | **PASS** |

## TC1–TC14 final dispositions

| ID | Disposition | Notes |
|---|---|---|
| TC1 | Supported | Closed FieldSemantic + mapper |
| TC2 | Supported | object_type |
| TC3 | Supported | object_title capped 200 |
| TC4 | Partial | Deterministic sibling pairs only |
| TC5 | Partial | ≤3 category names |
| TC6 | Partial | ≤5 attribute names |
| TC7 | Deferred | — |
| TC8 | Partial | Optional language display names |
| TC9 | Supported | SEO purpose search/social_snippet |
| TC10 | Deferred | — |
| TC11 | Supported | Glossary do-not-copy + source boundary |
| TC12 | Supported | 1200/200/8 budgets + drop priority |
| TC13 | Supported | Allowlist ContextItem types |
| TC14 | Supported | schema_version + provenance |

## Packages TI2.0–TI2.8

| Package | Status |
|---|---|
| TI2.0–TI2.8 | **PASS** (implementation branch) |

## Quality evidence

| Gate | Result |
|---|---|
| verify-baseline baseline-v1.1.0 | PASS |
| compare baseline ↔ _staging-ti2 | **PASS** — 0 new Class A critical |
| gut_01 scaffold | **GONE** |
| C1.1 additive corpus | Present (4 paired-context cases) |

## Limitations

- Context changes do **not** mark existing Store rows stale (`source_hash` unchanged).
- C1.0 cases lack rich object_title/category metadata; C1.1 probes context fields.
- Class B human re-consensus for gut_01 not re-run in this branch (deterministic + packaging evidence recorded).
- Site tone (TC7) and surrounding text (TC10) remain Deferred.

## Next step

Independent implementation review agent → merge only after PASS. Do not start TI.3.
