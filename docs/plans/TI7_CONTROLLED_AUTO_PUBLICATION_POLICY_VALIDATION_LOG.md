# TI.7 — Controlled Auto-Publication Policy — Implementation Validation Log

**Status:** **Complete** on `main`
**Plan:** [TI7_CONTROLLED_AUTO_PUBLICATION_POLICY_IMPLEMENTATION_PLAN.md](TI7_CONTROLLED_AUTO_PUBLICATION_POLICY_IMPLEMENTATION_PLAN.md)
**ADR:** [0020-controlled-auto-publication-and-frontend-gate.md](../adr/0020-controlled-auto-publication-and-frontend-gate.md) (**Accepted**; implemented)
**Main baseline (pre-implementation):** `ffe0addf7d3c4ea69c0ef6550fb8d3bcb7c8a75e`
**Freeze merge:** `fdf313500764014ebcedd25c99b393c1679ebd3e`
**Reviewed feature HEAD:** `dfb7f3a0cebbb60143a929940def06da93a68c25`
**Merge commit:** `25fee160f323dd33b7f73d432f446caca6a72075`
**Authoritative feature CI:** `31437454100` (all SUCCESS)
**TARGET:** **7**
**Policy version:** P1.0
**Assessment consumption:** TI.5 R1.0 read-only
**TIQ:** **Complete** after this closure (TQ.0–TI.7)

---

## Closure summary

Independent implementation review **PASS**. Merged with `--no-ff`.

Shipped:

- Third Store axis: `publish_status` / `published_at` / `published_by`
- Migrator TARGET 6→7 with backfill preserving pre-TI.7 overlayable rows
- `Store::is_publicly_overlay_eligible()` + gate default off
- Seams: `translated_value`, BlockTranslationLookup, ElementorOverlayResolver, IntegrationFrontendBridge, CustomerEmailBridge
- One PublicationPolicy (P1.0) + one PublicationService
- Modes: `manual` | `approved_only` | `controlled_auto` (default `manual`)
- Sync + Jobs via TranslationService; publication failure ≠ translation failure
- Workspace / REST / CLI / diagnostics / bounded audit
- No score / LLM confidence / LLM judge / force-publish hard blockers

Independent review fixes before merge:

- gated `CustomerEmailBridge` through central eligibility helper
- PublicationService re-reads and re-evaluates immediately before mutation

---

## Work package results

| WP | Status |
|---|---|
| TI7.0 Baseline | **PASS** |
| TI7.1 Schema TARGET 7 + backfill | **PASS** |
| TI7.2 Policy/Service + frontend gate | **PASS** |
| TI7.3 Settings safe defaults | **PASS** |
| TI7.4 Sync + Jobs integration | **PASS** |
| TI7.5 Workspace/CLI/REST/diagnostics | **PASS** |
| TI7.6 False-authority + gate suites | **PASS** |
| TI7.7 SEO/Woo acceptance | **PASS** |
| TI7.8 Feature-branch closure | **PASS** |

## AC tracker

**82/82 PASS** (independent re-evaluation).

## Local / CI gates (reviewed feature HEAD)

| Gate | Result |
|---|---|
| Unit | **PASS** — 763 tests, 2156 assertions |
| Integration | **PASS** — 658 tests, 19200 assertions |
| PHPCS | **PASS** |
| quality + baseline-v1.1.0 + H1.1 | **PASS** |
| build + ZIP audit | **PASS** |
| Feature CI `31437454100` | **PASS** (phpcs, unit, integration, quality, build) |

## Architecture audit

- Third publication axis; `review_status` not overloaded; `approved ≠ published`
- TARGET 7 only as ADR-0020 authorized
- New rows default `unpublished`; migration backfill → `published` for overlayable rows
- Gate default off; automation default `manual`
- Steady-state gate: `publish_status` sole segment publication authority
- TI.5 R1.0 consumed read-only; generation markers forwarded for auto evidence completeness
- SEO/Woo ownership unchanged; no generation redesign

## Limitations / debt

- Auto paths require evidence `complete` (markers when available from generation)
- AP12 Partial (provenance); AP26 Partial (manual unpublish only)
- AP13–15, AP27–28 Deferred
- AP20 / AP29 / AP30 Unsupported

## Exact next step

Make an explicit release/version decision from the closed TIQ main baseline. Do not begin another product milestone before that decision.
