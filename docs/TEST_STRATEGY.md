# Test strategy

Three layers. Every feature ships with tests in the layer that can actually
falsify it.

## Unit — `tests/unit/`, no WordPress

The bootstrap loads the Composer autoloader and nothing else. Anything tested
here is therefore provably free of WordPress, which is the point: the rules that
decide routing and staleness should be checkable without a database.

- `SettingsSanitizeTest` — sanitization is total; any input yields a usable
  array and nothing throws.
- `LanguageValidationTest` — code, locale and state-transition rules.
- `LanguageResolverTest` — the full routing priority matrix, including preview
  visibility and the slug-that-starts-with-a-language-code case.
- `LanguageContextTest` — the switch stack, including restoration when the
  callback throws.
- `NormalizePlainTest`, `NormalizeHtmlTest`, `NormalizeJsonTest`,
  `NormalizeCodeTest` — one class per text format, because the rules genuinely
  differ and a shared test would hide that.
- `HashSemanticsTest` — what each hash means, segment identity, and the
  normalization-version contract.
- `ExtractorFieldsTest`, `PluginTest`.

## Integration — `tests/integration/`, WordPress + WooCommerce + HPOS

WooCommerce is installed for parity with later milestones even though Milestone
1 does not depend on it. Plugin tables are created on `muplugins_loaded`,
before the router first reads them and before WooCommerce's installer fires
`save_post`; DDL must also land before the first test transaction, since it
would implicitly commit.

- `ActivationTest` — tables, schema version, seeded default language, granted
  capability, idempotent re-activation, migration from version 0.
- `LanguagesTest` — CRUD, the single-default invariant, state transitions,
  cache invalidation, and that deleting a language keeps its translations.
- `RoutingTest` — prefixed URLs reach the same canonical post; unprefixed URLs
  are untouched; unknown and disabled prefixes 404; preview languages are
  visible only to translators; locale and `lang` follow; `home_url` is prefixed
  only after routing; no cookie is set.
- `TranslationRenderingTest` — overlays per language, the source row byte-identical
  after a full render cycle, stale detection that leaves translations alone, and
  stale content still rendering.
- `ExtractorBodyGuardTest` — classic bodies translatable; block and Elementor
  bodies refused; titles still translatable on refused pages.
- `SwitcherTest` — links target the current page, preview languages hidden from
  visitors, menu integration opt-in.
- `AdminAuthorizationTest` — capability split, no logged-out handler.
- `LifecycleTest` — no deactivation hook; uninstall with retention on removes
  nothing and a reinstall finds its translations.

## Structural guards — `tests/integration/PluginGuardTest.php`

These read the source and the hook table rather than exercising behaviour,
because the invariants they protect erode one convenient call at a time and a
behavioural test would only catch the consequence.

They assert: no canonical content is created or written; no direct writes to
core tables; no WooCommerce setters; `$wpdb` confined to `src/Database` and the
store classes; no hardcoded table prefix; reads are prepared; no REST routes; no
cookie; no rewrite rules; no coupling to another translation plugin; no broad
exception swallowed; object-cache access only through the wrapper; cache keys
carry the language; uninstall removes nothing before the retention guard; boot
is idempotent; and `the_content` is hooked at priority 1.

## Not covered here, and why

- **Uninstall with removal enabled** would drop the tables mid-suite and take
  the remaining tests with it, since DDL is not transactional. The destructive
  branch is asserted structurally; end-to-end verification belongs to the
  hardening milestone.
- **WP-CLI command invocation** needs the WP-CLI runtime. The rule that matters
  — that the body guard cannot be bypassed from the CLI — lives in `Extractor`
  and is covered by `ExtractorBodyGuardTest`.

## Commands

The host has no PHP, Composer or Node, so everything runs in Docker. Only SWAG
may publish ports, so the test database sits on an internal network.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2.8 composer install
docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpcs
docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit -c phpunit.xml.dist

# One-off runner image with mysqli and unzip.
printf 'FROM php:8.3-cli\nRUN docker-php-ext-install mysqli && apt-get update -q && apt-get install -yq --no-install-recommends unzip\n' \
  | docker build -q -t aiml-test-runner -

docker network create --internal aiml-test
docker run -d --name aiml-test-db --network aiml-test \
  -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=wordpress_test mariadb:11.4

# Provisioning needs egress, so it runs on the default bridge.
docker run --rm -e WC_VERSION=10.9.4 -v "$PWD":/app -w /app aiml-test-runner bash tests/bin/install-wp.sh

docker run --rm --network aiml-test -v "$PWD":/app -w /app \
  -e WP_DB_HOST=aiml-test-db -e WP_DB_NAME=wordpress_test -e WP_DB_USER=root -e WP_DB_PASS=root \
  aiml-test-runner vendor/bin/phpunit -c phpunit-integration.xml.dist

docker rm -f aiml-test-db && docker network rm aiml-test
```

Files created inside containers are root-owned; git reads them fine.

CI runs the same four gates: phpcs, unit, integration against a `mariadb:11.4`
service, and a packaging build.

## Strategy F browser acceptance (F9+)

F9 closed under **engineering acceptance** — formal 35/35 Tier 3 Playwright PASS
was not achieved; see [F9_BROWSER_VALIDATION_LOG.md](plans/F9_BROWSER_VALIDATION_LOG.md).

### Tier policy (ongoing)

| Tier | Scope | When |
|---|---|---|
| **Tier 0 (default)** | PHPUnit unit, PHPUnit integration, PHPCS, WP-CLI | Every merge and F10 work package |
| **Tier 1/2** | Targeted Playwright `--grep` or single spec | Editor, overlay, or cross-browser behavior under change |
| **Tier 3** | Full 35-test matrix (`acceptance/f9-browser/tools/run-f9-acceptance.sh`) | Milestone/release gate only; **explicit operator approval required** |

Full Playwright must **not** run automatically during F10 ordinary work. See
[STRATEGY_F_F9_BROWSER_ACCEPTANCE.md](plans/STRATEGY_F_F9_BROWSER_ACCEPTANCE.md) §25
and [STRATEGY_F_F10_TRANSLATOR_WORKSPACE.md](plans/STRATEGY_F_F10_TRANSLATOR_WORKSPACE.md) §16.
