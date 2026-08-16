# AI Multilingual v1.6.0 — Release Scope Audit

**Status:** RELEASED (tag `v1.6.0` → `417df7a5b…`; [closure](V1_6_0_RELEASE_CLOSURE.md))  
**Date:** 2026-08-16  
**Preparation branch:** `release/v1.6.0-preparation` (merged via PR #50)  
**Baseline main HEAD:** `bdf28f96cc6b74ee655a30286f722b5bd0678822`  
**Release-ready commit:** `417df7a5b8df3121aedd5fff0b03ae79cc728290`  
**Previous intentional release:** `v1.5.1` @ `6298df08b3b1456e4875ecdb860b71506d5ae313`  
**Schema:** Migrator `TARGET = 8` (**unchanged** — no migration)  
**Decision:** **RELEASE VERSION DECISION: 1.6.0** (minor — operator-facing capability train)

## A. Included milestones (entire assessed train)

| Milestone | Contribution |
|---|---|
| **P0** Localized URL Operator Completion | Workspace/term LU operator surfaces; Settings honesty; thin term slug REST |
| **P1** G4 Rank Math Model A Characterization | Docs + DEV probe; NO SUPPORTED-CONTRACT DEFECT; EXPECTED OMIT under `blog_public=0` |
| **P2** Jobs / Stale Operator Literacy | Multi-post create without segment keys; stale/conflict literacy; gated recovery |

Do **not** cherry-pick P0/P1/P2 apart.

## B. Must NOT claim as shipped

| Item | Disposition |
|---|---|
| New Localized URL routing capability | Not this release (P0 exposes Supported lifecycle) |
| Sitemap architecture redesign / competing sitemap | Not this release |
| Universal “xhtml always omitted” | Invalid — DEV omit was `blog_public=0` EXPECTED OMIT |
| New Job type / Job engine / concurrency redesign | Not this release |
| Silent conflict overwrite | Forbidden / unchanged fail-safe |
| Schema TARGET 9 / step_9 | Not this release |
| Public Extension/Integration API expansion | Unchanged |
| Tag / GitHub Release / deploy | **Separate authorization** |

## C. Schema / API / upgrade

| Item | Status |
|---|---|
| `Migrator::TARGET` | **8** |
| New migration in v1.6.0 | **None** |
| Public Extension API | Unchanged |
| Public Integration API | Unchanged |
| P0 REST | Admin/workspace application seam |
| P2 Job create | Existing domain authority (`job_type_resolves_missing`) |
| Activation / uninstall | Unchanged |
| Settings / routes / history | Preserved |

## D. Known limitations (non-blocking)

- Post-ID oriented bulk Job create UX  
- Jobs→Ops source-level linking only  
- Richer multi-language UX backlog  
- Sitemap xhtml subject to discoverability gates  

## E. Package

| Item | Value |
|---|---|
| Artifact name | `ai-multilingual-1.6.0.zip` |
| Build | `bin/build-zip.sh` |
| Audit | `bin/audit-zip.sh` |
| Must include | `assets/term-slug-admin/term-slug-admin.js` (P0 runtime) |

## F. Tag / release boundary

```
release preparation (this task)
≠
tag / GitHub Release / deployment
```

Future `v1.6.0` tag must target the **release-ready code commit** after prep merge (separate auth).
