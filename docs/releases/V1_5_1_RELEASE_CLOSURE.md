# AI Multilingual v1.5.1 — Release Closure

**Status:** **TAGGED / GITHUB RELEASE PUBLISHED** — published artifact independently verified; **DEV runtime re-acceptance PASS** ([V1_5_1_DEV_RUNTIME_REACCEPTANCE.md](../validation/V1_5_1_DEV_RUNTIME_REACCEPTANCE.md))  
**Version:** 1.5.1  
**Schema TARGET:** **8** (unchanged)  
**Migration:** **NONE**  
**Release-ready commit (tagged):** `6298df08b3b1456e4875ecdb860b71506d5ae313`  
**Annotated tag:** `v1.5.1`  
**Preparation branch:** `release/v1.5.1-preparation` (PR #44)  
**Release workflow:** `31900093937` SUCCESS  
**GitHub Release:** https://github.com/magpern/ai-multilingual/releases/tag/v1.5.1  
**Previous release:** `v1.5.0` @ `03a3a09a7ee4e1a0d7624582dcfe07af90ce89d5` (unmoved)

## Preflight (tag time)

| Field | Value |
|---|---|
| Starting / docs-only main HEAD | `1e8718bf753a82e8f44af9410054b3edf4af18f6` |
| `origin/main` at tag time | `1e8718bf753a82e8f44af9410054b3edf4af18f6` |
| Working tree | Clean; `main` == `origin/main` |
| Drift after release-ready | Docs-only (`1e8718bf7` release-preparation evidence) — no production-code drift |
| Release-preparation review | **PASS** — [V1_5_1_RELEASE_PREPARATION_REVIEW.md](V1_5_1_RELEASE_PREPARATION_REVIEW.md) |
| Release PR | https://github.com/magpern/ai-multilingual/pull/44 |
| Preparation merge SHA | `6298df08b3b1456e4875ecdb860b71506d5ae313` |
| Fresh main CI (post-merge) | GREEN — run `31899786449` |
| Plugin version @ release-ready | **1.5.1** |
| `Migrator::TARGET` @ release-ready | **8** |
| Migration / `step_9` | Absent |
| Tag `v1.5.1` before task | Did not exist |
| Tag `v1.5.0` | Unchanged @ `03a3a09a7ee4e1a0d7624582dcfe07af90ce89d5` |

## Tag

| Field | Value |
|---|---|
| Tag | `v1.5.1` |
| Type | Annotated |
| Message | `AI Multilingual v1.5.1` |
| Target commit | `6298df08b3b1456e4875ecdb860b71506d5ae313` |
| Tag object | `4fb54fe4f626ea6004c04528779c152734fb6e7a` |
| Push | SUCCESS — origin `refs/tags/v1.5.1` |

**Do not move this tag** for later docs-only closure commits.

## Release workflow

| Field | Value |
|---|---|
| Workflow | `Release` (`.github/workflows/release.yml`) |
| Run ID | `31900093937` |
| URL | https://github.com/magpern/ai-multilingual/actions/runs/31900093937 |
| Built from | tag `v1.5.1` / commit `6298df08b3b1456e4875ecdb860b71506d5ae313` |
| Result | **SUCCESS** |
| Artifact name | `ai-multilingual-1.5.1.zip` |

## Published GitHub Release asset (source of truth)

Independently downloaded from the GitHub Release (not the local release-prep ZIP).

| Field | Value |
|---|---|
| Filename | `ai-multilingual-1.5.1.zip` |
| Source | GitHub Release `v1.5.1` asset |
| Byte size | **761275** |
| SHA-256 (independent) | `6e88a679ddadec0ec371e28ab2209b008ba13a9511ac4832a5de82bd56d739c7` |
| Archive entries | **476** |
| Plugin header Version | **1.5.1** |
| `AIML_VERSION` | **1.5.1** |
| `Migrator::TARGET` | **8** |
| Package audit (`bin/audit-zip.sh`) | **PASS** |
| Forbidden paths (tests/docs/dev) | None |

### Difference vs release-preparation artifact

| | Release-prep (local) | Published (GitHub) |
|---|---|---|
| Size | 761244 | **761275** |
| SHA-256 | `f068a1f90ed692413b10c8ac836f3ae4922f14224f5e13a315d00828bafa9e91` | `6e88a679ddadec0ec371e28ab2209b008ba13a9511ac4832a5de82bd56d739c7` |
| Entries | 476 | 476 |

**Investigation:** Same entry set. Plugin source content matches. Sole file-byte difference: `vendor/composer/installed.php` — CI `composer install` on the annotated tag records root package `pretty_version`/`version`/`reference` as `v1.5.1` / `1.5.1.0` / `6298df08b…`; local prep had `1.0.0+no-version-set` / null reference. Remaining entries differ only in ZIP timestamps. **Not a failure** — published asset audits PASS and is identity-consistent with tag source.

## Acceptance criteria

| Criterion | Status |
|---|---|
| V151AC21 (release-prep ZIP / merge) | **PASS** |
| V151AC22 (published ZIP + independent SHA-256 + DEV acceptance) | **PASS** — published artifact verified + DEV runtime re-acceptance of corrective code ([V1_5_1_DEV_RUNTIME_REACCEPTANCE.md](../validation/V1_5_1_DEV_RUNTIME_REACCEPTANCE.md)); no redundant ZIP install (DEV bind-mounts repo) |

## Deployment / programs

| Item | Status |
|---|---|
| DEV `dev.biopentra.eu` deployment | **NOT PERFORMED** (not required; repo bind-mount) |
| DEV runtime re-acceptance | **PASS** |
| PRODUCTION `biopentra.eu` | **UNTOUCHED** |
| Program B | **NOT STARTED** |
| Corrective lifecycle | **COMPLETE** |

## Release notes

Reviewed notes: [v1.5.1.md](v1.5.1.md) (corrective patch claims only).  
GitHub Release also carries workflow-generated compare notes for `v1.5.0...v1.5.1`.

## Tag vs closure

The tag **`v1.5.1` remains on `6298df08b3b1456e4875ecdb860b71506d5ae313`** and is not moved for this closure documentation commit.

## Exact next step

**FRESH POST-v1.5.1 ROADMAP PRIORITIZATION** (do not auto-start Program B).
