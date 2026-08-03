# Roadmap

One approved milestone at a time. Each is accepted before the next begins.

| M | Name | Delivers | Schema |
|---|---|---|---|
| 0 | Discovery and plan | Architecture, scaffold, CI, docs, ADRs | — |
| **1** | **Skeleton and language foundation** | **Activation, migrations, three-state language model, `/sv/` routing, switcher, manual translation of one classic page, stale detection, inert deactivation** | **languages, translations** |
| 2 | Object-aware manual translation | Block-level segmentation; translated slugs with permanent reservation and redirects; term, menu and SEO-meta segments; REST API; side-by-side segment editor; assembled-field cache | slugs |
| 3 | AI, memory and jobs | `AIProviderInterface` with OpenAI first; prompt profiles; response validator; ten-stage resumable pipeline with bounded checkpoints; Action Scheduler; translation memory; glossary; usage and cost tracking; revision-hash migration | jobs, glossary, tm, ai_usage |
| 4 | WooCommerce completeness | Variations, attributes, archives, cart and checkout routing, order language, email language, Store API context, language cookie | — |
| 5 | SEO | Canonical, hreflang, `x-default`, Open Graph, Rank Math adapter, sitemap alternates, redirect history, noindex for preview languages | — |
| 6 | Visual editor and residual strings | Front-end editing, gettext capture, menu and theme strings, Elementor segmentation | strings |
| 7 | Hardening and release candidate | Compatibility matrix, security review, performance profiling, migration and rollback, uninstall with removal, import/export, packaging | — |

## Milestone 1 scope boundary

Explicitly **not** in Milestone 1: AI translation, translation memory, glossary,
background jobs, REST API, cookies, default-language URL prefixing, translated
slugs, Gutenberg block bodies, Elementor bodies, WooCommerce product
translation, advanced SEO, bulk operations.

The schema and interfaces do not block any of them.

## Known limitations of Milestone 1

- Body translation covers classic content only. Block and Elementor bodies are
  refused by the extractor rather than risked; their titles and excerpts still
  translate.
- Slugs are not translated, so a Swedish page lives at `/sv/<source-slug>/`.
- Canonical URLs and hreflang are not yet emitted; the canonical redirect is
  suppressed for prefixed requests so nothing loops, but correct canonical
  output is Milestone 5.
- On a site running an SEO plugin that emits its own title tag — Rank Math
  does — the document `<title>` is not translated, because
  `document_title_parts` never runs. Headings and body text translate normally.
  The adapter that fixes this is Milestone 5.
- Editing content while the plugin is deactivated will not mark translations
  stale, because the hook is not registered. Re-checking hashes on activation is
  a Milestone 2 addition.
- One translation write invalidates the whole language's cache. Correct, and
  deliberately simple at this content scale.

## Strategy F production track

Block-identity production work (F1–F13) runs in parallel with roadmap
milestones. As of 2026-08-03:

| Milestone | Status |
|---|---|
| F1–F9 | Engineering complete on `main` |
| **F10** Translator Workspace MVP | **Complete** — merged to `main`; see [F10 validation log](plans/F10_TRANSLATOR_VALIDATION_LOG.md) |
| **F11** Translation Memory & AI Assistance | **Complete** on feature branch — [canonical plan](plans/STRATEGY_F_F11_TRANSLATION_MEMORY_AND_AI_ASSISTANCE.md); [validation log](plans/F11_TRANSLATOR_VALIDATION_LOG.md) PASS; tag `strategy-f-f11-tm-ai-complete`; [merge readiness](plans/F11_MERGE_READINESS_REPORT.md) — merge to `main` pending |
| **F12** Limited rollout | **Next** — operational only: cohort/feature flags, rollout strategy, telemetry, monitoring, performance, caching, production confidence, operational diagnostics. **No** new translator features. Plan not started. |
| **F13** General rollout + ADR acceptance | Planned (unchanged) |
