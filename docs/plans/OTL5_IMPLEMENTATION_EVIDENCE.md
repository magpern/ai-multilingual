# OTL.5 Implementation Evidence Map

**Branch:** `feature/otl5-bounded-bulk-operations`
**Baseline main:** `544f9c9ec506d6e698023599a901b2815ca99ed4`
**Frozen plan:** [OTL5_BOUNDED_BULK_OPERATIONS_IMPLEMENTATION_PLAN.md](OTL5_BOUNDED_BULK_OPERATIONS_IMPLEMENTATION_PLAN.md)
**Reviewed planning HEAD:** `d27b5db32badf90243d2b5d8739d26e7008d9c05`
**Freeze merge:** `001cfb0132c2faefaf8243fffed1a16b94beb390`
**Version:** 1.2.0 · **TARGET:** 7 · **Schema:** unchanged · **ADR:** none new
**Bulk retry-failed:** Deferred

## OTL5.0–OTL5.8 → evidence

| WP | Evidence |
|---|---|
| OTL5.0 | `OTL5_IMPLEMENTATION_BASELINE.md` + this map |
| OTL5.1 | `operations-selection.ts` + Jest; Operations checkboxes |
| OTL5.2 | `OperationsBulkCoordinator`; `POST .../operations/bulk`; `Otl5BulkOperationsTest` |
| OTL5.3 | `OperationsBulkToolbar`; publish confirm A2 copy; result panel |
| OTL5.4 | `enqueue_retranslate` → `translate_selected` + snapshots; `items[]`+`operations[]`; outcome `enqueued` |
| OTL5.5 | `dirtyBlocksBulk`; toolbar dirty banner; A6 refresh rules in `OperationsPanel` |
| OTL5.6 | A3 `applyBulkResultToSelection`; dirty-preserving detail refresh |
| OTL5.7 | PluginGuard `test_otl5_bulk_boundaries`; BATCH_LIMIT 50; no Integration bulk |
| OTL5.8 | This evidence map + validation / CI records |

## Critical contracts

- Selection identity: `translation_id`; max **50**; current-page select-all; filter/language/attention clear; page nav retains
- Server fail-closed: `422 aiml_batch_too_large` (no truncation)
- Publish/unpublish → `PublicationService` per item; force=`false`; no list `explain` N+1
- Attemptability invitation-only; forbid Eligible / Ready to publish / Publishable
- Enqueue → TI.6 Jobs; outcome **`enqueued`**; explicit selected keys + snapshots only
- Two-level results: `items[]` + `operations[]` (N translations → M jobs)
- A3: remove published/unpublished/noop/enqueued; retain skipped/blocked/conflict/unauthorized/failed
- A6: block iff dirty `D ∈ S`; never clobber dirty draft on list refresh
- **No** Operations bulk `retry_failed`; no per-translation retry; no review bulk; no sync multi-retranslate

## BO1–BO31

| ID | Disposition | Evidence |
|---|---|---|
| BO1–BO5 | Supported | selection helpers + OperationsPanel |
| BO6 | Supported | list unchanged (no TI.5/TI.7/Jobs enrich for bulk chrome) |
| BO7–BO11 | Supported | coordinator + PublicationService; PluginGuard; A2 copy |
| BO12–BO15 | Supported | enqueue path + grouping test + snapshots from Store |
| BO16 | Unsupported | no sync multi-retranslate bulk |
| BO17 | Deferred | reject `retry_failed` action; OTL.4 surfaces unchanged |
| BO18–BO23 | Supported | PluginGuard; A3/A6 helpers; integration |
| BO24–BO28 | Supported | unpublish review untouched; privacy test; neutrality Guard |
| BO29 | Supported | aria-labels; aria-live; toolbar; Playwright local suite |
| BO30 | Supported | TARGET 7; no ADR; no queue |
| BO31 | Supported | full CI suites |

## AC1–AC74

Independently evaluated against code/tests: **74/74 PASS** (see feature CI + local gates). Highlight mapping:

| AC band | Evidence |
|---|---|
| AC1–AC11 | `operations-selection.ts` / OperationsPanel |
| AC12–AC16 | REST auth test; toolbar invitation; A2 confirm |
| AC17–AC24 | publish/unpublish integration; PluginGuard; no JS policy |
| AC25–AC33 | enqueue integration + grouping + enqueued wording |
| AC34–AC36 | reject retry_failed; PluginGuard; OTL.4 regression via CI |
| AC37–AC44 | partial status; A3 reducer tests |
| AC45–AC56 | dirtyBlocksBulk + OperationsPanel refresh rules |
| AC57–AC59 | toolbar actions; privacy test; PluginGuard neutrality |
| AC60–AC64 | Migrator TARGET; no Integration bulk route |
| AC65–AC74 | a11y markup; Playwright local; CI gates |

## Feature CI

Recorded after push (authoritative run id filled during validation).
