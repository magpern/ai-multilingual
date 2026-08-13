# Changelog

All notable changes to AI Multilingual are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### TSC.4 Gutenberg Coverage Expansion

- Fix `BlockTranslationLookup` to load all six grammar-valid block fields (`content`, `citation`, `summary`, `caption`, `fileName`, `downloadButtonText`) for frontend rendering.
- Add fail-closed `BlockStructuralAttributeGuard` after adapter apply to reject translated fragments that mutate `href`, `class`, `id`, `target`, `rel`, or `data-*` attributes.
- Characterization tests for gallery/media-text/cover/buttons recursion; malformed block/field pair authority; sync_source per-segment stale granularity; PluginGuard TSC.4 invariants.
- Bounded local browser smoke documented in `acceptance/tsc4-browser/README.md` (non-CI).
- Version **1.3.0** unchanged; `Migrator::TARGET` **7** unchanged; no schema migration.

## [1.3.0] — 2026-08-12

### Operator Translation Lifecycle

- OTL.0–OTL.6 Complete: Operations list/attention, unified detail edit/review, publication + stale/retranslate workflow, Jobs integration, bounded bulk operations, and final lifecycle polish.
- Shared ConfirmDialog and centralized async dirty-leave admission; session-only Operations context restore; Review→Operations and bulk→Jobs navigation.
- Bounded bulk publish / unpublish / enqueue_retranslate (max 50) via OperationsBulkCoordinator → TI.7 / TI.6.
- Authoritative local Playwright suite `acceptance/otl-browser/`; historical otl1–otl5 archives retained.

### Compatibility / infrastructure

- Schema TARGET remains **7** (no migration).
- Integration API v1 unchanged; TIQ authorities (Store, review, QA, assessment, Jobs, PublicationService) unchanged.
- Safe publication defaults unchanged: gate OFF, mode `manual`.

### Notes

- Production package is `ai-multilingual-1.3.0.zip` from `bin/build-zip.sh` / GitHub Actions on `v*` tags.
- TSC is not part of this release.
- See [docs/releases/v1.3.0.md](docs/releases/v1.3.0.md) and [docs/releases/V1_3_0_RELEASE_SCOPE.md](docs/releases/V1_3_0_RELEASE_SCOPE.md).

## [1.2.0] — 2026-08-11

### Translation quality and safety

- TQ.0 Translation Quality Baseline: C1.0 corpus, H1.0 scorer, B1.0 reviews, official immutable `baseline-v1.1.0` evidence pack, quality CLI/CI (network-free).
- TI.1 persist-path structural safety on sync and Background Jobs.
- TI.4 shared deterministic QA detectors and policy adapters; additive H1.1 / C1.3 evidence.

### Translation intelligence

- TI.2 bounded translation context on the generation path.
- TI.3 exact approved Translation Memory direct reuse and relevance-gated assisted examples.
- TI.5 explainable read-only risk/readiness assessment (**R1.0**) — no aggregate score, no LLM confidence, no publication decision.

### Background operations

- TI.6 truthful provider usage/budgets, Retry-After handling, bounded concurrency, and recovery/operator evidence improvements.
- Exactly-once provider spend is not claimed (Outcome B may repeat a provider call after crash-after-Store).

### Controlled publication

- TI.7 segment publication axis (`publish_status` / `published_at` / `published_by`); Migrator **TARGET 7**.
- Frontend publication gate (default **off**); modes `manual` (default), `approved_only`, `controlled_auto`.
- Single PublicationPolicy **P1.0** and PublicationService; Workspace / REST / CLI controls.
- Sync and Jobs publish via the same service; publication failure is separate from translation failure.
- Upgrade backfills previously overlayable rows to `published`; new rows default `unpublished`; no silent auto-publication on upgrade.

### Compatibility / infrastructure

- Compatible CI/Actions maintenance landed after v1.1.0 (including Node 24 runtime upgrades).
- Integration API v1 unchanged; A.SEO / Woo ownership unchanged.

### Notes

- Production package is `ai-multilingual-1.2.0.zip` from `bin/build-zip.sh` / GitHub Actions on `v*` tags.
- Official quality evidence pack remains labeled **baseline-v1.1.0** (historical behavioral baseline).
- See [docs/releases/v1.2.0.md](docs/releases/v1.2.0.md) and [docs/releases/V1_2_0_RELEASE_SCOPE.md](docs/releases/V1_2_0_RELEASE_SCOPE.md).

## [1.1.0] — 2026-08-09

### Added

- First intentional public release package after the restored green CI/release baseline.
- WooCommerce visitor coverage (A.7a–A.7d): product/catalog overlays, archive chrome (orderby labels), customer journey chrome (checkout / My Account), and customer email subject/heading overlays with ADR-0018 transactional language context.
- WordPress visitor chrome (A.6): translated custom nav menu item titles (Supported N1).
- Fluent Forms contact bridge (A.8): Integration API v1 consumer for contact form chrome.
- A.SEO family (A.SEOa–A.SEOf):
  - A.SEOa — permalink/preview honesty for Supported SA7/SA10 (translated leaf slugs remain Deferred).
  - A.SEOb — canonical, hreflang, and SB11 `LanguageRelationshipService`.
  - A.SEOc — Rank Math title/description overlays (Partially Supported SC7–SC9).
  - A.SEOd — Open Graph / Twitter text overlays via official Rank Math hooks.
  - A.SEOe — Rank Math sitemap xhtml:link discovery overlays with `blog_public` honesty.
  - A.SEOf — bounded SEO diagnostics (CLI `wp aiml seo status` + admin), not a site-wide crawler.
- Release engineering: full-repo PHPCS green (warnings fail), Action Scheduler integration harness fix, runtime-only ZIP packaging, and `bin/audit-zip.sh` in CI/Release.

### Notes

- Schema target remains **6** — upgrading from a historical 1.0.0 package does not require a schema bump.
- Production package is `ai-multilingual-1.1.0.zip` from `bin/build-zip.sh` / GitHub Actions on `v*` tags.
- See [docs/releases/v1.1.0.md](docs/releases/v1.1.0.md) and [docs/releases/V1_1_0_RELEASE_SCOPE.md](docs/releases/V1_1_0_RELEASE_SCOPE.md) for scope admissions and known limitations.

## [1.0.0] — 2026-08-06

### Added

- Scoped platform v1.0.0: Gutenberg leaf translation, Translator Workspace, Translation Memory, Glossary MVP, Review Workflow, Background Translation Jobs, Limited Rollout, and General Availability controls.
- Database schema target **6** (Store, TM, glossary, review columns, background jobs).
- OpenAI provider via Chat Completions (`/v1/chat/completions`) with encrypted API key storage.
- REST, WP-CLI, diagnostics, and Action Scheduler job execution.

### Fixed

- OpenAI Chat Completions: omit `temperature` for `gpt-5*` and `o`-series models that reject custom values; retain `0.2` for other models.

### Notes

- Production package is the Release ZIP built by `bin/build-zip.sh` / GitHub Actions on `v*` tags.
- Explicit product limits (not blockers): no Elementor body translation; no nested container block identity; WooCommerce surfaces incomplete; render cache default-off; seven documented Gutenberg leaf adapters.
- Historical package/tag metadata only — not treated as an intentional public release for SemVer sequencing of 1.1.0.

## [0.1.0] — prior

Initial development builds through Strategy F (F1–F14) and the post-v1 platform track (Glossary, Review, Background Jobs).
