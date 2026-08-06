# Changelog

All notable changes to AI Multilingual are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

## [0.1.0] — prior

Initial development builds through Strategy F (F1–F14) and the post-v1 platform track (Glossary, Review, Background Jobs).
