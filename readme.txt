=== AI Multilingual ===
Contributors: magpern
Tags: multilingual, translation, woocommerce, gutenberg, ai
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multilingual layer for WordPress: canonical content with segment translations applied as render-time overlays.

== Description ==

AI Multilingual stores one canonical object per content item and applies language overlays at render time. Platform v1.0.0 includes Translator Workspace, Translation Memory, Glossary, Review Workflow, Background Translation Jobs, rollout/GA controls, and OpenAI Chat Completions integration for Gutenberg leaf blocks.

== Installation ==

1. Upload the `ai-multilingual` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Confirm database schema version 6 (option `aiml_db_version`).
4. Configure languages, providers, and rollout in the AI Multilingual admin screens.

== Changelog ==

= 1.0.0 =
* Scoped platform release: Store, TM, Glossary, Review, Jobs, Rollout/GA, REST/CLI.
* OpenAI gpt-5 / o-series temperature compatibility for Chat Completions.
