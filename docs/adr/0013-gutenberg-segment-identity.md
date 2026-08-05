# ADR-0013 — Persistent Gutenberg block identity

## Status

**Accepted** (2026-08-05) — Strategy F production identity model.

Disposition recorded under F13.1 ([STRATEGY_F_F13_GENERAL_ROLLOUT.md](../plans/STRATEGY_F_F13_GENERAL_ROLLOUT.md)).
Human approval checklist completed against merged F1–F12 evidence.
Elementor-primary coverage and cross-site paste remain **open questions**
(non-blocking; listed below) and do **not** reverse this acceptance.

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
| F — persistent UUID in attrs | **Accepted** | **pass** |
| G — registry table | Documented fallback (not selected) | not implemented |

Full evidence: `docs/spikes/S5-gutenberg-segment-identity.md`.

## Decision (Accepted)

**Adopt Strategy F** as the block segment identity algorithm.

1. ~~Browser validation extended to duplicate/copy/paste, patterns, and
   representative third-party blocks~~ — **done (Phase 3).** Finding:
   `aimlBlockId` **is** systematically stripped on real edits **unless
   registered** in block metadata; this is evidence *for* production
   registration, not evidence against Strategy F.
2. ADR-0013 attribute contract ratified: `aimlBlockId`, RFC 4122 v4,
   segment key grammar `b:<uuid>:<field>` (initial field: `content` only).
3. Save-time UUID injection implemented as editor/server filter,
   and `aimlBlockId` registered via `block_type_metadata`/`block.json` for
   every eligible core and supported third-party block type.
4. Migration plan for existing content accepted (one-time `post_content`
   mutation per document).
5. Synced-pattern references are explicitly excluded from tagging.

**Retain Strategy G** as the documented historical fallback only. It is not
the production path.

## Consequences

### Strategy F accepted

- Segment keys are stable across reorder, move, wrap, identical-content
  scenarios that rejected C–E.
- Canonical `post_content` gains ~55 bytes per eligible block (UUID JSON).
- Renderer remains pure; identity lifecycle lives in extraction/sync.
- Duplicate UUID repair (first-wins) runs on every save.
- Text edits preserve UUID but suppress render until re-translation when
  `source_hash` differs (availability trade-off, not false positive).

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

## Open questions (non-blocking)

1. ~~Does Gutenberg **copy** `aimlBlockId` on duplicate/paste?~~ **Answered
   (Phase 3).**
2. Elementor-primary site: Gutenberg block coverage on production content?
   **Still open** — Elementor-authored content was out of scope for S5 and
   remains a future spike/ADR. **Does not block this acceptance.**
3. ~~Cross-post paste behavior?~~ **Answered (Phase 3)** for same-site.
   **Cross-site** paste remains untested. **Does not block this acceptance.**
4. ~~Production registration of `aimlBlockId`?~~ **Answered (Phase 3):**
   registration is required and shipped in F1+.
5. ~~Should Strategy F tag blocks inside pattern entities?~~ **Answered: no.**
6. ~~Concurrent duplicate of the same block?~~ **Answered (Phase 3).**

## Rollout prerequisites

Production implementation plan:
[`docs/plans/STRATEGY_F_PRODUCTION_IMPLEMENTATION.md`](../plans/STRATEGY_F_PRODUCTION_IMPLEMENTATION.md).

**Baselines:** spike evidence `42237bd`; production-plan baseline `ea5af19`
(amended on `spike/s5` before F1).

- [x] Extended browser validation matrix (Phase 3)
- [x] Decide and implement `aimlBlockId` registration strategy (F1)
- [x] Production UUID injection hook (F2)
- [x] Block adapter model + initial allowlist (F4)
- [x] Renderer proof gate (F5) passed before general rendering (F6)
- [x] Migration runbook + canonical-only backfill + autosave/revision policy (F7 / §6.4)
- [x] Cross-post UUID ownership policy accepted (§4.2)
- [x] Feature-flag dependency rules + ordered rollback accepted (§15 / F12)

## Human approval checklist (required before Accepted)

Completed 2026-08-05 against merged F1–F12 evidence (F13.1):

- [x] `post_content` mutation approved — shipped F1–F2; operating under F12 observation
- [x] Permanent registration compatibility requirement approved (post-rollout) — F1 / §2.2
- [x] Key grammar `b:<uuid>:<field>` approved (initial field: `content`) — `Contract`
- [x] Initial block adapter allowlist approved (paragraph, heading, button) — F4–F6
- [x] Synced-pattern exclusion approved — `BlockRegistry` / `core/block` exclusion
- [x] Autosave/revision semantics approved (plan §6.4) — F2
- [x] Renderer proof (F5) completed and accepted — F5/F6
- [x] Frontend metadata / structured sanitizer policy approved — F6
- [x] Rollout cohort approved — F12 PO decision sheet (post 6321 / `sv`)
- [x] Rollback behavior approved (plan §15.4) — F12 rollback rehearsal PASS

This ADR is **Accepted** after all checklist items passed and Strategy F
milestones **F1–F12** prerequisites were met.
