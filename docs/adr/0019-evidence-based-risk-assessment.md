# ADR-0019 — Evidence-based risk assessment contract

## Status

**Accepted** (2026-08-10) — Durable read-only TI.5 assessment contract frozen for later TI.7 consumption.

**Decision maker:** Product Owner  
**Approval date:** 2026-08-10  
**Decision:** ADR-0019 **Accepted** (planning freeze)  
**Scope:** Versioned, recomputed, evidence-based translation assessment contract produced by TI.5. Does **not** define publication policy, Integration API v2, persisted quality state, opaque sole scores, LLM confidence, review-state redesign, Jobs lifecycle mutation, schema/TARGET changes, or Store identity changes.

**Residual risks accepted:**

- Assessment is current-state only; historical TQ.0 packs remain the measurement baseline, not certification of live rows
- Partial provenance (especially post-hoc TM-assisted generation) may report `unknown` rather than invent strength
- Workspace presentation may lag domain richness; policy must remain in the assessment core, not the UI
- TI.7 may later refuse to auto-publish even when assessment reports `structurally_clean`

**Implementation gate:** **Open for TI.5 implementation** only after [TI5_EVIDENCE_BASED_REVIEW_RISK_SIGNALS_IMPLEMENTATION_PLAN.md](../plans/TI5_EVIDENCE_BASED_REVIEW_RISK_SIGNALS_IMPLEMENTATION_PLAN.md) is **Architecture Frozen** on `main`. This ADR does **not** authorize TI.6 or TI.7 coding.

**Evidence / plan base:**

- [TIQ_PARENT_IMPLEMENTATION_PLAN.md](../plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md) §4 invariants 6–7; §9 TI.5; §11 TI.5 gate
- [TI5_EVIDENCE_BASED_REVIEW_RISK_SIGNALS_IMPLEMENTATION_PLAN.md](../plans/TI5_EVIDENCE_BASED_REVIEW_RISK_SIGNALS_IMPLEMENTATION_PLAN.md)
- [TI4_DETERMINISTIC_QA_HARDENING_IMPLEMENTATION_PLAN.md](../plans/TI4_DETERMINISTIC_QA_HARDENING_IMPLEMENTATION_PLAN.md)
- [ADR-0015](0015-review-workflow-and-tm-approval-policy.md) (review ≠ published)

**Related:** ADR-0010 (provider-agnostic interface; unchanged by this ADR); ADR-0014 (glossary lexicon; preferred-term evidence only); ADR-0015 (review workflow ownership retained).

**Revalidation triggers:** Proposal to persist canonical assessment scores; proposal to make LLM/suggestion/TM confidence publish authority; proposal to add `publish_decision` to the assessment contract; proposal to bump TARGET/schema for assessment storage; proposal to expose Integration API v2 for assessment; proposal to redesign ADR-0015 review states inside TI.5; proposal to invent a second detector engine for risk scoring.

---

## Context

TIQ milestones TQ.0–TI.4 established measurement, persist structural safety, bounded context, TM intelligence, and shared deterministic QA detection with policy-neutral `RawFinding` objects.

The TIQ parent freezes TI.5 as **Evidence-based review / risk signals**: derive review/risk signals from **observable evidence only**, not LLM self-confidence percentages. TI.7 later owns controlled auto-publication and must consume risk evidence without reinventing assessment.

Without an explicit contract:

- Workspace, Jobs, and future publish policy risk diverging “risk” interpretations
- Soft warnings or approval state could accidentally greenwash hard structural findings
- A sole numerical score could become de-facto publish authority
- Persisted scores would go stale whenever QA methodology, review state, or text changes

---

## Decision

1. **One assessment core.** TI.5 introduces a single computed assessment assembler + risk/readiness policy over existing evidence (TI.1 structural outcomes, TI.4 shared detectors + policies, Store content/review metadata, optional in-request TM outcomes, bounded generation provenance). Surfaces are thin consumers.

2. **Read-only, recomputed, current-state.** Assessment is calculated on demand from current evidence. No new assessment table/column as canonical state. No Migrator `TARGET` bump for TI.5. Request-scoped memoization may exist but is non-canonical.

3. **Versioned contract.** Assessments carry `assessment_version` (initial conceptual `R1.0`) and a methodology reference. Methodology-breaking changes bump the version. Assessment versioning is independent of TQ.0 `H1.x`.

4. **Explainable multi-facet output.** Required visibility: `overall_category`, facets, hard blockers, errors, warnings, `review_status`, `provenance_class`, evidence-completeness, conflicts, reason/finding references. **No** authoritative sole numerical score in TI.5 (RA14 Deferred). **No** `publish_decision` field.

5. **Closed readiness taxonomy.** `overall_category` ∈ `{ blocked, needs_review, review_recommended, structurally_clean }` with explicit non-claims: `structurally_clean` ≠ semantic perfection ≠ safe to auto-publish; `approved` ≠ published and does not erase hard findings.

6. **Hard/soft/human precedence.** Hard structural evidence dominates; deterministic errors dominate warnings; warnings are advisory; human review is a separate facet; approved + hard findings → conflict, not greenwashing; `not_applicable` / evidence unavailable ≠ PASS.

7. **Evidence authority exclusions.** LLM self-confidence, suggestion ranking confidence, TM retrieval confidence, and TQ.0 Class C advisory judgments are **not** TI.5 authorities.

8. **TI.7 consumption fence.** TI.7 may later consume this contract read-only. TI.5 must not import TI.7 types or implement publication mutation. Dependency direction is TI.1–TI.4 → TI.5 → TI.7 (later). This is an **internal** PHP/ViewModel contract, not Integration API v2 and not a public cross-plugin API.

9. **ADR-0015 remains owner of review state.** TI.5 consumes `review_status` and related metadata; it does not redesign submit/approve/reject.

---

## Consequences

### Positive

- Durable, explainable risk signals without inventing a second QA engine or translator
- Honest handling of incomplete evidence (TI.4 N/A carry-forward)
- Clear fence for TI.7 publication policy work
- No schema/TARGET pressure for a stale score store

### Negative / residual risks

- Consumers must recompute (or accept request-scoped memo) rather than read a durable score column
- Partial provenance honesty may under-claim TM-assisted history
- UI must not invent policy when ViewModel fields are incomplete

### Out of scope (this ADR)

TI.7 publication thresholds/hooks; Jobs scale/retry polish (TI.6); detector admission; glossary mode redesign; vector/semantic TM; additional AI providers; Integration API v2; review-state redesign; opaque sole quality score as authority.

---

## Provisional approval log

**Not applicable** — ADR-0019 is fully **Accepted** at TI.5 planning freeze (gate A). Gate B provisional approval is not used.

---

## References

- [TIQ_PARENT_IMPLEMENTATION_PLAN.md](../plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md)
- [TI5_EVIDENCE_BASED_REVIEW_RISK_SIGNALS_IMPLEMENTATION_PLAN.md](../plans/TI5_EVIDENCE_BASED_REVIEW_RISK_SIGNALS_IMPLEMENTATION_PLAN.md)
- [ADR-0015](0015-review-workflow-and-tm-approval-policy.md)
- [ADR-0010](0010-provider-agnostic-interface.md)
