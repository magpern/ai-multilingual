# Human Review Rubric B1.0

**Scale:** integer **1–5** per dimension. Anchors apply to EN→SV product translation quality.

## Dimension anchors

| Dimension | 1 Unusable | 3 Usable with issues | 5 Publish-ready |
|---|---|---|---|
| Semantic fidelity | Wrong or inverted meaning | Core meaning preserved; minor drift | Fully faithful meaning |
| Omission | Major content missing | Small omission or softening | Complete coverage |
| Hallucination / unsupported addition | Invented facts or unsafe claims | Minor unsupported wording | No unsupported additions |
| Terminology accuracy | Wrong domain terms | Mostly correct; occasional slip | Correct preferred terms |
| Terminology consistency | Inconsistent within segment | Mostly consistent | Fully consistent |
| Fluency / grammar | Ungrammatical or broken SV | Readable with errors | Fluent, grammatical SV |
| Naturalness | Awkward or machine-like | Acceptable but stiff | Natural Swedish |
| Tone / register | Wrong tone for context | Mostly appropriate | Fully appropriate |
| Technical meaning | Technical facts wrong | Minor imprecision | Technically accurate |
| Formatting / structural fidelity (beyond deterministic) | Broken structure beyond A checks | Minor layout/register issues | Structure feels native |
| Publish readiness (holistic) | Would not ship | Ship with edits | Ready to publish |

## Critical flags (binary)

Set `true` when any apply:

| Flag | When to set |
|---|---|
| `wrong_language` | Output is not target language (SV) |
| `meaning_inversion` | Negation, scope, or claim direction reversed |
| `invented_claims` | Unsupported medical/regulatory/safety claims added |
| `corrupted_protected_tokens` | SKU, URL, unit, or protected token damaged (human judgment beyond A) |
| `unusable_for_publish` | Holistically unusable regardless of partial strengths |

**Rule:** Class B critical flags do not override Class A deterministic failures. Both must be reported.

## Scoring workflow

1. Read source, translation, and optional reference.
2. Score all dimensions 1–5 using anchors above.
3. Set critical flags independently of dimension scores.
4. Add brief notes for regressions or calibration cases.

See `DUAL_REVIEW_PROTOCOL.md` for dual-review (~20% stratified) requirements.
