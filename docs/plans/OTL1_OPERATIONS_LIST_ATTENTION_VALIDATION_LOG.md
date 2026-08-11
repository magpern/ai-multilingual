# OTL.1 Operations List + Attention — Implementation Validation Log

**Status:** Implementation baseline established — implementation in progress
**Milestone:** OTL.1 — Operations List + Attention
**Plan:** [OTL1_OPERATIONS_LIST_ATTENTION_IMPLEMENTATION_PLAN.md](OTL1_OPERATIONS_LIST_ATTENTION_IMPLEMENTATION_PLAN.md)
**Parent:** [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)
**Feature branch:** `feature/otl1-operations-list-attention`
**Independent review (implementation):** pending

## Baseline

| Field | Value |
|---|---|
| Main baseline SHA | `54ea13fdc06347ccb81acd51cdd939f124251be3` |
| OTL.1 freeze merge | `30332a315e2b0a99a036a5aa521771b21ba2cd9a` |
| OTL.1 planning closure | `54ea13fdc06347ccb81acd51cdd939f124251be3` |
| Frozen plan on main | Architecture Frozen; planning review PASS; **82** ACs |
| Plugin version | **1.2.0** (unchanged) |
| TARGET | **7** (unchanged) |
| Schema migration | None expected |
| Integration API | Unchanged |
| TSC | Not started |
| OTL.2–OTL.6 | Not started |
| Public/SaaS neutrality | Hard invariant |

## Scope locks

- Collision-free attention IDs: `stale`, `review_pending`, `review_rejected`, `unpublished`, `translation_failed`
- Never use `needs_review` for ADR-0015 pending (TI.5 owns that ID)
- Counts auth ≡ Operations list auth
- No TI.5 / TI.7 / QA on default list or counts
- No OTL.2 review mutations / OTL.3 publish-retranslate / OTL.4 Jobs / OTL.5 bulk
- No version bump, no tag, no TARGET change

## OL capability matrix

Frozen dispositions in OTL.1 plan §24 (OL1–OL39). Implementation must not widen Deferred/Unsupported items.

## Acceptance criteria

**82/82** from frozen plan — evidence recorded as work packages complete.

## Work package tracker

| WP | Objective | Status |
|---|---|---|
| OTL1.0 | Baseline / validation log | In progress |
| OTL1.1 | Attention presets + `attention_reasons` + count helper | Pending |
| OTL1.2 | REST attention-counts + list `attention`; auth parity | Pending |
| OTL1.3 | Operations admin shell tab + bootstrap | Pending |
| OTL1.4 | Filters, list, counts UI, URL state, honesty copy | Pending |
| OTL1.5 | Navigation + reusable read-only inspector | Pending |
| OTL1.6 | A11y + responsive + Playwright smoke | Pending |
| OTL1.7 | Perf / security / neutrality / PluginGuard | Pending |
| OTL1.8 | Docs / closure | Pending (after merge) |

## Baseline gates

Recorded after first commit of this log + gate run.
