=== AI Multilingual ===
Contributors: magpern
Tags: multilingual, translation, woocommerce, gutenberg, ai
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multilingual layer for WordPress: canonical content with segment translations applied as render-time overlays.

== Description ==

AI Multilingual stores one canonical object per content item and applies language overlays at render time. Version 1.3.0 completes the Operator Translation Lifecycle (OTL.0–OTL.6) on the Translation Intelligence & Quality (TQ.0–TI.7) foundation: Operations list and attention, unified detail edit/review, publication and stale/retranslate workflow, Jobs integration, bounded bulk operations, and final lifecycle polish.

== Installation ==

1. Upload the `ai-multilingual` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Confirm database schema version 7 (option `aiml_db_version`).
4. Configure languages, providers, and rollout in the AI Multilingual admin screens.
5. Publication gate and auto-publication mode default off/manual — enable only after reviewing release notes.

== Changelog ==

= 1.3.0 =
* Operator Translation Lifecycle (OTL.0–OTL.6): Operations list/attention, unified detail edit/review, publication + stale/retranslate, Jobs integration, bounded bulk operations, lifecycle polish.
* Schema target remains 7 (no migration). Safe publication defaults unchanged (gate off; mode manual).
* TSC not included.

= 1.2.0 =
* Translation Intelligence & Quality (TQ.0–TI.7): quality baseline, persist structural safety, bounded context, TM intelligence, deterministic QA, explainable assessment R1.0, Jobs scale/safety, controlled publication P1.0.
* Schema target 7: publication axis with safe upgrade backfill; gate default off; auto-publication mode default manual.
* New translations default unpublished; existing overlayable translations backfilled published so upgrades do not hide content.

= 1.1.0 =
* First intentional public release package: WooCommerce visitor surfaces, WordPress visitor chrome, Fluent Forms contact bridge, and A.SEO (A.SEOa–A.SEOf).
* Canonical/hreflang, Rank Math title/meta overlays, Open Graph/Twitter text overlays, Rank Math sitemap xhtml honesty, SEO diagnostics CLI/admin.
* CI/release baseline recovery with audited production ZIP packaging.
* Schema target remains 6 (no migration required from historical 1.0.0 packages).

= 1.0.0 =
* Scoped platform release: Store, TM, Glossary, Review, Jobs, Rollout/GA, REST/CLI.
* OpenAI gpt-5 / o-series temperature compatibility for Chat Completions.
