# OTL.0 Foundations — Implementation Validation Log

**Status:** OTL.0 implementation complete — ready for independent review  
**Branch:** `feature/otl0-foundations`  
**Plan:** [OTL0_FOUNDATIONS_IMPLEMENTATION_PLAN.md](OTL0_FOUNDATIONS_IMPLEMENTATION_PLAN.md)  
**Parent:** [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)

## Baseline

| Field | Value |
|---|---|
| Implementation baseline SHA | `3823c4b3dd9b941b84d9679c9c54ac5e4c9062ce` |
| Frozen plan freeze merge | `9b922222564da4f3294e36188de992c1384c630c` |
| OTL parent freeze merge | `9a31176f0147d726b251315259cd6d6ca84ea432` |
| Plugin version | **1.2.0** (unchanged) |
| TARGET | **7** (unchanged) |
| Schema migration | None |
| UI delivery | None (backend foundation only) |
| Integration API | Unchanged |
| TSC | Not started |
| OTL.1–OTL.6 | Not started |
| Public/SaaS neutrality | Hard invariant |

## Scope

OTL0.0–OTL0.8 per frozen plan. OF1–OF30 dispositions unchanged.

## Invariants

- No persisted composite operator state
- List path: zero AssessmentAssembler / PublicationService::explain / DeterministicQA attributable to OTL list
- `allowed_actions` = UI admission only (not mutation authority)
- TI.7 exclusive publication eligibility
- TI.4 / TI.5 ownership preserved (detail QA reuse: **path B** — independent calls; shared-detect deferred as debt)

## Work packages

| WP | Result |
|---|---|
| OTL0.0 Baseline | PASS |
| OTL0.1 Operator read model | PASS — `OperatorTranslationAssembler` |
| OTL0.2 AllowedActionsResolver | PASS |
| OTL0.3 Store query primitives | PASS — `query_operations`, `get_by_translation_id` |
| OTL0.4 List REST | PASS — `GET /aiml/v1/workspace/operations` |
| OTL0.5 Detail REST | PASS — `GET /aiml/v1/workspace/operations/{translation_id}` |
| OTL0.6 Performance/security | PASS |
| OTL0.7 Regression/PluginGuard/neutrality | PASS |
| OTL0.8 Docs/closure prep | PASS — REVIEW-READY (not Complete on main) |

## OF1–OF30

| ID | Disposition | Evidence |
|---|---|---|
| OF1 | Supported | `OperatorTranslationAssembler` |
| OF2 | Supported | `OperatorTranslationListItemViewModel` |
| OF3 | Supported | `OperatorTranslationDetailViewModel` |
| OF4 | Supported | `AllowedActionsResolver` |
| OF5 | Supported | capability_flags ∩ state |
| OF6 | Deferred | list has no QA summary |
| OF7 | Deferred | list has no assessment |
| OF8 | Supported | detail assessment |
| OF9 | Unsupported | list has no publication eligibility |
| OF10 | Supported | detail publication explain |
| OF11 | Supported | review_status axis |
| OF12 | Supported | publish_status axis |
| OF13 | Supported | is_stale axis |
| OF14 | Supported | provider/model/error fields |
| OF15 | Partial | tm_id on detail only |
| OF16 | Deferred OTL.4 | `jobs: null` |
| OF17 | Partial | error_code/message when present |
| OF18 | Supported | links via WP helpers |
| OF19 | Supported | language + pagination |
| OF20–24 | Supported | Store filters |
| OF25 | Unsupported | no FULLTEXT |
| OF26 | Deferred OTL.1 | no attention endpoint |
| OF27 | Unsupported | no composite persist |
| OF28 | Unsupported | no Integration API |
| OF29 | Unsupported | no action command bus |
| OF30 | Supported | PluginGuard neutrality |

## Local gates (feature branch)

| Gate | Result |
|---|---|
| `git diff --check` | PASS |
| PHPCS (full tree) | PASS (586 files) |
| Unit | PASS — **770** tests, 2174 assertions, 2 skipped |
| Integration | PASS — **675** tests, 22782 assertions, 2 skipped |
| PluginGuard | PASS (incl. OTL boundaries + neutrality) |
| Quality validate | PASS — cases=60 |
| Baseline verify | PASS — cases=60 critical=0 dual=13 |
| Build ZIP | PASS — `ai-multilingual-1.2.0.zip` |
| ZIP audit | PASS — 546355 bytes, 340 entries |

## Performance evidence

| Metric | Result |
|---|---|
| List AssessmentAssembler invocations | **0** (`OperationsScaleTest`) |
| List PublicationService::explain | **0** |
| List QAEngine | **0** |
| Detail assessment/explain/qa | **1 each** per translation |
| Pagination | DB `LIMIT/OFFSET`; page 1 vs 22 disjoint at 1100 rows |
| Page size | default 20, max 50 (clamped) |
| Preview cap | 200 Unicode chars + ellipsis |
| Index / TARGET | TARGET **7**; no new index; admitted queries use existing axes |

## QA reuse outcome

**Path B:** Detail calls `AssessmentAssembler`, `QAEngine`, and `PublicationService::explain` independently. No TI.4/TI.5 redesign. Shared-detect optimization recorded as later debt.

## Acceptance criteria (72/72)

| AC | Result | Evidence |
|---|---|---|
| 1–5 Parent/boundary | PASS | No UI; no TSC; no Playwright product work |
| 6–12 Read model | PASS | Assembler + VMs; no persist; axes distinct; `translation_id` on OTL VMs only |
| 13–21 allowed_actions | PASS | Resolver + unit matrices; no mutation endpoints; no cache |
| 22–32 TI.7/4/5 | PASS | Detail-only explain; list zeros; PluginGuard no heuristic |
| 33–37 Axes | PASS | ADR-0015/0020 vocab; approved ≠ published |
| 38–43 Jobs/nav/previews | PASS | jobs null; WP links; 200-char preview; object access |
| 44–52 Store | PASS | query_operations + PK get; filters; pagination; no FULLTEXT |
| 53–55 Attention | PASS | filter/total primitives; no attention UX |
| 56–62 REST/VMs | PASS | routes + header + caps; snake_case |
| 63–66 Perf | PASS | scale tests |
| 67–72 Schema/CI/neutrality | PASS | TARGET 7; PluginGuard; ZIP 1.2.0 |

**Verified AC count: 72/72 PASS.**

## Architecture audit

- [x] One computed read model
- [x] No persisted operator state
- [x] List cheap; no assessment/explain/QA per row
- [x] Detail authoritative TI.4/TI.5/TI.7
- [x] Path B QA reuse (no TI.4/TI.5 redesign)
- [x] Server-computed allowed_actions ≠ mutation authority
- [x] TI.7 exclusive eligibility
- [x] DB-backed pagination; no load-all; no FULLTEXT
- [x] No schema; TARGET 7
- [x] Jobs deferred; no Integration API; no OTL.1 UI; no TSC
- [x] Site-neutral product code

## Limitations / debt

- Shared DeterministicQA single-pass for TI.4+TI.5 not implemented (path B duplicate local work on detail only).
- Rich Jobs linkage deferred to OTL.4.
- `retranslate_stale` admission is Partial/metadata (`deferred_milestone`); orchestration is OTL.3.
- Attention UX endpoint deferred to OTL.1.

## Merge readiness

**REVIEW-READY** on `feature/otl0-foundations`. Do **not** self-merge. Independent review required before main.

## Exact next step

Independently review `feature/otl0-foundations`. If it passes, merge it to main, run fresh full CI, close OTL.0, and only then begin the definitive OTL.1 planning process.
