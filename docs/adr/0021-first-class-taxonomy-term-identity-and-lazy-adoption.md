# ADR-0021 — First-class taxonomy-term identity, lazy adoption, and visitor-only overlays

## Status

**Accepted** (2026-08-12) — First-class taxonomy-term identity, lazy adoption, and visitor-only overlays for TSC.1.

**Decision maker:** Product Owner  
**Approval date:** 2026-08-12  
**Decision:** ADR-0021 **Accepted** (planning freeze)  
**Scope:** `SOURCE_TERM` / TERM_ID; taxonomy `source_subtype`; native name/description identity; lazy hosted→native adoption; temporary read-alias; native precedence; honest hosted retirement; single authoritative writer; permanent dual-write prohibition; Store adoption persistence primitive; lifecycle/evidence preservation; adoption/mutation serialization; adoption triggers; read-only compatibility resolver; visitor-only overlays; no broad `get_term` mutation; `pa_*` term values vs attribute taxonomy labels.

**Does not authorize:** TSC.1 production coding until an implementation task opens `feature/tsc1-first-class-taxonomy-terms` against the Architecture Frozen plan. Does **not** bump `Migrator::TARGET` or plugin version.

**Residual risks accepted:**

- Temporary coexistence of hosted (shop/posts-page) and native term rows until adopt
- Store introduces transactional row locking (new code path; no schema change)
- Axis mutations may still target hosted rows until a content-write adopt — under Store authority serialization
- Parent plan wording that mentioned `ignored`/`orphaned` for adopt retirement is superseded by honest `ignored` without `orphaned`
- Exactly-once adoption is not claimed beyond InnoDB transaction + idempotent retry
- Attribute taxonomy labels and product-local attributes remain out of scope (TSC.3)

**Evidence / plan base:**

- [TSC_PARENT_IMPLEMENTATION_PLAN.md](../plans/TSC_PARENT_IMPLEMENTATION_PLAN.md) §6 TERM_ID; §15 ADR requirement
- [TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md](../plans/TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md)
- [TSC0_INTERNAL_SURFACE_CAPABILITY_FOUNDATION_IMPLEMENTATION_PLAN.md](../plans/TSC0_INTERNAL_SURFACE_CAPABILITY_FOUNDATION_IMPLEMENTATION_PLAN.md)
- ADR-0001, ADR-0005, ADR-0007, ADR-0015, ADR-0017, ADR-0020

**Related:** Overlay-not-duplication; segment-centric storage; hash semantics; review/publication axes; SurfaceCapability (TSC.0).

**Revalidation triggers:** Proposal to dual-write hosted+native permanently; proposal to mutate `WP_Term` / term tables for overlays; proposal to add durable alias table or TARGET bump for TERM_ID; proposal to treat attribute taxonomy labels as SOURCE_TERM; proposal to adopt on every axis mutation without Store serialization audit; proposal to put write/lock APIs on the read resolver.

---

## Context

Today (post-TSC.0, pre-TSC.1):

- Taxonomy term translations for Woo catalog (and Rank Math term SEO) are **hosted** on shop / posts-page Store identities with integration-owned segment keys.
- Store unique key already admits arbitrary `source_type` strings; only `SOURCE_POST` is defined in code.
- SurfaceCapability is source-type-generic, but coordinator flush, Extractor, SegmentAssembler, Jobs worker, and several OTL gates remain post-shaped.
- `save_translation` clears review/publish axes on material text change — unsafe as an adoption path.
- `ignored`+`orphaned` means missing extract unit, not “superseded by another identity.”
- Concurrent axis mutation vs adoption can strand lifecycle writes on a retired hosted row if unsynchronized.

Without this ADR:

- Terms remain second-class hosted chrome with no TERM_ID
- Adoption could destroy review/publication evidence via `save_translation`
- Dual-write or unbounded migration might be invented
- Frontend might mutate `get_term` broadly
- Attribute labels could be falsely claimed as term coverage

---

## Decision

### 1. SOURCE_TERM / TERM_ID

```text
source_type    = 'term'           // Store::SOURCE_TERM
source_id      = term_id          // BIGINT
source_subtype = taxonomy slug    // VARCHAR(32); not part of uniqueness
```

Native segment keys: `name`, `description`. Term slug translation is out of scope for TSC.1.

### 2. Schema — STATE A

Existing unique key `(source_type, source_id, segment_hash, language_id)` is sufficient. **No** TARGET bump. **No** durable alias table. **No** second Store.

### 3. Lazy adoption + temporary read-alias

- Adopt on **content writes** (manual save, retranslate persist, Jobs provider persist), optionally bounded maintenance CLI.
- Do **not** adopt on GET, frontend render, OTL inspect, or QA/assessment read.
- Axis-only mutations (submit/approve/reject/publish/unpublish) do **not** adopt, but **must** use Store authority serialization with adoption (§6).

### 4. Native precedence + single writer

After native exists: native is authoritative; hosted is never the active write target; permanent dual-write is forbidden.

### 5. Hosted retirement (honest)

Successful adopt retires hosted as `status=ignored` with `error_code=''` (cleared). **Do not** use `orphaned` for supersession. True orphan remains missing-source / deleted extract semantics.

### 6. Adoption persistence + mutation serialization

- `Store::adopt_row_to_identity` — transactional clone/move; **must not** use `save_translation`.
- `Store::with_term_compat_authority` — lock order: native candidate key, then hosted key; shared by adopt and hosted-compat axis persist. Absent native uses unique-key `SELECT … FOR UPDATE` (InnoDB gap/next-key). Do not lock hosted by `translation_id` before entering the helper.
- Under lock: if native appeared, remap axis to native; never write retired hosted as authoritative.
- TI.5/TI.7 still decide whether review/publication is legal; this ADR only freezes identity/authority serialization.

### 7. Lifecycle / evidence preservation

Copy columns only when still semantically valid under native identity and hash contract (see plan column matrix). Especially:

- Recompute `segment_hash` when keys change
- Preserve publish axis on adopt
- Preserve review evidence only if `submitted_translation_hash` still matches `translation_hash` (else honest reset)

### 8. Compatibility resolver (read-only)

One internal `TermTranslationResolver`: native first, else deterministic hosted lookup; returns provenance; **never writes or locks**; never decides publication policy.

### 9. Visitor-only overlays

Admit only the frozen seam table in the TSC.1 plan (`single_term_title`, `term_description` with hard guards, `woocommerce_page_title` for product taxonomies). **Forbid** broad `get_term` mutation, canonical WP_Term mutation, term table writes, and admin/REST/internal overlay leakage.

### 10. `pa_*` values vs attribute taxonomy labels

- Global attribute **term values** may be SOURCE_TERM when admitted.
- Global attribute **taxonomy labels/names** and product-local attributes remain **TSC.3 / out of scope**.

### 11. Rank Math term SEO

In TSC.1: adopt hosted Rank Math term rows to `source_type=term` while **retaining** `p:rankmath:term:{term_id}:*` keys; same retirement and serialization rules.

### 12. Surface integration

Implement via TSC.0 `SurfaceCapability` / registry (`TermSurfaceAdapter`). Additive `extract_segments`; registry-driven coordinator flush. No public surface/taxonomy admission API.

---

## Consequences

### Positive

- Terms become first-class without schema migration
- Lifecycle axes survive adopt when semantically valid
- Axis/adopt races cannot strand mutations on retired hosted rows
- Frontend stays overlay-only

### Negative / costs

- New Store locking infrastructure
- Temporary hosted/native coexistence complexity
- OTL/Jobs post-shaped paths need additive rewires

### Neutral

- Version remains 1.3.0 through planning and (expected) implementation unless a later release decision
- TARGET remains 7

---

## Implementation gate

**Implementation gate:** **Open for TSC.1 implementation** after Architecture Frozen on `main` (freeze merge `1fcf8d2e3088b09174526643e13a2d8ccf5cb2d4`) when an implementation task opens `feature/tsc1-first-class-taxonomy-terms`.
