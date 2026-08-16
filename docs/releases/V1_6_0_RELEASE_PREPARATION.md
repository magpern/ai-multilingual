# AI Multilingual v1.6.0 — Release Preparation Evidence

**Status:** IN PROGRESS (updated through merge/CI)  
**Date:** 2026-08-16  
**Preparation branch:** `release/v1.6.0-preparation`

| # | Field | Value |
|---|---|---|
| 1 | Initial main HEAD | `bdf28f96cc6b74ee655a30286f722b5bd0678822` |
| 2 | Reconciled main HEAD | `bdf28f96cc6b74ee655a30286f722b5bd0678822` |
| 3 | Release-preparation branch | `release/v1.6.0-preparation` |
| 4 | Baseline SHA | `bdf28f96cc6b74ee655a30286f722b5bd0678822` |
| 5 | P0 status | COMPLETE |
| 6 | P1 status | COMPLETE (NO SUPPORTED-CONTRACT DEFECT) |
| 7 | P2 status | COMPLETE |
| 8 | Version before | 1.5.1 |
| 9 | Version after | **1.6.0** |
| 10 | TARGET before | 8 |
| 11 | TARGET after | **8** |
| 12 | Migration | **NONE** |
| 13 | CHANGELOG | `[1.6.0] — 2026-08-16` |
| 14 | Release notes | [v1.6.0.md](v1.6.0.md) |
| 15 | Release scope | [V1_6_0_RELEASE_SCOPE.md](V1_6_0_RELEASE_SCOPE.md) |
| 16 | Schema audit | TARGET 8; no step_9; P0/P1/P2 require no DB migration |
| 17 | Public API audit | Extension/Integration unchanged; P0 thin admin REST; P2 domain reuse |
| 18 | Upgrade audit | 1.5.1→1.6.0 no DB migration; routes/settings preserved |
| 19 | Permissions/security | Run admin-only; term slug caps; no silent overwrite; no secrets in package |
| 20 | PHPCS | PASS (touched PHP; full suite on CI) |
| 21 | Unit | PASS (929) |
| 22 | Integration | Required GREEN on PR CI / main CI |
| 23 | JS/admin | PASS (106) |
| 24 | PluginGuard | Version asserts updated to 1.6.0; full suite on CI |
| 25 | PackageGuard | On CI |
| 26 | Quality/baseline | PASS |
| 27 | Focused P0 | Covered by PluginGuard P0 boundaries + packaging audit |
| 28 | Focused P2 | Covered by PluginGuard P2 boundaries + unit/JS |
| 29 | P1/SEO regression | Characterization docs; no runtime redesign |
| 30 | Build result | PASS (`composer install --no-dev` + `bin/build-zip.sh`) |
| 31 | ZIP filename | `ai-multilingual-1.6.0.zip` |
| 32 | ZIP byte size | 771477 |
| 33 | ZIP entry count | 480 |
| 34 | ZIP SHA-256 | `af945dbb3bc4a9dd76ce30037cd5546542a6ef3795ca4de63ac1de3d5754ac4a` |
| 35 | ZIP audit | PASS (`bin/audit-zip.sh`) |
| 36–38 | Independent review | [V1_6_0_RELEASE_PREPARATION_REVIEW.md](V1_6_0_RELEASE_PREPARATION_REVIEW.md) — **PASS** |
| 39 | Final reviewed prep HEAD | *(set after commit)* |
| 40 | PR | *(set after open)* |
| 41 | PR CI | *(pending)* |
| 42 | Preparation merge SHA | *(pending)* |
| 43 | Fresh main CI | *(pending)* |
| 44 | Exact release-ready commit | *(pending — code commit to tag later)* |
| 45 | Final main HEAD | *(pending)* |
| 46 | Clean / main==origin | *(pending)* |
| 47 | Tag `v1.5.1` | Unchanged @ `6298df08b3b1456e4875ecdb860b71506d5ae313` |
| 48 | Tag `v1.6.0` | **NOT CREATED** |
| 49 | GitHub Release | **NOT CREATED** |
| 50 | Deployment | **NOT PERFORMED** |
| 51 | Production | **UNTOUCHED** |
| 52 | Next | Separately authorize `v1.6.0` tag + GitHub Release |

## Release-prep defect remediated in-branch

- **Missing ZIP asset:** `assets/term-slug-admin/` was not copied by `bin/build-zip.sh` / not required by `bin/audit-zip.sh`. Fixed so P0 term UI ships in the package; re-audited PASS.

## Tag / deployment boundary

**TAG NOT AUTHORIZED · GITHUB RELEASE NOT AUTHORIZED · DEPLOYMENT NOT AUTHORIZED**
