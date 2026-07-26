# Deployment record

One section per milestone: what changed, what it added to the database, how to
deploy it and how to back it out.

---

## Milestone 1 — Skeleton and language foundation

Version 0.1.0.

### Files created

```
ai-multilingual.php          Loader
uninstall.php                No-op unless retention is switched off
src/Plugin.php               Composition root, activation, stale detection
src/Settings.php
src/Database/Schema.php
src/Database/Migrator.php
src/Language/Languages.php
src/Language/LanguageResolver.php
src/Language/LanguageContext.php
src/Routing/Router.php
src/Translation/Store.php
src/Translation/Extractor.php
src/Translation/Renderer.php
src/Frontend/Switcher.php
src/Cache/Cache.php
src/Admin/SettingsPage.php
src/Admin/Editor.php
src/Cli.php
```

### Database

Schema version 1 creates two tables:

- `{prefix}aiml_languages` — language configuration, one `status` column
  (`disabled` / `preview` / `published`), exactly one default.
- `{prefix}aiml_translations` — the segment store.

Options added: `aiml_settings`, `aiml_db_version`, `aiml_cache_version`, and
`aiml_lang_version_{id}` per language.

Capability added: `aiml_translate`, granted to `administrator` and `editor`.

Migrations run on activation and from an `admin_init` drift check, because
bind-mount deployments never fire the activation hook.

### New hooks

See `docs/HOOKS.md`. In summary: `plugins_loaded` (999), `locale`,
`language_attributes`, `redirect_canonical`, `parse_request`, `home_url`,
`the_title`, `the_content` (1), `get_the_excerpt`, `document_title_parts`,
`save_post` (20), `admin_init`, `wp_nav_menu_items`, the `aiml_switcher`
shortcode, three `admin_post_*` handlers, and `before_woocommerce_init`.

### Deploying

1. Back up the database first — **to a path outside the web root**. `wp db
   export` writes wherever it is told, and the WordPress directory is served:
   a dump left there is a publicly downloadable copy of the entire database,
   password hashes and customer data included. Export to a directory the web
   server does not serve, and set the file mode to 600.
2. Mount the plugin. On a Docker install the bind mount must be added to **both**
   the web and the WP-CLI service, then the stack recreated.
3. Validate before starting: check the compose configuration parses, bring the
   stack up, confirm containers are healthy, confirm the public listener set is
   unchanged, and confirm the site still answers.
4. Deactivate any other multilingual plugin and flush caches and rewrites. Leave
   it installed so it can be reactivated.
5. Activate `ai-multilingual` and confirm both tables exist and
   `aiml_db_version` is set.
6. Add the target language, then work through the acceptance checks below.

### Acceptance

1. The plugin activates cleanly.
2. Both tables exist and the schema version is recorded.
3. The default language stays unprefixed.
4. A target language can be added in `preview` or `published`.
5. `/sv/` and `/sv/<source-slug>/` reach the same canonical objects as the
   English routes.
6. No language cookie is set on any front-end response.
7. The switcher moves between the default and target URL for the same page.
8. One classic page carries a manual translated title and body.
9. Both languages render from the same post ID.
10. Editing the English title or body marks the translation stale.
11. The stale translation continues to render.
12. The canonical title and content are unchanged by translation activity.
13. Alternating languages shows no cache bleed.
14. Deactivation makes the plugin inert and leaves all data intact.
15. Reactivation restores the translations with no further action.
16. The previous multilingual plugin can be reactivated to roll back.

### Rolling back

Deactivate `ai-multilingual`, reactivate the previous plugin, flush caches. No
data is removed by deactivation, so reactivating restores translations exactly.

### Acceptance result — 2026-07-26

Run against WordPress 7.0.2, WooCommerce 10.9.4, Blocksy child theme, Rank Math
and Elementor Pro active, Redis object cache on.

All sixteen checks passed, with two observations worth recording:

- **The document `<title>` does not translate yet.** On a site running Rank
  Math, the title tag is emitted by Rank Math rather than through
  `wp_get_document_title()`, so the `document_title_parts` overlay never runs.
  The `h1` and body translate correctly. Translating the title tag needs the
  Rank Math adapter, which is Milestone 5. Without Rank Math the existing
  overlay would apply.
- **A default-language prefix redirects rather than 404s.** `/en/<slug>/`
  returns 301 to `/<slug>/` because the prefix is not recognized and core's
  canonical redirect then resolves the slug. That is better behaviour than a
  404 and needs no change. The same applies to a `preview` language for an
  anonymous visitor: they are redirected to the unprefixed page rather than
  shown unfinished content.

An acceptance page (`aiml-acceptance-page`) was created for the walkthrough and
left published so the slice can be viewed. It can be deleted at any time.

### Known limitations

- Body translation covers classic content only. Block and Elementor bodies are
  refused with an explanation; their titles and excerpts still translate.
- Slugs are not translated; a target-language page lives at `/sv/<source-slug>/`.
- Canonical URLs and hreflang are not emitted yet. The canonical redirect is
  suppressed for prefixed requests so nothing loops.
- Content edited while the plugin is deactivated will not be marked stale,
  because the hook is not registered. Re-checking on activation is a Milestone 2
  addition.
- One translation write invalidates the whole language's cache.
