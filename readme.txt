=== AI Multilingual ===
Contributors: magpern
Tags: multilingual, translation, woocommerce, gutenberg, ai
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multilingual layer for WordPress: canonical content with segment translations applied as render-time overlays.

== Description ==

AI Multilingual stores one canonical object per content item and applies language overlays at render time. Version 1.1.0 expands visitor-facing WordPress and WooCommerce coverage and completes the A.SEO family (canonical/hreflang, Rank Math overlays, Open Graph/Twitter text, sitemap discovery honesty, and bounded SEO diagnostics), on top of the platform core shipped in the historical 1.0.0 package (Workspace, TM, Glossary, Review, Jobs, rollout/GA, OpenAI).

== Installation ==

1. Upload the `ai-multilingual` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Confirm database schema version 6 (option `aiml_db_version`).
4. Configure languages, providers, and rollout in the AI Multilingual admin screens.

== Changelog ==

= 1.1.0 =
* First intentional public release package: WooCommerce visitor surfaces, WordPress visitor chrome, Fluent Forms contact bridge, and A.SEO (A.SEOa–A.SEOf).
* Canonical/hreflang, Rank Math title/meta overlays, Open Graph/Twitter text overlays, Rank Math sitemap xhtml honesty, SEO diagnostics CLI/admin.
* CI/release baseline recovery with audited production ZIP packaging.
* Schema target remains 6 (no migration required from historical 1.0.0 packages).

= 1.0.0 =
* Scoped platform release: Store, TM, Glossary, Review, Jobs, Rollout/GA, REST/CLI.
* OpenAI gpt-5 / o-series temperature compatibility for Chat Completions.
