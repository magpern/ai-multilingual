# TI.7 — Controlled Auto-Publication Policy — Implementation Validation Log

**Status:** Implementation in progress on `feature/ti7-controlled-auto-publication-policy`
**Plan:** [TI7_CONTROLLED_AUTO_PUBLICATION_POLICY_IMPLEMENTATION_PLAN.md](TI7_CONTROLLED_AUTO_PUBLICATION_POLICY_IMPLEMENTATION_PLAN.md)
**ADR:** [0020-controlled-auto-publication-and-frontend-gate.md](../adr/0020-controlled-auto-publication-and-frontend-gate.md) (**Accepted**)
**Main baseline:** `ffe0addf7d3c4ea69c0ef6550fb8d3bcb7c8a75e`
**Frozen plan SHA (blob at baseline):** `docs/plans/TI7_CONTROLLED_AUTO_PUBLICATION_POLICY_IMPLEMENTATION_PLAN.md` @ main baseline
**ADR-0020 SHA (blob at baseline):** `docs/adr/0020-controlled-auto-publication-and-frontend-gate.md` @ main baseline
**Freeze merge:** `fdf313500764014ebcedd25c99b393c1679ebd3e`
**Implementation branch:** `feature/ti7-controlled-auto-publication-policy`
**TARGET before implementation:** **6**
**TARGET after implementation (planned):** **7**
**Policy version:** P1.0
**Assessment consumption:** TI.5 R1.0 read-only

---

## Frozen contracts locked at baseline

### AP1–AP30 dispositions

| ID | Disposition |
|---|---|
| AP1–AP5, AP8–AP11, AP16–AP19, AP21–AP25 | Supported |
| AP12, AP26 | Partially Supported |
| AP13–AP15, AP27–AP28 | Deferred |
| AP6–AP7, AP20, AP29–AP30 | Unsupported |

### Safe defaults

- `segment_publication_gate_enabled` = false
- `auto_publication_mode` = manual

### Migration / backfill

- Non-empty `translated_text` + status ∉ {ignored, missing} → `publish_status=published`
- New rows default `unpublished`

### Gate rollout

- Gate OFF: legacy overlay semantics
- Gate ON: `publish_status` is sole segment publication authority

### Authority exclusions

- No LLM confidence / judge
- No aggregate quality score
- No second QA / assessment
- No publication authority outside TI.7 PublicationPolicy/Service

---

## Baseline gates (main @ ffe0addf7)

| Gate | Result |
|---|---|
| Main CI (planning freeze close) | **PASS** — run `31434136393` |
| Main CI (freeze merge) | **PASS** — run `31434133468` |
| TARGET | **6** |

Feature-branch gate totals recorded at closure.

---

## Work package tracker

| WP | Status |
|---|---|
| TI7.0 | In progress |
| TI7.1 | Pending |
| TI7.2 | Pending |
| TI7.3 | Pending |
| TI7.4 | Pending |
| TI7.5 | Pending |
| TI7.6 | Pending |
| TI7.7 | Pending |
| TI7.8 | Pending |

## AC tracker

82 ACs — evaluated at feature-branch closure.
