# Universal Multilingual — working agreement

Multilingual layer for WordPress and WooCommerce. Display name Universal
Multilingual; slug/text domain `universal-multilingual`. Namespace
`AIMultilingual\`, prefix `aiml_`, tables `{$wpdb->prefix}aiml_*` (runtime
identity unchanged).

## Invariants

These are enforced by integration tests — mostly `PluginGuardTest.php`, noted
per item where a different test owns it. If a change requires breaking one,
that is an architecture decision, not an implementation detail — write an ADR
first.

1. **One canonical object.** There is exactly one WordPress object per piece of
   content. Never duplicate posts, products, variations, inventory, stock,
   prices, SKUs, orders, reviews, media, tax or shipping data for a language.
2. **Translations are overlays.** The default-language object is the source of
   truth. `src/` never calls `wp_insert_post`, `wp_update_post`,
   `wp_insert_term` or a WooCommerce setter, and never writes to `wp_posts`,
   `wp_postmeta`, `wp_terms` or `wp_termmeta`.
3. **WooCommerce is overlaid in `view` context only.** `WC_Data::get_prop()`
   applies `woocommerce_{type}_get_{prop}` filters only when the context is
   `view`, which is what keeps admin screens, CRUD saves and every internal
   calculation on canonical values.
4. **Deactivation removes nothing** and leaves the site byte-identical.
5. **Uninstall retention is all-or-nothing.** With `remove_data_on_uninstall`
   off (the default) uninstall is a no-op; with it on, every table, option and
   capability the plugin created is removed. Never partially.
6. **A source change never overwrites a translation.** It flags the segment
   stale. Stale translations keep rendering until reviewed or replaced —
   falling back mid-page would splice two languages together.
7. **No visitor-triggered AI.** Provider calls are reachable only from
   background jobs and authenticated actions.
8. **`$wpdb` is confined** to `src/Database/*` and the store classes, always via
   `prepare()` for DML, always with table names from `$wpdb->prefix`.
9. **Every cache key carries the language.** All object-cache access goes
   through `src/Cache/Cache.php`.
10. **No coupling to another translation plugin.** No `trp_`, `icl_` or
    Polylang references anywhere.
11. **Anonymous language resolution is URL-authoritative** (enforced by
    `tests/integration/RoutingTest.php` alongside PluginGuardTest's no-cookie
    check). `host + request_uri` alone must determine an anonymous visitor's
    rendered language — no cookie, `Accept-Language`, or geo/location signal
    may change it for the same URL. This is a cache contract with the
    deployment's reverse-proxy cache, not just a routing default — see
    ADR-0024 before changing it.

## Code rules

- Generic product. No site, client, host or deployment name appears in any
  committed file. Machine-specific notes go in the gitignored `CLAUDE.local.md`.
- Self-contained repo. Never commit it into a surrounding repository, never
  reference paths outside it.
- Minimums: PHP 8.1, WordPress 6.5. WooCommerce is optional until the
  WooCommerce milestone; integration code guards itself.
- Thin main file; `final class Plugin` is the composition root with an
  idempotent `init()`. Services are `final`, constructor-injected, and register
  their own hooks in `register()`.
- One versioned settings option (`aiml_settings`) owned by `Settings`, whose
  `defaults()` and `sanitize()` are pure, WordPress-free and never throw.
- Custom tables are owned by versioned explicit-SQL migrations in
  `src/Database/`. Never `dbDelta` — its parser silently drops composite prefix
  indexes.
- No secrets in the repo.

## Workflow

```
composer phpcs
composer test:unit
composer test:integration
```

phpcs is a hard gate. Every feature ships with tests.

One approved milestone at a time: do not start the next milestone before the
current one is accepted.

Release: bump the `Version:` header, tag `vX.Y.Z`, push the tag. CI verifies the
tag matches the header and publishes the zip.
