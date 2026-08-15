# AI Multilingual v1.5.1 — Release Preparation Evidence

**Status:** RELEASE PREPARATION COMPLETE · superseded for publication status by [V1_5_1_RELEASE_CLOSURE.md](V1_5_1_RELEASE_CLOSURE.md)  
**Date:** 2026-08-15

| # | Field | Value |
|---|---|---|
| 1 | Initial main HEAD | `b321bf36cb0affcf3d03f5e4da858b473179eff4` |
| 2 | Release-prep branch | `release/v1.5.1-preparation` |
| 3 | Baseline SHA | `8f049aa9c068a683ec4f7aaec7aefdb11de82b6d` |
| 4 | Corrective closure | [V151_LOCALIZED_URL_CORRECTNESS_STABILIZATION_CLOSURE.md](../plans/V151_LOCALIZED_URL_CORRECTNESS_STABILIZATION_CLOSURE.md) |
| 5 | Version before | 1.5.0 |
| 6 | Version after | **1.5.1** |
| 7 | TARGET | **8** (unchanged) |
| 8 | STATE | A |
| 9 | Migration | NONE |
| 10 | CHANGELOG | `[1.5.1] — 2026-08-15` added |
| 11 | Release notes | [v1.5.1.md](v1.5.1.md) |
| 12 | Release scope | [V1_5_1_RELEASE_SCOPE.md](V1_5_1_RELEASE_SCOPE.md) |
| 13 | Upgrade audit | 1.5.0→1.5.1 no DB migration; routes/history/settings preserved |
| 14 | Settings/default audit | Unchanged |
| 15 | Public Extension / Integration API | Unchanged |
| 16 | PHPCS | PASS |
| 17 | Unit | PASS (929) |
| 18 | Integration | PASS (PR CI) |
| 19 | PluginGuard | PASS (`test_v151_corrective_boundaries` expects 1.5.1) |
| 20 | Quality/baseline | PASS |
| 21 | Release-prep artifact | `ai-multilingual-1.5.1.zip` |
| 22 | Artifact version / TARGET | 1.5.1 / 8 |
| 23 | ZIP audit | PASS — 761244 bytes, 476 entries |
| 24 | Local SHA-256 | `f068a1f90ed692413b10c8ac836f3ae4922f14224f5e13a315d00828bafa9e91` |
| 25 | Independent review | **PASS** — [V1_5_1_RELEASE_PREPARATION_REVIEW.md](V1_5_1_RELEASE_PREPARATION_REVIEW.md) |
| 26 | Final reviewed prep HEAD | `404023e6d` |
| 27 | Release PR | https://github.com/magpern/ai-multilingual/pull/44 |
| 28 | Release PR CI | GREEN — run `31899697819` |
| 29 | Preparation merge SHA | `6298df08b3b1456e4875ecdb860b71506d5ae313` |
| 30 | Fresh main CI | GREEN — run `31899786449` |
| 31 | **V1.5.1 RELEASE-READY COMMIT** | **`6298df08b3b1456e4875ecdb860b71506d5ae313`** |
| 32 | V151AC21 | **PASS** (release ZIP built/audited; merged) |
| 33 | V151AC22 | See [V1_5_1_RELEASE_CLOSURE.md](V1_5_1_RELEASE_CLOSURE.md) — published artifact verified; DEV re-acceptance pending |
| 34 | Tag `v1.5.0` | Unmoved — `03a3a09a7ee4e1a0d7624582dcfe07af90ce89d5` |
| 35 | Tag `v1.5.1` | **CREATED** on `6298df08b3b1456e4875ecdb860b71506d5ae313` — see closure |
| 36 | GitHub Release | **CREATED** — https://github.com/magpern/ai-multilingual/releases/tag/v1.5.1 |
| 37 | Deployment | **NOT PERFORMED** (publication task) |
| 38 | Production | **UNTOUCHED** |
| 39 | Program B | **NOT STARTED** |
| 40 | Exact next step | Separately authorize published-artifact DEV re-acceptance |

## Tag semantics

Repository convention tags the **release-preparation merge commit on main** (`6298df08b…`). Later docs-only evidence commits (this file) must **not** be used as the tag target.

## Verdict

AI MULTILINGUAL v1.5.1 RELEASE PREPARATION: COMPLETE

VERSION: 1.5.1 · TARGET: 8 · MIGRATION: NONE  
V151AC21: PASS · V151AC22: NOT STARTED  
TAG / GITHUB RELEASE / DEV ACCEPTANCE: NOT STARTED
