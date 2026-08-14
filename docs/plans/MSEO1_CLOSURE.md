# MSEO.1 Localized Slug Candidate & Active Route Lifecycle — Closure

**Status:** **MSEO.1 Localized Slug Candidate & Active Route Lifecycle COMPLETE**  
**Review:** **MSEO.1 IMPLEMENTATION REVIEW: PASS**  
**Next:** **MSEO.2 NOT STARTED** — STOP before MSEO.2.

## Baseline and branch

| Item | Value |
|---|---|
| Starting main HEAD | `c2282f3a12ce6f0882f6718fbf5e253164bc7013` |
| Plan materialization SHA | `13ddb1afb62616000a034ae727bd723040c63a59` |
| Freeze / ROADMAP SHA (authoritative freeze HEAD) | `cc35ed4ae69a4c04e14d6391758b4adc3729d32d` |
| Authoritative plan | [MSEO1_LOCALIZED_SLUG_CANDIDATE_ACTIVE_ROUTE_IMPLEMENTATION_PLAN.md](MSEO1_LOCALIZED_SLUG_CANDIDATE_ACTIVE_ROUTE_IMPLEMENTATION_PLAN.md) |
| Implementation branch | `feature/mseo1-localized-slug-route-lifecycle` |
| Implementation baseline SHA | `ccf38d5d551db8c98b55c4a7e5478a3772a6a469` |
| Feature implementation SHA | `85e34ade562257ae992f11245d69845eed7f9264` |
| Review-fix SHA | `f4ec6da8b8c495b81b250270215e2b0d6425d610` |
| Final reviewed feature HEAD | `f4ec6da8b8c495b81b250270215e2b0d6425d610` |
| Merge SHA | `15f3b4587941cdd9fad1124d9a3f59c972dcd379` |
| Closure SHA | _(this commit)_ |
| Final main HEAD | _(this commit)_ |

## Architecture

| Item | Value |
|---|---|
| ADR-0023 | **Accepted** — sufficient; not replaced |
| STATE | **B** |
| Initial TARGET | **8** |
| Final TARGET | **8** (no migration; no TARGET 9) |
| Version | **1.4.0** (unchanged) |
| Tag / release / deploy | **None** |
| Public localized routing | **OFF** (`localized_urls_state=off`; `is_discoverable` always false) |

## Pull request and CI

| Item | Value |
|---|---|
| PR | https://github.com/magpern/ai-multilingual/pull/35 |
| Feature CI (reviewed HEAD) | **SUCCESS** — run `31835625989` (phpcs, unit, integration, build, quality) |
| Fresh main CI (merge SHA) | **SUCCESS** — run `31835764367` (phpcs, unit, integration, build, quality) |

## Commits (feature branch)

1. `ccf38d5d5` — docs(mseo1): record implementation baseline for feature branch
2. `85e34ade5` — feat(mseo1): localized slug candidate and prepared route lifecycle
3. `f4ec6da8b` — fix(mseo1): wire source_path refresh and close review gaps

## Docs-only freeze (main, before implementation)

1. `13ddb1afb` — docs(mseo1): freeze localized slug lifecycle implementation plan
2. `cc35ed4ae` — docs(mseo1): point ROADMAP at frozen MSEO.1 implementation plan

## Delivered

- `post_name` / FORMAT_SLUG identity + SlugCandidateService (generate/manual/clear)
- RoutePublicationService sole FORMAT_SLUG publication + prepared-route authority
- PublicationService fail-closed standalone FORMAT_SLUG; `publish_under_route_authority`
- Collision-adjusted effective routes; idempotent re-publish; history max 5 + same-object reuse
- REST: generate / edit / clear / publish-route; `route_sync_state` + `collision_adjusted`
- `post_updated` → `refresh_source_path`; trash deactivate; delete purge
- Jobs/provider/TM FORMAT_SLUG exclusion; PluginGuard MSEO.1 boundaries

## M1R1–M1R36 / M1AC1–M1AC36 / WP MSEO1.0–MSEO1.6

All **Supported** requirements and acceptance criteria **PASS** — see [MSEO1_IMPLEMENTATION_EVIDENCE.md](MSEO1_IMPLEMENTATION_EVIDENCE.md). Deferred: M1R32 (terms / hierarchy / Woo category → MSEO.3+).

## Independent review

**Defects found during review-fix loop:**

1. **Material** — `refresh_source_path` not signaled from WP permalink/`post_name` changes → wired `post_updated` + tests
2. **Material** — REST clear action missing → `DELETE .../slug`
3. **Coverage** — added manual 409 collision, foreign history, trash/purge, clear, discoverability, sanitize-drift tests
4. **Guards** — PluginGuard asserts refresh/trash/purge wiring, clear REST, under-route primitive

**Final verdict:** **MSEO.1 IMPLEMENTATION REVIEW: PASS**

## Validation

| Gate | Result |
|---|---|
| PHPCS | PASS (feature + main CI) |
| Unit | PASS (feature + main CI) |
| Integration | PASS (feature + main CI; `Mseo1SlugLifecycleTest` 20+ cases) |
| Quality baseline | PASS |
| Build / ZIP audit | PASS — `ai-multilingual-1.4.0.zip`, TARGET 8 |

## Public-routing-off confirmed

- EffectiveUrlService unwired in Plugin
- No Router/home_url/canonical/hreflang/sitemap/switcher/rewrite activation
- No WP `post_name` mutation for localization
- No provider-generated URL slugs
- No bulk slug ops; no MSEO.2 symbols/activation jobs

## Limitations / debt

- Public localized routing remains MSEO.2
- Term localized routes deferred to MSEO.3
- Optional Workspace UI polish deferred
- No version bump until a future MSEO release milestone

## Exact next step

Begin **MSEO.2** only when explicitly authorized. Do not enable public localized routing until MSEO.2.

**MSEO.2 NOT STARTED**
