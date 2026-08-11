# OTL.1 Operations List + Attention — Implementation Validation Log

**Status:** OTL.1 implementation complete — ready for independent review
**Milestone:** OTL.1 — Operations List + Attention
**Plan:** [OTL1_OPERATIONS_LIST_ATTENTION_IMPLEMENTATION_PLAN.md](OTL1_OPERATIONS_LIST_ATTENTION_IMPLEMENTATION_PLAN.md)
**Parent:** [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)
**Feature branch:** `feature/otl1-operations-list-attention`
**Draft PR:** https://github.com/magpern/ai-multilingual/pull/15
**Independent review (implementation):** pending

## Baseline

| Field | Value |
|---|---|
| Main baseline SHA | `54ea13fdc06347ccb81acd51cdd939f124251be3` |
| OTL.1 freeze merge | `30332a315e2b0a99a036a5aa521771b21ba2cd9a` |
| Frozen plan | Architecture Frozen; planning review PASS; **82** ACs |
| Plugin version | **1.2.0** (unchanged) |
| TARGET | **7** (unchanged) |
| Schema migration | None |
| Integration API | Unchanged |
| TSC | Not started |
| OTL.2–OTL.6 | Not started |
| Public/SaaS neutrality | Hard invariant |

## Delivered

- `OperationalAttention` vocabulary: `stale`, `review_pending`, `review_rejected`, `unpublished`, `translation_failed` (never `needs_review`)
- List `attention_reasons: string[]` (multi-label, cheap axes only)
- List `attention` filter + AND composition with explicit axes; invalid/`needs_review` → 422
- `GET …/operations/attention-counts` — language-wide independent counts; auth ≡ list
- `Store::count_operations_attention` single aggregate COUNT
- Operations Workspace tab + filters + URL state + honesty copy
- Reusable read-only inspector (Decision A); no review/publish/Jobs mutations
- Navigation: Open in Translate / Open in Review / source / frontend
- Local Playwright suite `acceptance/otl1-browser/` (not CI-gated)

## Work packages

| WP | Status |
|---|---|
| OTL1.0 Baseline | Complete |
| OTL1.1 Attention contract | Complete |
| OTL1.2 REST + auth parity | Complete |
| OTL1.3 Operations shell tab | Complete |
| OTL1.4 Filters / list / counts UI | Complete |
| OTL1.5 Navigation + inspector | Complete |
| OTL1.6 A11y / responsive / Playwright | Complete (local smoke) |
| OTL1.7 Perf / security / neutrality | Complete |
| OTL1.8 Closure | Pending (after merge) |

## Local gates (pre-review)

| Gate | Result |
|---|---|
| PHPCS | PASS (589 files) |
| Unit | PASS 776 tests / 2190 assertions (2 skipped) |
| Integration (full) | PASS 684 tests / 23171 assertions (2 skipped) |
| PluginGuard | PASS (incl. prepare/`get_row` + OTL attention guards) |
| JS unit | PASS 62 tests |
| Quality baseline verify | PASS cases=60 |
| ZIP build/audit | PASS `ai-multilingual-1.2.0.zip` |
| Playwright | Local suite added; not CI-gated (F10 pattern) |

## Attention / performance notes

- Default list: AssessmentAssembler / Publication explain / QA = 0 (OTL.0 preserved)
- Counts: one bounded aggregate; no assess/explain; `total` ≡ unfiltered list inventory
- Scale: count parity exercised at 250+ seeded rows; list cheap path retained from OTL.0 scale suite

## Acceptance criteria

**82/82** targeted by frozen plan — independent review must verify evidence.

## Exact next step

Independent implementation review on this feature branch. Do not mark OTL.1 Complete on main until PASS + merge + closure.
