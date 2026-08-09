# Changelog

All notable changes to AI Multilingual are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
