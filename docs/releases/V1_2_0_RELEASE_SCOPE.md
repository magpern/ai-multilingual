# AI Multilingual v1.2.0 — Release Scope Audit

**Status:** **PREPARATION** (not tagged / not published)
**Date:** 2026-08-11
**Preparation branch:** `release/v1.2.0-preparation`
**Baseline main HEAD:** `d4ac047ad2e3f21a87b45a5d661d95c8078fa68b`
**Previous intentional release:** `v1.1.0` @ `d9c2336182fa2e0ae0582ead78cc0a346670c92a`
**Schema:** Migrator `TARGET = 7` (6 → 7 publication axis; ADR-0020)
**Decision:** TARGET RELEASE VERSION = **1.2.0** (post-TIQ product baseline)

Tag / GitHub Release are **out of scope** for this preparation branch. After independent review + merge to `main`, the annotated tag `v1.2.0` must land on the **release-preparation merge commit**, not this branch tip.

## Intent

v1.2.0 ships the complete **Translation Intelligence & Quality (TIQ)** program (TQ.0–TI.7) on top of the v1.1.0 visitor/SEO platform baseline, plus compatible CI/release tooling maintenance that landed on `main` after `v1.1.0`.

**“TIQ Complete” means:** the foundational ladder (measurement → structural safety → bounded context → TM intelligence → deterministic QA → explainable risk assessment → Jobs hardening → controlled publication) is implemented and closed on `main`. It does **not** mean every Deferred surface is shipped, nor perfect linguistic quality, nor professional certification, nor semantic guarantees from deterministic QA.

## Shipped (Supported) — included in 1.2.0

### Translation quality and safety

| Milestone | Shipped |
|---|---|
| **TQ.0** | Measurement infrastructure: C1.0 corpus, H1.0 scorer, B1.0 reviews, official immutable pack `tests/quality/baselines/baseline-v1.1.0/`, quality CLI/CI (network-free). Historical behavioral quality baseline remains labeled **baseline-v1.1.0** (not relabeled to v1.2.0). |
| **TI.1** | Persist-path structural safety on sync + Jobs; ResponseValidator blockers on persist; TS7 numbers non-blocking on persist. |
| **TI.4** | Shared deterministic QA detectors → RawFinding → policy adapters; H1.1 + C1.3 additive evidence. |

### Translation intelligence

| Milestone | Shipped |
|---|---|
| **TI.2** | Bounded `TranslationContext` on generation path (field/domain/glossary/examples budget). |
| **TI.3** | Exact approved TM direct reuse + relevance-gated assisted examples; structural-fail AI fallthrough once. |
| **TI.5** | Explainable read-only assessment contract **R1.0** (no aggregate score, no LLM confidence, no persisted assessment, no publication decision). |

### Background operations

| Milestone | Shipped |
|---|---|
| **TI.6** | Truthful provider usage/budgets, Retry-After honor, bounded concurrency, recovery/operator evidence; publication failure remains separate from translation failure (TI.7). Exactly-once provider spend is **not** claimed (Outcome B). |

### Controlled publication

| Milestone | Shipped |
|---|---|
| **TI.7** | Segment `publish_status` / `published_at` / `published_by`; TARGET **7**; central frontend gate `Store::is_publicly_overlay_eligible()`; one `PublicationPolicy` **P1.0**; one `PublicationService`; modes `manual` \| `approved_only` \| `controlled_auto`; Workspace / REST / CLI / diagnostics / bounded audit; sync+Jobs via TranslationService. |

### Compatibility / infrastructure (post-v1.1.0)

- GitHub Actions Node 24 runtime upgrades and related CI maintenance on `main`.
- No Integration API v2; no TARGET beyond 7; no prompt/H1/R1/P1 contract renames.

## Still Deferred / Partial / Unsupported (verified)

Do **not** claim these as shipped in 1.2.0:

### TIQ-specific

| Item | Status | Evidence |
|---|---|---|
| TI.3 TM21 Store `translations.tm_id` provenance persistence | **Dormant / narrowed** — column exists; Store never reads/writes; diagnostics carry TM outcome codes | TI.3 validation log |
| Vector / fuzzy automatic TM reuse | **Deferred / Unsupported** as auto authority | TI.3 / TIQ parent |
| TI.4 Deferred QA surfaces (units, wrong-language, SKU heuristic, never_translate, SEO length, etc.) | **Deferred / Partial** per QD matrix | TI.4 validation log |
| TI.5 RA14 aggregate score | **Deferred** | TI.5 validation log |
| TI.5 RA8–RA10 / RA18 Partial surfaces | **Partial** (in-request TM outcome; soft facets; CLI get) | TI.5 validation log |
| TI.6 JO23 cleanup/retention policy redesign | **Deferred** (existing 30/90 retained) | TI.6 plan |
| TI.6 JO24 multi-job fairness | **Deferred** | TI.6 plan |
| Non-TM intra-job identical coalescing | **Deferred** | TI.6 plan |
| TI.6 exactly-once provider claim | **Not claimed** (Outcome B — crash-after-Store may repeat provider spend) | TI.6 validation log |
| Site-wide daily spend platform | **Not shipped** | TI.6 / platform |
| TI.7 AP12 provenance policy | **Partial** | TI.7 plan |
| TI.7 AP13–AP15 field/content-type/language-pair matrices | **Deferred** | TI.7 plan |
| TI.7 AP26 automatic unpublish | **Partial** (manual Supported; automatic Deferred) | TI.7 plan |
| TI.7 AP27 bulk publication | **Deferred** | TI.7 plan |
| TI.7 AP28 scheduled publication | **Deferred** | TI.7 plan |
| TI.7 AP6/AP7 needs_review / review_recommended auto-publish | **Unsupported** | TI.7 plan |
| TI.7 AP20 force-publish hard blockers | **Unsupported** | TI.7 plan |
| TI.7 AP29 confidence score / AP30 LLM publication judge | **Unsupported** | TI.7 plan |

### Pre-existing platform (still relevant; unchanged by TIQ)

| Limitation | Notes |
|---|---|
| Translated leaf slugs / rewrite bases | A.SEOa Deferred |
| Social image/card surfaces SD4/SD9/SD10/SD12 | A.SEOd Deferred |
| Sitemap media / SitemapDiscovery SE10/SE11 | A.SEOe Deferred |
| `blog_public=0` suppresses public SEO discovery enrichment | Honesty gate |
| `/sv/` front-page 301 self-loop | Pre-existing router finding; not fixed in TIQ |
| Duplicate product `<title>` (Rank Math / theme) | Pre-existing; AIML does not emit an extra title |
| Elementor body translation; nested container identity | Platform carry-forward |
| A.6 / A.7 Deferred chrome/email/body surfaces | Per A.6/A.7 validation logs |
| WP-CLI host “restful” quirk on some images | Workaround: admin SEO UI / service eval |

## Upgrade / schema (critical)

- `Migrator::TARGET` **7**
- Step 7 adds `publish_status`, `published_at`, `published_by` (+ index)
- Backfill: non-empty `translated_text` and `status NOT IN ('ignored','missing')` and currently `unpublished` → `published` (preserves pre-TI.7 overlay visibility)
- New writes default `publish_status = unpublished`
- Settings defaults: `segment_publication_gate_enabled = false`, `auto_publication_mode = manual`
- **Upgrade does not enable automatic publication**
- Gate OFF preserves legacy overlay semantics; gate ON requires `publish_status = published`
- `review_status` is not overloaded as publication state (`approved ≠ published`)

## Public contracts (compatible)

| Contract | Status |
|---|---|
| Integration API v1 | Unchanged |
| Store / PluginIdentity / `source_hash` | Unchanged identity model |
| Router / LanguageContext / SB11 | Unchanged |
| Assessment **R1.0** | Read-only; no publication decision |
| Publication policy **P1.0** | New; deterministic |
| A.SEO / Woo ownership | Unchanged (overlays only) |
| Quality pack **baseline-v1.1.0** | Immutable historical evidence |

## Authoritative version sources for 1.2.0

| Source | Value |
|---|---|
| Plugin header `Version:` | 1.2.0 |
| `AIML_VERSION` | 1.2.0 |
| `readme.txt` Stable tag | 1.2.0 |
| Artifact name | `ai-multilingual-1.2.0.zip` |
| Composer package version field | unset (name only) |

## Remaining intentional `1.1.0` references

| Location | Class |
|---|---|
| `CHANGELOG.md` `[1.1.0]` / `docs/releases/v1.1.0.md` / `V1_1_0_RELEASE_SCOPE.md` | **historical — leave** |
| Official pack path `baseline-v1.1.0/` | **historical quality baseline label — leave** |
| `@since 1.1.0` PHPDoc | **historical — leave** |
| Keep a Changelog URL `keepachangelog.com/en/1.1.0/` | **spec URL — leave** |
| Plugin header / `AIML_VERSION` / Stable tag | **current — must be 1.2.0** |

## Generation-path honesty

Relative to v1.1.0, meaningful **generation** changes are TI.2 bounded context and TI.3 TM reuse/examples. TI.1/TI.4/TI.5/TI.6/TI.7 primarily constrain, assess, operate, or publish — they do **not** rewrite OpenAI prompt intelligence as “better translations.”
