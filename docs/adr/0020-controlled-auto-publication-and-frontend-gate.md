# ADR-0020 — Controlled auto-publication and frontend publication gate

## Status

**Proposed** (2026-08-10) — Architecture amendment for TI.7 Controlled auto-publication policy.

**Decision maker:** Product Owner  
**Approval date:** _(pending independent architecture review)_  
**Decision:** _(pending)_  
**Scope:** Third Store publication axis; Migrator TARGET 6→7 additive schema; frontend segment publication gate; safe-default automation OFF; PublicationPolicy / PublicationService authority boundary; TI.5 read-only consumption; Jobs publication-failure separation; migration backfill preserving currently-public overlays. Does **not** authorize TI.7 production coding until [TI7_CONTROLLED_AUTO_PUBLICATION_POLICY_IMPLEMENTATION_PLAN.md](../plans/TI7_CONTROLLED_AUTO_PUBLICATION_POLICY_IMPLEMENTATION_PLAN.md) is Architecture Frozen on `main` after this ADR is **Accepted**.

**Residual risks accepted (when Accepted):**

- Upgrade introduces a new Store axis and planned TARGET 7; runtime TARGET remains 6 until TI.7 implementation migrates
- `segment_publication_gate_enabled` defaults **false** so upgrades do not change visitor-facing overlays until operators opt into the gate
- While the gate is disabled, `publish_status` may be written/managed but is **not** visitor-facing authority (legacy render eligibility applies)
- Segment-level unpublished does **not** remove language-level SEO relationships (hreflang/sitemap remain ADR-0008 / A.SEO language-published)
- Stale published translations are not auto-unpublished; they remain marked stale under existing freshness rules
- Publication failure after successful translation persistence is a separate result (Jobs item stays translation-successful)

**Implementation gate:** **Closed** for production coding until this ADR is **Accepted** and the TI.7 plan is Architecture Frozen on `main`. This ADR does **not** bump runtime `Migrator::TARGET` by itself.

**Evidence / plan base:**

- [TIQ_PARENT_IMPLEMENTATION_PLAN.md](../plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md) §4 invariant 7; §9 TI.7; §11 TI.7 gate; §14 “Controlled auto-publication / frontend gate change | ADR required before TI.7”
- [TI7_CONTROLLED_AUTO_PUBLICATION_POLICY_IMPLEMENTATION_PLAN.md](../plans/TI7_CONTROLLED_AUTO_PUBLICATION_POLICY_IMPLEMENTATION_PLAN.md)
- [ADR-0008](0008-language-state-model.md) (language publication)
- [ADR-0015](0015-review-workflow-and-tm-approval-policy.md) (review ≠ published; render independence revalidation)
- [ADR-0019](0019-evidence-based-risk-assessment.md) (TI.5 assessment; no `publish_decision`)
- [ADR-0011](0011-resumable-job-pipeline.md) (Jobs; no auto-publish)

**Related:** ADR-0001 (overlay not duplication); ADR-0005 (segment-centric storage); ADR-0007 (hash / staleness); A.SEO ownership unchanged.

**Revalidation triggers:** Proposal to overload `review_status` as publication; proposal to treat LLM/TM confidence or aggregate quality score as publication authority; proposal to add `publish_decision` to TI.5 assessment; proposal to make Jobs translation failure depend on publication failure; proposal to auto-unpublish on source change; proposal to enable automation by default on upgrade; proposal to permanently retain two contradictory visitor-facing publication authorities without an explicit steady-state.

---

## Context

TIQ milestones TQ.0–TI.6 delivered measurement, persist structural safety, bounded context, TM intelligence, deterministic QA, evidence-based assessment (R1.0), and Jobs scale/safety polish.

Today (pre-TI.7):

- Store owns provenance `status` and ADR-0015 `review_status`.
- There is **no** durable segment-level `publish_status`.
- Frontend overlays use `RENDERABLE_STATUSES` / non-empty text (and path-specific freshness rules). They do **not** consult `review_status`.
- Machine-translated rows with `review_status=not_submitted` are publicly overlayable when the language is routable.
- Language `status=published` (ADR-0008) controls routes/SEO membership — a different layer from segment overlay.
- ADR-0015 explicitly states **Approved ≠ published** and that approval-gated rendering requires a separate architecture decision.
- The TIQ parent requires an ADR for **controlled auto-publication / frontend gate change** before TI.7.

Without this ADR:

- “Auto-publication” has no durable state to flip, or would falsely overload `approved`
- Policy could invent scores/LLM confidence as publish authority
- Overlay paths could diverge (partial gate → unpublished leak)
- Upgrades could silently auto-publish or strip currently-public overlays

---

## Decision

### 1. Three orthogonal translation axes

Each Store translation row has three independent axes:

| Axis | Owner / vocabulary | Meaning |
|---|---|---|
| **Provenance / content** | existing `status` | How the text was produced (`machine_translated`, `manually_edited`, `reviewed`, …) |
| **Review** | ADR-0015 `review_status` | `not_submitted` \| `pending` \| `approved` \| `rejected` |
| **Publication** | **new** `publish_status` | `unpublished` \| `published` |

**Do not overload `review_status` to mean published.** Approval remains review/TM write-back semantics only.

### 2. Additive schema — TARGET 6 → 7

TI.7 implementation **shall** bump Migrator `TARGET` from **6** to **7** with additive translation-table columns (TARGET-5 review pattern):

- `publish_status` `VARCHAR` — catalog `unpublished` \| `published`; schema/default for **new** rows: `unpublished`
- `published_at` nullable datetime
- `published_by` nullable user id (system/auto actor recorded as `0` or null with audit `actor_kind=system` — implementation chooses one consistent convention)

No new publication tables. No second Store. Integration API v1 unchanged.

**This docs task does not change runtime TARGET.** Runtime remains 6 until TI.7 implementation.

### 3. Migration / backward compatibility

Deterministic backfill on step 7:

> Set `publish_status=published` (and set `published_at` to a deterministic migration timestamp or existing `updated_at` if present) for every existing row that would be **frontend-overlay-eligible under pre-TI.7 rules**: content `status ∈ RENDERABLE_STATUSES`, non-empty translated text.

Purpose: **existing currently-public overlays must not disappear merely by upgrading.**

After migration:

- **New** persists default to `unpublished` until an explicit PublicationService publish (manual or automatic under policy).
- Installing/upgrading TI.7 **must not** enable automatic publication.

### 4. Approved ≠ published (ADR-0015 revalidation)

ADR-0015 remains owner of review workflow and TM write-back.

This ADR **does not** make `approved` imply `published`.

**Supersession (narrow):** ADR-0015 §6 residual assumption that “rendering remains independent of review” remains true for **review**. When the **publication gate is enabled**, rendering becomes dependent on **`publish_status`**, not on `review_status`. That is the separate architecture decision ADR-0015 deferred.

### 5. Frontend publication gate — canonical long-term truth

**Steady-state model (authoritative):**

When `segment_publication_gate_enabled` is **true**, `publish_status` is the **sole segment-level publication authority** for public translation overlays. Content `status ∈ RENDERABLE_STATUSES` (and path-specific non-empty / non-stale rules) remain **content-validity prerequisites**, not publication authority.

**Rollout / compatibility control:**

| Setting | Default | Role |
|---|---|---|
| `segment_publication_gate_enabled` | **false** | Backward-compatible rollout switch. When **false**, overlays use **pre-TI.7** render eligibility (ignore `publish_status` for visitor-facing overlay). When **true**, overlays require `publish_status=published`. |

This switch is **not** a second permanent definition of “published.” It selects whether the new axis is **active** for visitors yet.

- **Intended steady-state:** gate **enabled** after operators verify backfill and operational readiness.
- **Leaving the gate permanently false** means TI.7 can still record/manage `publish_status` and run explain/automation dry-runs, but visitors continue under legacy overlay rules — publication control is dormant. That is an explicit transitional/ops choice, not dual authority.

**Authoritative gating seams** (all must consume the same publication check when the gate is enabled; no partial rollout):

1. `Store::translated_value()` — classic field overlays
2. `Renderer` title / content / excerpt filters (via Store or shared helper)
3. `BlockTranslationLookup` / block frontend path (`BlockRenderGate` remains request/feature gate; lookup applies segment eligibility)
4. `ElementorOverlayResolver` (+ applier path)
5. Any WooCommerce / taxonomy / metadata overlay that reads Store translation text for public display (must share the same Store-level or shared eligibility helper — **no path-local policy**)

Implementation must centralize eligibility so unpublished cannot leak through one path while another respects the gate.

### 6. Automation safe defaults

| Setting | Default | Role |
|---|---|---|
| `auto_publication_mode` | **`manual`** | Closed vocabulary (see §7). `manual` means **no automatic publication**. |

Installing or upgrading **must never** silently enable automatic publication.

Disabling automation (setting mode to `manual`) stops **future** automatic publication only. It does **not** delete translations and does **not** mass-unpublish existing `published` rows.

Gate enablement and automation are **independent**:

- Gate → whether `publish_status` governs rendering
- Mode → whether TI.7 may automatically transition `unpublished` → `published`

### 7. Closed publication modes

`auto_publication_mode ∈ { manual, approved_only, controlled_auto }`

| Mode | Auto-publish when |
|---|---|
| `manual` | Never |
| `approved_only` | `review_status=approved` **and** all publication guards pass |
| `controlled_auto` | Approval **not** required; requires `overall_category=structurally_clean`, `evidence_completeness=complete`, allowed provenance, non-rejected review, public source, not stale, and other guards |

**Never auto-publish** when assessment category is `blocked`, `needs_review`, or `review_recommended`.

No policy DSL. No score thresholds. No per-FieldSemantic / content-type / language-pair matrix in this ADR (Deferred to later product decisions outside this freeze).

### 8. PublicationPolicy vs PublicationService

- **`PublicationPolicy.evaluate(...)`** → versioned **`PublicationDecision` (P1.0)** — pure / non-mutating. Records `policy_version`, `eligible`, reason codes, assessment version/category, mode, and guard results (source, stale, review, evidence, provenance, current `publish_status`). **No** quality score. **No** LLM confidence.
- **`PublicationService.apply(...)`** — mutates Store only after **re-evaluating** current eligibility immediately before write. Idempotent: already `published` → safe success/no-op.

Callers (sync persist success, Jobs item success, Workspace, CLI) **invoke** the service; they **must not** embed policy.

Dry-run / explain uses the **same** Policy.

### 9. TI.5 consumption

Consume ADR-0019 / TI.5 `TranslationAssessment` **read-only** (recompute at decision time).

- Do **not** add `publish_decision` to the assessment contract
- Do **not** persist assessment as publish authority
- `structurally_clean` is **necessary but not sufficient** for `controlled_auto`
- `not_applicable` / evidence `unavailable` / `partial` **must not** count as positive evidence for auto paths (`complete` required)

### 10. Authority exclusions (hard)

Publication decisions **must not** use:

- LLM self-confidence / quality percentage
- LLM judge approval
- Aggregate numerical quality thresholds
- TM / suggestion ranking confidence as publication authority

### 11. Review-state rules

- `rejected` → blocks automatic **and** manual publish
- `approved_only` auto path requires `approved`
- `controlled_auto` requires non-`rejected` plus assessment/guards (approval not required)
- Manual publish may proceed for soft categories when **not** `blocked` and not otherwise guarded — operators accept responsibility; **hard structural `blocked` cannot be force-published**

### 12. Provenance (narrow)

For `controlled_auto`: `provenance_class` in `{missing, unknown}` blocks auto-publication. `tm_direct_reuse` is **not** an automatic pass — full policy still applies. No TM confidence.

### 13. Source visibility and staleness

- **Source guard:** refuse publish (auto and manual) when the source object is not in a public-visibility class (e.g. draft / private / trash / non-public product). Translation publication **must not** mutate Woo stock, price, catalog visibility, product `post_status`, purchasability, inventory, or order behavior.
- **Staleness:** `is_stale` blocks **new** publish of that row as current. `source_hash` architecture unchanged (ADR-0007).
- **Translation content edit:** any successful change to translated content that invalidates the prior published text **must** set `publish_status=unpublished` and clear publication metadata (parallel to ADR-0015 edit invalidation of review).
- **Source change after publish:** do **not** automatically unpublish. Existing stale marking remains; stale published rows are reportable for review. Auto-unpublish on source change is **Deferred** / out of scope.

### 14. Triggers and Jobs failure separation

One canonical PublicationService path for:

- synchronous translation persist success
- Jobs item translation success
- manual Workspace publish
- CLI publish

**Jobs:** translation/persistence success remains success even if subsequent publication fails or is skipped. Record a separate publication result / audit reason. **No automatic publication retry storm**; operator retry is sufficient.

### 15. Idempotency and concurrency

- Publish when already `published` → success/no-op
- Concurrent manual/auto paths must re-read current row and re-evaluate policy before mutation
- Optional optimistic `expected_publish_status` for Workspace/CLI conflict detection (implementation detail)

### 16. Audit

Emit bounded `aiml_publication_audit` (or equivalent allowlisted action) events:

- manual publish, automatic publish, skipped, failed, manual unpublish

Payload: translation/object identity, previous/new publish state, policy version, assessment version/category, reason codes, actor/system classification, timestamps. **No** source/target/prompt bodies or secrets.

Reuse Review/Jobs audit conventions. Do not create an event-store product.

### 17. Manual unpublish

Manual unpublish is in scope (Workspace/CLI). Automatic unpublish / rollback of previously published translations is **out of scope** except as future Deferred work.

### 18. SEO / language publication

A.SEO remains owner of document SEO emitters.

- Language-level `published` (ADR-0008) continues to govern routes, hreflang set membership, and sitemap language inclusion.
- Segment `publish_status` governs **whether translated overlay text is applied**. Unpublished segments fall back to source text for that segment; they do **not** by themselves remove the language from hreflang/sitemap.
- TI.7 must not implement a second SEO pipeline.

### 19. Rollback

Operator rollback of automation:

1. Set `auto_publication_mode=manual`
2. Optionally disable `segment_publication_gate_enabled` to restore legacy overlay behavior

Neither step deletes translations or mass-unpublishes.

---

## Consequences

### Positive

- Real controlled auto-publication with durable, explainable state
- Preserves ADR-0015 review semantics and ADR-0019 assessment fence
- Upgrade-safe backfill + automation OFF by default
- Single policy/service path for sync and Jobs
- Clear steady-state publication truth when gate enabled

### Negative / residual risks

- Operators must understand gate vs automation settings
- While gate is off, publish_status is dormant for visitors (documented)
- Segment unpublished ≠ language de-indexed (SEO honesty)
- TARGET 7 migration complexity (additive; resume-safe pattern required)

### Out of scope (this ADR)

FieldSemantic/content-type/language-pair policy matrices; bulk/scheduled publication; automatic unpublish on source change; force-publish of hard blockers; LLM/score authority; Integration API v2; translator/prompt/TM redesign; second SEO pipeline; TI.7 production coding before plan freeze.

---

## Provisional approval log

**Pending** — awaiting independent architecture review for gate A (Accepted).

---

## References

- [TIQ_PARENT_IMPLEMENTATION_PLAN.md](../plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md)
- [TI7_CONTROLLED_AUTO_PUBLICATION_POLICY_IMPLEMENTATION_PLAN.md](../plans/TI7_CONTROLLED_AUTO_PUBLICATION_POLICY_IMPLEMENTATION_PLAN.md)
- [ADR-0008](0008-language-state-model.md)
- [ADR-0015](0015-review-workflow-and-tm-approval-policy.md)
- [ADR-0019](0019-evidence-based-risk-assessment.md)
- [ADR-0011](0011-resumable-job-pipeline.md)
