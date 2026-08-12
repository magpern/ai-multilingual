# AI Multilingual v1.3.0 — Release Scope Audit

**Status:** **CLOSED / RELEASED**
**Date:** 2026-08-12
**Preparation branch:** `release/v1.3.0-preparation` (PR #21)
**Baseline main HEAD:** `46851dc607172d32473b3755001a0d1d8327f05e`
**Merge / tag target:** `c88ba30681439d9e7113a20d7ebc03c942dd240d`
**Previous intentional release:** `v1.2.0` @ `b67fc296e2b2170dea84228b1acda502e518f07a`
**Schema:** Migrator `TARGET = 7` (**unchanged** — no migration in this release)
**Decision:** **RELEASE VERSION DECISION: 1.3.0**
**Published:** tag `v1.3.0` + GitHub Release (workflow `31577172928`); published ZIP audit PASS

## Version decision rationale

| Option | Verdict |
|---|---|
| Patch `1.2.x` | **Rejected** — understates OTL.0–OTL.6 (new operator surfaces, REST, bulk orchestration, lifecycle UX) |
| Minor `1.3.0` | **Selected** — additive operator product capability on the TIQ/v1.2.0 baseline; Integration API v1 unchanged; TARGET remains 7; no intentional breaking admin/REST contracts |
| Major `2.0.0` | **Rejected** — no backwards-incompatible public contract or forced destructive upgrade behavior |

**“OTL Complete” / “TIQ Complete” means** the frozen program ladders are implemented and closed on `main`. They do **not** mean every Deferred/Partial/Unsupported surface is shipped, nor that TSC exists, nor perfect linguistic quality.

## A. Product capabilities shipped since v1.2.0

### Operator Translation Lifecycle (OTL.0–OTL.6) — Complete

| Milestone | Shipped |
|---|---|
| **OTL.0** | Foundations: Operations read model, attention taxonomy, Workspace Operations admission |
| **OTL.1** | Operations list + attention filters + URL-synced Ops context |
| **OTL.2** | Unified detail inspector with edit/review; dirty honesty; concurrency on shared save |
| **OTL.3** | Publication + stale/retranslate workflow in Operations (TI.7 authority retained) |
| **OTL.4** | Jobs integration: bounded association, Open-in-Jobs, Ops→Jobs; Jobs→Ops **Partial** |
| **OTL.5** | Bounded bulk publish / unpublish / enqueue_retranslate (≤50); A3/A6 rules |
| **OTL.6** | ConfirmDialog; centralized async dirty-leave; session Ops context; Review→Ops; bulk→Jobs; a11y/responsive polish; authoritative `otl-browser` |

### Already in v1.2.0 (still claimed; not re-shipped as new)

TIQ Complete (TQ.0–TI.7) remains the intelligence/quality/publication foundation. v1.3.0 does not reopen TIQ architecture.

## B. Safety / architecture hardening

- PluginGuard extended for OTL/bulk/session/TS neutrality forbids
- Retranslate hash guards / publication admissions (OTL.3)
- Bulk fail-closed size limits; server revalidation of publication
- Jobs failure privacy / bounded Ops Jobs lookup (OTL.4)
- No second publication or review policy engine in OTL

## C. Admin / operator UX

- Translator Workspace tabs: Operations → Translate → Review → Jobs
- ConfirmDialog for Operations consequential actions
- Session-only Ops nav restore across temporary tab hops
- Humanized publish/bulk outcome labels (honesty retained)
- Laptop column priority; focus-visible / focus restore

## D. Schema / persistence

| Item | Status |
|---|---|
| `Migrator::TARGET` | **7** (unchanged since v1.2.0) |
| New migration in v1.3.0 | **None** |
| Publication columns | Already present from TI.7 / v1.2.0 |
| Selection / dirty drafts / Ops session | **Not** persisted (by design) |

## E. CI / tooling / docs

- OTL planning/implementation/closure docs
- Playwright suites `acceptance/otl{1–6}-browser` / `otl-browser` (live **local/non-CI**)
- Ignore rules for local Playwright `node_modules`/artifacts

## F. Must NOT claim as shipped

| Item | Disposition |
|---|---|
| Jobs→Operations reverse deep-link / Jobs `translation_id` enrichment | **Partial / Deferred** |
| Bulk retry-failed | **Deferred** |
| Jobs-backed attention | **Deferred** |
| Path-B QA/assessment duplication | **Deferred** |
| Live Playwright as CI gate | **Unsupported** (local only) |
| Mobile-first admin | **Deferred** |
| Selection/bulk-result cross-tab persistence | **Unsupported** |
| Durable publish-verification product | **Not shipped** |
| TSC (Translation Surface Coverage) | **Not started** |
| TIQ Deferred QA detectors / RA14 score / exactly-once Jobs / auto-unpublish / scheduled publication | Still Deferred/Partial as in v1.2.0 scope |
| Integration API v2 | **Not shipped** |

## Upgrade implications (v1.2.0 → v1.3.0)

1. Install `ai-multilingual-1.3.0.zip` over previous plugin directory.
2. Activate / visit wp-admin so `maybe_migrate()` runs (no-op at TARGET 7).
3. Confirm `aiml_db_version` remains **7**.
4. Confirm publication defaults unchanged: gate OFF, mode `manual`.
5. No republish/unpublish sweep; existing translation/review/publication rows remain valid.
6. New OTL Workspace surfaces available to operators with existing capabilities.

## Public contracts

| Contract | Status |
|---|---|
| Integration API v1 | Unchanged |
| Schema TARGET | **7** |
| PublicationPolicy P1.0 / PublicationService | Unchanged ownership |
| Assessment R1.0 | Unchanged ownership |
| Quality pack `baseline-v1.1.0` | Immutable historical label |
| Additive Workspace REST (Operations / bulk) | Compatible additive |

## Authoritative version sources for 1.3.0

| Source | Value |
|---|---|
| Plugin header `Version:` | 1.3.0 |
| `AIML_VERSION` | 1.3.0 |
| `readme.txt` Stable tag | 1.3.0 |
| CHANGELOG / release notes | 1.3.0 |
| Package name | `ai-multilingual-1.3.0.zip` |

Historical refs intentionally retained: prior changelog entries, `@since`, baseline-v1.1.0 pack name, v1.2.0 release docs, OTL plan SHAs.

## Deployment

This release preparation does **not** deploy to production. Tag-triggered GitHub Release builds and attaches the audited ZIP; site deployment is a separate operator action.
