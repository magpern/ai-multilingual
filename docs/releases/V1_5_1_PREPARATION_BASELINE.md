# v1.5.1 Release Preparation Baseline

**Branch:** `release/v1.5.1-preparation`  
**Starting main HEAD:** `b321bf36cb0affcf3d03f5e4da858b473179eff4`  
**Previous tag:** `v1.5.0` → `03a3a09a7ee4e1a0d7624582dcfe07af90ce89d5` (unmoved)  
**Version before:** 1.5.0  
**Target version:** **1.5.1**  
**TARGET:** 8 (unchanged)  
**STATE:** A  
**Migration:** NONE  

## Corrective implementation references

| Item | Reference |
|---|---|
| Plan | [V151_LOCALIZED_URL_CORRECTNESS_STABILIZATION_IMPLEMENTATION_PLAN.md](../plans/V151_LOCALIZED_URL_CORRECTNESS_STABILIZATION_IMPLEMENTATION_PLAN.md) |
| Evidence | [V151_IMPLEMENTATION_EVIDENCE.md](../plans/V151_IMPLEMENTATION_EVIDENCE.md) |
| Independent review | [V151_INDEPENDENT_REVIEW.md](../plans/V151_INDEPENDENT_REVIEW.md) — **PASS** |
| Implementation closure | [V151_LOCALIZED_URL_CORRECTNESS_STABILIZATION_CLOSURE.md](../plans/V151_LOCALIZED_URL_CORRECTNESS_STABILIZATION_CLOSURE.md) |
| Feature PR | [#43](https://github.com/magpern/ai-multilingual/pull/43) |
| Merge SHA | `3ec082f7858d44af33ed95008e3c694c7fdb1570` |

## Release kind

**PATCH** corrective release restoring Supported contracts broken under Localized URLs ON (Gate B dogfood). Not a new capability program. Not MSEO.6. Not Program B.

## Explicit exclusions (this preparation)

- No `v1.5.1` tag  
- No GitHub Release  
- No DEV or production deployment  
- No published-artifact DEV re-acceptance (V151AC22)  
- No TARGET/schema migration  
- No public API expansion  
- No Program B  

## Authorization

This branch may bump version metadata, CHANGELOG, release notes/scope, build/audit `ai-multilingual-1.5.1.zip`, and merge after review + CI. Tag/release/deploy require separate authorization.
