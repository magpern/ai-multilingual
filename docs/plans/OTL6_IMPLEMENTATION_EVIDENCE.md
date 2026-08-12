# OTL.6 Implementation Evidence

**Branch:** `feature/otl6-final-operator-lifecycle-polish`
**Baseline main:** `986902f5dc8ed1790c5346d5b70e70b2dc5ee818`
**Frozen plan:** `docs/plans/OTL6_FINAL_OPERATOR_LIFECYCLE_POLISH_IMPLEMENTATION_PLAN.md`
**Freeze merge:** `7e4bdd7e1e750abdd143ce10ba865437b15ea1f0`
**Version:** 1.2.0 · **TARGET:** 7 · **ADR:** none · **TSC:** not started

## Work packages

| WP | Status | Evidence |
|---|---|---|
| OTL6.0 | Done | `OTL6_IMPLEMENTATION_BASELINE.md` |
| OTL6.1 | Done | `ConfirmDialog.tsx`; Operations Panel confirms via `requestConfirm` |
| OTL6.2 | Done | App `requestLeaveOperations` / `requestViewChange`; panel dirty predicate registration; shared async Modal; `beforeunload` retained |
| OTL6.3 | Done | `operations-session.ts`; URL clear includes `language`; remount `peek ?? URL` |
| OTL6.4 | Done | `operations-labels.ts`; bulk outcome + message/reason_codes |
| OTL6.5 | Done | Review VM `translation_id`; Review→Ops; bulk→Jobs; Jobs→Ops **not** implemented |
| OTL6.6 | Done | focus restore on detail close; focus-visible; column priority CSS |
| OTL6.7 | Done | `acceptance/otl-browser/`; otl1–5 READMEs; otl3 testMatch; otl4 package/login |
| OTL6.8 | Done | PluginGuard TS neutrality + OTL.6 forbids |

## A1–A4

- **A1** Centralized async dirty-leave admission (`useConfirmDialog` + App gate + panel registration).
- **A2** Session snapshot; URL canonical only on Operations; selection/bulk non-persistent.
- **A3** Jobs→Ops Partial retained (no Jobs `translation_id` enrichment).
- **A4** Authoritative `otl-browser`; archives preserved; live Playwright non-CI.

## OP1–OP24 / AC1–AC52

All Supported OPs implemented; OP15 Partial; OP24 forbids held. AC1–AC52 independently evaluated against code/tests (see closure after merge).

## Authority

Store/TranslationService · ADR-0015 · TI.4/5 · TI.6 Jobs · TI.7 Publication · OTL.5 bulk coordinator · OTL.6 UI only.
