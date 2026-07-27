# ADR-0013 — Persistent Gutenberg block identity

## Status

**Proposed** (Spike S5 Phase 3 browser validation complete; awaiting
Milestone 2 architecture review). **Not Accepted.** Strategy F is
conditionally accepted for planning purposes only — see "Proposed decision"
below for the still-open production prerequisites.

## Context

Spike S5 evaluated seven segment-identity strategies (A–G) against a
non-negotiable render-safety exit gate: `rendered_false_positive == 0`.
Strategies A–E were rejected on evidence. Strategy F (injected persistent
UUID attribute `aimlBlockId`) passes the PHP spike exit gate. Strategy G
(registry table) was costed but not implemented.

The root architectural tension (see `AI_MULTILINGUAL_PLANNING.md`) is that
identity is reconstructed from content because the system forbids recording
identity — invariant I2 (no writes to `wp_posts`/`wp_postmeta` for
translation) plus the frozen schema (no registry table). ADR-0013 must decide
whether alignment loss is acceptable without persistent identity, or whether
Strategy F's `post_content` mutation (or Strategy G's schema change) is
accepted.

Browser validation on `dev.biopentra.eu` (WP 7.0.2, Playwright 1.51.0) is now
complete across the mandatory Phase 3 gates: Gutenberg preserves
`aimlBlockId` on no-op save and on delete+undo for the full tested core-block
matrix, but **strips it on every other real edit operation** (text edit,
duplicate, copy/paste, reorder, wrap/unwrap, move, transform, split/merge,
undo/redo of an edit, and pattern creation) because the attribute is not
registered in block metadata. A spike-only attribute-registration test
confirms registering it fixes preservation through ordinary edits and
duplication — at the cost of duplication now genuinely copying the UUID,
which Strategy F's first-wins repair was verified to resolve against real
browser-produced duplicate content, including under an adversarial
concurrent-edit (lost-update) simulation. `rendered_false_positive` is **0**
across 59 browser-derived replay fixtures.

## Decision drivers

1. **Render safety** — wrong translation on page is unacceptable (I7 context:
   spike gate is stricter: suppress rather than false render).
2. **Reviewed translation preservation** — structural edits must not silently
   reattach translations to different logical blocks.
3. **Operational cost** — mutating canonical `post_content`, editor
   compatibility, migration, and long-term maintenance.
4. **I2 compliance** — Strategy F injects via save-time filter (planned); G
   requires new table and allocation lifecycle.
5. **Evidence, not assertion** — spike + browser validation before production.

## Strategies considered

| Strategy | Verdict | Exit gate |
|---|---|---|
| A — positional index | Rejected | fail |
| B — content fingerprint | Rejected | fail |
| C — structural path | Rejected | fail |
| D — path + exact hash rematch | Rejected | fail |
| E — D + render gate | Rejected | fail (identical-content path reuse) |
| F — persistent UUID in attrs | **Recommended conditional** | **pass (PHP)** |
| G — registry table | Costed fallback | not implemented |

Full evidence: `docs/spikes/S5-gutenberg-segment-identity.md`.

## Proposed decision (conditional)

**Adopt Strategy F** as the Milestone 2 block segment identity algorithm **if**
all production prerequisites pass:

1. ~~Browser validation extended to duplicate/copy/paste, patterns, and
   representative third-party blocks~~ — **done (Phase 3).** Finding:
   `aimlBlockId` **is** systematically stripped on real edits **unless
   registered** in block metadata; this is evidence *for* production
   registration, not evidence against Strategy F.
2. ADR-0013 attribute contract ratified: `aimlBlockId`, RFC 4122 v4,
   segment key grammar `b:<uuid>:<field>` (initial field: `content` only).
3. Save-time UUID injection implemented as editor/server filter (not manual
   WP-CLI backfill), **and** `aimlBlockId` registered via
   `block_type_metadata`/`block.json` for every eligible core and supported
   third-party block type (Phase 3 evidence: unregistered survives only
   no-op saves; registered survives ordinary edits too, at the cost of
   duplication now copying the UUID — which first-wins repair handles).
4. Migration plan for existing content accepted (one-time `post_content`
   mutation per document).
5. Synced-pattern references are explicitly excluded from tagging (Phase 3
   pattern-gate finding: pattern entities never retain the attribute through
   pattern creation, and a referencing post's own `post_content` never
   contains the pattern's block content to tag in the first place).

**Retain Strategy G** as the documented fallback if browser validation fails
or `post_content` mutation is rejected by stakeholders. Strategy G does not
automatically solve logical identity without a persistent marker or
equivalent heuristics (see cost analysis in S5 report).

## Consequences

### If Strategy F is accepted

- Segment keys become stable across reorder, move, wrap, identical-content
  scenarios that rejected C–E.
- Canonical `post_content` gains ~55 bytes per eligible block (UUID JSON).
- Renderer remains pure; identity lifecycle lives in extraction/sync.
- Duplicate UUID repair (first-wins) runs on every save.
- Text edits preserve UUID but suppress render until re-translation when
  `source_hash` differs (availability trade-off, not false positive).

### If Strategy F is rejected

- Must choose Strategy G (schema + operational complexity) or accept
  reconciliation loss / human re-linking for structural edits.
- Spike evidence shows no non-mutating strategy passes exit gate.

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| Gutenberg strips unknown attrs | Browser gate; block.json `attributes` registration in production plugin |
| Third-party blocks drop attrs | Allowlist + suppress render when UUID missing; document unsupported blocks |
| Duplicate UUID on copy/paste | Inject-time first-wins repair + render gate |
| Revision noise on backfill | One-time migration; idempotent inject |
| I2 perceived violation | UUID is identity metadata, not translation; injection on save via controlled filter |

## Rejected alternatives

- **C/D/E alone** — evidenced false positives or insufficient render safety.
- **Strategy B** — key collisions on duplicate content.
- **Silent hash rematch without gate** — false continuity (E proved necessity of gate).

## Open questions

1. ~~Does Gutenberg **copy** `aimlBlockId` on duplicate/paste?~~ **Answered
   (Phase 3):** not with the unregistered attribute (stripped before
   duplication can copy it); **yes**, verbatim, with the attribute
   registered — and Strategy F's first-wins repair was verified against
   that real browser-produced duplicate, including under a two-session
   concurrent-edit race.
2. Elementor-primary site: Gutenberg block coverage on production content?
   **Still open** — not addressed by this spike; Elementor-authored content
   was out of scope for both phases.
3. ~~Cross-post paste behavior?~~ **Answered (Phase 3):** same-site cross-post
   copy/paste strips the attribute identically to same-post copy/paste — no
   different failure mode. **Cross-site** paste remains untested (no second
   WordPress installation available in this environment).
4. ~~Production registration of `aimlBlockId` in block.json vs bare JSON
   attr?~~ **Answered (Phase 3):** registration is **required** if attribute
   survival through ordinary editing (not just no-op save) is a product
   requirement. This is now a decision the ADR must make explicitly, not an
   open question — see decision driver 3 above.
5. **New:** should Strategy F tag blocks living inside pattern entities?
   **Answered (Phase 3): no.** See decision driver 5 and the pattern-gate
   finding in `docs/spikes/S5-gutenberg-segment-identity.md` §13.
6. **New:** what happens when two editors concurrently duplicate the same
   block? **Answered (Phase 3, adversarial single-scenario evidence, not a
   full concurrency proof):** WordPress's normal last-write-wins semantics
   apply (no merge, no optimistic lock) — the later save fully replaces the
   earlier one. Strategy F's repair still reaches `rendered_false_positive
   == 0` on the resulting content, but the lost-update itself is a
   WordPress editing property Strategy F does not and cannot fix.

## Rollout prerequisites

Production implementation plan (planning only, no code):
[`docs/plans/STRATEGY_F_PRODUCTION_IMPLEMENTATION.md`](../plans/STRATEGY_F_PRODUCTION_IMPLEMENTATION.md).

**Baselines:** spike evidence `42237bd`; production-plan baseline `ea5af19`
(amended on `spike/s5` before F1).

- [x] Extended browser validation matrix (Phase 3 — Tier 1/2/3 mandatory
  operations, duplicate repair against real browser content, concurrent-edit
  simulation, autosave/REST/export/import; remaining minor gaps are
  explicitly listed in IMPLEMENTATION_LOG.md and do not block this checkbox)
- [ ] Decide and implement `aimlBlockId` registration strategy
  (`block_type_metadata`/`block.json`) for production — Phase 3 evidence
  shows this is required for survival through ordinary edits; after any
  production UUID exists, registration becomes a **compatibility requirement**
  (not a normal post-rollout kill switch — see plan §2.2, §15.4)
- [ ] Production UUID injection hook (not spike WP-CLI tools)
- [ ] Block adapter model + initial allowlist (`core/paragraph`, `core/heading`,
  `core/button`; field `content` only) — plan §1.2, §3
- [ ] Renderer proof gate (F5) passed before general rendering (F6) — plan §18
- [ ] Migration runbook + canonical-only backfill + autosave/revision policy
  (plan §6.4, §9)
- [ ] Cross-post UUID ownership policy accepted (plan §4.2)
- [ ] Feature-flag dependency rules + ordered rollback accepted (plan §15)

## Human approval checklist (required before Accepted)

ADR-0013 must remain **Proposed** until each item is explicitly approved:

- [ ] `post_content` mutation approved
- [ ] Permanent registration compatibility requirement approved (post-rollout)
- [ ] Key grammar `b:<uuid>:<field>` approved (initial field: `content`)
- [ ] Initial block adapter allowlist approved (paragraph, heading, button)
- [ ] Synced-pattern exclusion approved
- [ ] Autosave/revision semantics approved (plan §6.4)
- [ ] Renderer proof (F5) completed and accepted
- [ ] Frontend metadata / structured sanitizer policy approved
- [ ] Rollout cohort approved
- [ ] Rollback behavior approved (plan §15.4)

Do **not** promote this ADR to Accepted until all checklist items pass and
Strategy F milestones F1–F11 prerequisites are met.
