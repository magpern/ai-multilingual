# ADR-0015 — Review Workflow and approval-gated TM write-back

## Status

**Accepted** (2026-08-05) — Review Workflow and approval-gated TM write-back.

**Decision maker:** Product Owner  
**Approval date:** 2026-08-05  
**Decision:** ADR-0015 Accepted  
**Scope:** Two-axis Store-owned review state; submitted translation hash; approval/rejection lifecycle; minimal rejection metadata; approval-gated TM write-back; frontend rendering remains independent from review state — exactly as defined in this ADR and the frozen [REVIEW_WORKFLOW_IMPLEMENTATION_PLAN.md](../plans/REVIEW_WORKFLOW_IMPLEMENTATION_PLAN.md).

**Residual risks accepted:**

- Existing translations default to `review_status=not_submitted`
- Existing TM entries remain valid and are not retroactively removed
- Approval does not gate frontend rendering
- Rejected text remains renderable when otherwise eligible
- No version history or review snapshot body is stored
- Review concurrency relies on submitted translation hashes
- Warning-level QA does not block approval

**Review point:** Review Workflow milestone closure  

**Revalidation trigger:** approval-gated rendering; separate review storage; multi-stage review; assignments or comments; version history; automatic TM deletion or rollback; breaking Store or REST contract changes.

**Implementation gate:** **Open** — R1–R7 authorized on `feature/review-workflow`. Gate A (Accepted) satisfied. Gate B (provisional approval) is **not applicable**.

Canonical plan: [REVIEW_WORKFLOW_IMPLEMENTATION_PLAN.md](../plans/REVIEW_WORKFLOW_IMPLEMENTATION_PLAN.md).  
Product context: [POST_V1_PRODUCT_ROADMAP.md](../plans/POST_V1_PRODUCT_ROADMAP.md) §11.2.  
Prior freeze: [F11_FROZEN_API.md](../plans/F11_FROZEN_API.md) (TM write-back amended from save-time to approval-time by this ADR).

---

## Context

Glossary MVP is complete. The next product milestone is **Review Workflow**: an approval layer on the existing Workspace / Store / TM / Glossary / QA stack.

Today:

- Store `status` mixes translation-content provenance (`machine_translated`, `manually_edited`, `reviewed`, …) with no formal review lifecycle.
- Columns `reviewed_by` / `reviewed_at` exist but are unused.
- F11 TM write-back runs after eligible saves (`human` / `ai_accepted` / `import`) **without** requiring formal approval.
- Frontend render eligibility uses `RENDERABLE_STATUSES` and is independent of any review workflow.

Overloading `status` with values such as `pending_review` / `rejected` would conflate **translation-content state** with **review-workflow state**, break TM origin heuristics that rely on content status, and muddy Workspace semantics.

Changing TM write-back from save-time to approval-time is an **intentional F11 policy amendment** and requires an explicit ADR gate (same governance pattern as ADR-0014 for glossary ranking).

---

## Decision

1. **Store remains the single owner** of translation rows and review metadata. No `aiml_review_*` tables. No assignment/queue/comment stores. The “review queue” is a **filtered Store query** on `review_status` (and language/object scope), never separately persisted queue data.

2. **Two-axis state model.**
   - **Translation axis** — existing `status` catalog and meanings stay frozen for backward compatibility.
   - **Review axis** — additive `review_status` with catalog: `not_submitted` | `pending` | `approved` | `rejected`.
   - Approval/rejection **must not** rewrite `translated_text`, `source_text`, `source_hash`, translation origin fields, or `status` unless an existing frozen contract explicitly requires it (MVP: **do not** change `status` on approve/reject).

3. **Additive Store columns (schema target 5).** At minimum:
   - `review_status` (default `not_submitted`)
   - `review_submitted_by`, `review_submitted_at`
   - `submitted_translation_hash` (deterministic hash of submitted translated text; reuse `Store::translation_hash` semantics)
   - activate existing `reviewed_by`, `reviewed_at` on approve
   - `rejection_reason`, `rejected_by`, `rejected_at`
   - Indexes suitable for queue queries (`review_status`, language, object scope, submitted/updated time)

4. **Edit invalidates review.** Any successful change to `translated_text` clears approval/rejection metadata and sets `review_status=not_submitted`. A no-op save must not reset review state. Resubmit requires an explicit submit after invalidation.

5. **Submitted-hash concurrency.** On submit, persist `submitted_translation_hash`. On approve/reject, compare current `translation_hash` to `submitted_translation_hash`; mismatch → stable conflict (**HTTP 409**), no state transition, refreshed segment/review ViewModel, resubmit required. Do not copy full translation text into review metadata. No version-history storage.

6. **Rendering remains independent of review.** Review Workflow does **not** change frontend-render eligibility. No Review code in `BlockRenderGate` or `BlockFrontendRenderer`. Pending/rejected rows remain renderable if already renderable under existing Store contracts. “Approved” ≠ “published.” Approval-gated rendering is **out of scope** and would require a separate architecture decision.

7. **TM write-back moves to approval time (F11 amendment).**
   - After Review is enabled: pending/rejected never write TM; approved eligible translations write back once via existing `TranslationMemoryService` policy/provenance; machine-origin remains excluded unless AI-accepted under existing policy.
   - Rejecting does **not** delete historical TM entries.
   - Duplicate approval must not duplicate TM rows or inflate usage.
   - Migration: existing rows default `review_status=not_submitted`; existing TM entries remain valid (no retroactive removal). Do **not** auto-approve from legacy `status=reviewed` without reliable evidence — fail closed to `not_submitted`.

8. **Capability.** Introduce `aiml_review_translations` (Administrator initially). Translators (`aiml_translate`) submit/correct/resubmit; reviewers approve/reject without changing translated text through review actions.

9. **REST additive** under `aiml/v1/workspace` (submit/approve/reject/batch-review/review-queue). Optimistic fields: `source_hash`, `submitted_translation_hash`, expected `review_status`. Bounded batch via coordinator (not controller loops). ViewModels only.

10. **QA.** Reuse `QAEngine`. Errors block approval only when existing block-on-error policy is enabled; warnings (including `glossary_term_missing`) never block approval; rejection does not require QA pass; no second QA pipeline.

11. **Audit privacy.** Structured `aiml_review_audit` events; no translation body; rejection reason presence/length only in general logs; authorized Workspace may see full rejection reason via ViewModel.

---

## Consequences

### Positive

- Clear separation of translation provenance vs review lifecycle
- Backward-compatible `status` and render contracts
- Formal approval gate for TM quality without a second store
- Concurrency safety without version history

### Negative / residual risks

- F11 clients assuming save-time TM write-back must adapt (explicit amendment)
- Two-axis UI complexity (status badge + review badge)
- Schema v5 additive migration and index design
- Reviewers must resubmit after post-submit edits (409 conflicts)
- Legacy `status=reviewed` is **not** treated as formal approval without evidence

### Out of scope (this ADR)

Assignments, collaboration comments, notifications, reporting dashboards, multi-stage enterprise approval, version history, background jobs, approval-gated frontend rendering, nested blocks, Elementor, WooCommerce expansion, new AI providers, render-cache work.

---

## Provisional approval log

**Not applicable** — ADR-0015 is fully **Accepted** (gate A). Gate B provisional approval is not used.

---

## References

- [REVIEW_WORKFLOW_IMPLEMENTATION_PLAN.md](../plans/REVIEW_WORKFLOW_IMPLEMENTATION_PLAN.md)
- [POST_V1_PRODUCT_ROADMAP.md](../plans/POST_V1_PRODUCT_ROADMAP.md) §11.2
- [F11_FROZEN_API.md](../plans/F11_FROZEN_API.md)
- [ADR-0007](0007-hash-semantics.md) — hash semantics; historical `reviewed_hash` deferred (version history still out of scope)
- [ADR-0014](0014-glossary-platform-lexicon.md) — glossary complete; review must not redesign glossary
