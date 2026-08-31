# Universal Multilingual v1.11.0 — DEV / Pre-Production Release

**Status:** **AUTHORIZED — DEV / PRE-PRODUCTION ONLY**  
**Date:** 2026-08-31  
**Version:** **1.11.0** · **TARGET:** **8** · **Migration:** **NONE**  
**Authorization:** Operator request — release for dev; pre-production next  
**Production:** **FORBIDDEN** by this release  

## Scope

| Included | Source |
|---|---|
| Site Translate full path (PR #60) | `4d581a21f` merge |
| DeepSeek + per-provider AI settings (1.10.0 train) | PR #59 — never tagged as `v1.10.0` |
| User manual v1.11.0 | Site Translate operator section |
| Workspace bundle | `assets/translator-workspace/build/` |

## Explicitly not authorized

- Production deploy to `biopentra.eu`
- Release-readiness sign-off (full operator walkthrough still pending)
- Schema TARGET 9 / new migration

## Acceptance state

| Layer | Status |
|---|---|
| Implementation (Site Translate) | CLOSED — PASS |
| Automated + bounded DEV verification | PASS |
| Full operator-led Swedish workflow | **PENDING** |
| This release | **DEV / pre-prod package only** |

## Tag / artifact

| Item | Value |
|---|---|
| Tag | `v1.11.0` |
| Artifact | `universal-multilingual-1.11.0.zip` |
| Build | `bin/build-zip.sh` via GitHub Actions on tag push |
| DEV install | Bind mount `/opt/biopentra/dev/universal-multilingual` **or** release ZIP |

## Pre-production notes

- DEV (`dev.biopentra.eu`) serves as the pre-production environment per Biopentra VPS layout.
- After prod-clone import, bind-mounted plugins are restored from git; tag `v1.11.0` is the canonical package reference for non-bind-mount installs.
- Complete the pending operator walkthrough ([`SITE_TRANSLATE_DEV_OPERATOR_ACCEPTANCE_PENDING.md`](../validation/SITE_TRANSLATE_DEV_OPERATOR_ACCEPTANCE_PENDING.md)) before production release-readiness.

## Verdict

**v1.11.0 DEV / PRE-PRODUCTION RELEASE: AUTHORIZED**  
**PRODUCTION: UNTOUCHED**
