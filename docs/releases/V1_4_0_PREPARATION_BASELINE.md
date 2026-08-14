# v1.4.0 Release Preparation Baseline

**Branch:** `release/v1.4.0-preparation`
**Baseline main HEAD:** `505b818117bd830611f004ffe4bd16ac275d5286`
**Previous tag:** `v1.3.0` → `c88ba30681439d9e7113a20d7ebc03c942dd240d`
**Version decision:** **1.4.0**
**TARGET:** 7 (unchanged)
**Programs:** TIQ Complete; OTL Complete; **TSC PROGRAM COMPLETE — TSC.0–TSC.6**
**Preparation contents:** version metadata, CHANGELOG, readme Stable tag, release notes, release scope — no new product architecture beyond released TSC program work already on `main`

## Release version decision

| Option | Verdict |
|---|---|
| Patch `1.3.x` | **Rejected** — understates complete Translation Surface Coverage program and formal Extension API v1 |
| Minor **1.4.0** | **Selected** — additive public Extension API v1; TSC.0–TSC.6 complete; no breaking public contract; TARGET remains 7; no schema migration |
| Major `2.0.0` | **Rejected** — no backwards-incompatible public contract or forced destructive upgrade |

## Scope since v1.3.0

All production work on `main` between tag `v1.3.0` and baseline `505b81811` is TSC.0–TSC.6 (Translation Surface Coverage) plus planning/closure documentation. No unrelated product programs.

## Deployment

Release preparation does **not** deploy to production. Tag-triggered GitHub Release builds and attaches the audited ZIP; site deployment is a separate operator action.
