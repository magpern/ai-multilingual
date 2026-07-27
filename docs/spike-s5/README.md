# Spike S5 — Block identity and segment reconciliation

Research spike for Milestone 2: stable block identity and segment reconciliation.

## Status

- **Spike S5:** **Complete**
- **Selected strategy:** Strategy F (`aimlBlockId`, `b:<uuid>:content`)
- **Strategies A–F:** PHP spike complete. **F passes exit gate.**
- **Browser validation:** Phase 3 mandatory gates closed (see below).
- **Production planning:** **Allowed** — see [`../plans/STRATEGY_F_PRODUCTION_IMPLEMENTATION.md`](../plans/STRATEGY_F_PRODUCTION_IMPLEMENTATION.md)
- **Production implementation:** **Not started**
- **Production readiness:** **Not approved**
- **Strategy G:** cost analysis only — not implemented.
- **ADR-0013:** **Proposed** — not Accepted.

## Key documents

| Document | Purpose |
|---|---|
| [INVARIANTS.md](INVARIANTS.md) | Oracle model invariants |
| [IMPLEMENTATION_LOG.md](IMPLEMENTATION_LOG.md) | Per-strategy results A–F + Phase 2/3 browser validation |
| [../spikes/S5-gutenberg-segment-identity.md](../spikes/S5-gutenberg-segment-identity.md) | **Final spike decision report** |
| [../adr/0013-gutenberg-segment-identity.md](../adr/0013-gutenberg-segment-identity.md) | Draft ADR (conditional F adoption) |
| [../plans/STRATEGY_F_PRODUCTION_IMPLEMENTATION.md](../plans/STRATEGY_F_PRODUCTION_IMPLEMENTATION.md) | **Production implementation plan** (planning only) |

## Evidence

- PHP metrics: `spike/s5/corpus/strategy-{a..f}-*.json`
- Browser validation fixtures + per-fixture analysis: `spike/s5/corpus/browser-validation/`
- Browser-derived Strategy F replay: `spike/s5/corpus/strategy-f-browser-replay.json`
- Duplicate-repair-vs-real-browser-content replay: `spike/s5/corpus/browser-validation/duplicate-repair-browser-replay.json`
- Run browser suite: `spike/s5/tools/run-browser-validation.sh` (Phase 2 core matrix);
  individual Phase 3 specs live under `spike/s5/browser-validation/tests/`
  (`pattern-workflow.spec.ts`, `tier2-tier3-gaps.spec.ts`, `concurrent-edit.spec.ts`)

## Phase status

- Phase 0: assembly baseline — complete
- Phase 1c: Strategies A–F PHP evaluation — complete
- Phase 2 browser validation (core-block noop-save) — complete
- Phase 3 browser validation (mandatory identity, pattern, duplicate-repair,
  autosave/REST/export, concurrent-edit gates) — **complete**; see
  IMPLEMENTATION_LOG.md "Browser validation (Phase 3 — closing the mandatory
  gaps)" for the full results table
- Production implementation — **not started** (see [`docs/plans/STRATEGY_F_PRODUCTION_IMPLEMENTATION.md`](../plans/STRATEGY_F_PRODUCTION_IMPLEMENTATION.md))
- Strategy G — **not implemented** (costed only)
- ADR-0013 — **Proposed**, not Accepted
