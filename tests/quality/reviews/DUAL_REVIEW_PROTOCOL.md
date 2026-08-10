# Dual Review Protocol (B1.0)

## Purpose

Calibrate human judgment on ~20% of C1.0 cases (~12 stratified samples) without destroying individual reviewer provenance.

## Stratification

Select cases across categories with emphasis on:

- placeholder / protected tokens
- HTML-rich segments
- scientific terminology
- marketing tone
- Woo short vs long descriptions

## Process

1. **Primary reviewer** completes all 60 cases using `templates/review_sheet.template.json`.
2. **Secondary reviewer** independently reviews the stratified subset.
3. Store both originals under `human.B1.0.json`:
   - `reviews` — primary sheets keyed by `case_id`
   - `dual_review` — `{ case_id: { primary, secondary, consensus? } }`
4. **Consensus is additive only.** Never overwrite `primary` or `secondary` with consensus-only data.
5. If reviewers disagree on a critical flag, document resolution in `consensus.notes` without deleting either original.

## Class A precedence

Deterministic Class A failures remain failures regardless of human or LLM judge scores. Class C advisory output cannot clear Class A.

## Artifact

Official packs include `human.B1.0.json` conforming to `schemas/human_review.schema.json`.
