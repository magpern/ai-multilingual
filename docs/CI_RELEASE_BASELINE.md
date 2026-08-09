# CI & Release Baseline

**Recovered:** 2026-08-09  
**Status:** **CLOSED** — independently reviewed, merged to `main`, fresh main CI green  
**Branch:** `fix/ci-release-baseline-recovery` (PR #2)  
**Baseline main HEAD at recovery start:** `4ed56a9b60edfe1eb8ae4ad75e1629fc03a722d2` (A.SEOf closure)  
**Merge commit:** `f900a2f1b9522288e04594d8fd3b631b00f44696`  
**Fresh main CI:** run `31335352770` (phpcs / unit / integration / build all green)  
**Schema:** Migrator `TARGET = 6` (unchanged)  
**Version:** remains `1.0.0` — no release tag or GitHub Release created

This document freezes the authoritative green CI/release baseline after the
A.SEO program. It does not introduce product work.

## Root causes recovered

### PHPCS

Full-repository PHPCS had drifted during A.6 / A.SEO milestones. Local
milestone gates often used narrower / touched-file checks, while GitHub ran
`composer phpcs` on the whole tree. Accumulated errors included missing
docblocks, hook comments, foreign-hook prefix complaints, and docblock spacing.

**Fixes:** correct real violations; narrow phpcs:ignore only for unavoidable
foreign WooCommerce hooks; exclude test-only false positives for dynamic /
slash-separated third-party hooks; set `ignore_warnings_on_exit=0` so warnings
fail the gate.

### Integration / Action Scheduler

`WC_Install::install()` (harness `setup_theme`) calls
`as_unschedule_all_actions()` before Action Scheduler sets
`$data_store_initialized` (normally on `init` priority 1). AS 3.1.6+ /
WooCommerce 10.9.x emit `_doing_it_wrong`; PHPUnit promotes the notice to an
error for `@runTestsInSeparateProcesses` classes that re-bootstrap WordPress
(21 failures).

**Fix:** in `tests/integration/bootstrap.php`, initialize the AS store + logger
and mark the data store ready *before* `WC_Install::install()`, while keeping
install on `setup_theme` so Woo tables exist before `WooCommerce::init`
(priority 0).

### Release ZIP

`bin/build-zip.sh` previously copied all of `assets/`, including Translator
Workspace TypeScript sources, Jest tests, and package manifests. Runtime only
loads `assets/translator-workspace/build/*` (plus `block-editor.js` and
glossary-admin assets). Local trees with `node_modules/` could also stage
hundreds of MB into `dist/`.

**Fix:** ship runtime assets only; require the workspace bundle; add
`bin/audit-zip.sh`; run the audit in CI and Release workflows.

## Authoritative local commands

Host has no system PHP; use Docker (see `CLAUDE.local.md`).

```bash
# Dependencies
docker run --rm -v "$PWD":/app -w /app composer:2.8 composer install

# Unit
docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit -c phpunit.xml.dist
# or: composer test:unit

# PHPCS (warnings fail)
docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpcs
# or: composer phpcs

# Integration (WooCommerce 10.9.4, MariaDB 11.4)
docker network create --internal aiml-test
docker run -d --name aiml-test-db --network aiml-test \
  -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=wordpress_test mariadb:11.4
until docker exec aiml-test-db healthcheck.sh --connect --innodb_initialized; do sleep 2; done
docker run --rm -e WC_VERSION=10.9.4 -v "$PWD":/app -w /app aiml-test-runner bash tests/bin/install-wp.sh
docker run --rm --network aiml-test -v "$PWD":/app -w /app \
  -e WP_DB_HOST=aiml-test-db -e WP_DB_NAME=wordpress_test -e WP_DB_USER=root -e WP_DB_PASS=root \
  aiml-test-runner vendor/bin/phpunit -c phpunit-integration.xml.dist
# PluginGuard is part of the integration suite (--filter PluginGuardTest)

# Release package
docker run --rm -v "$PWD":/app -w /app composer:2.8 \
  composer install --no-dev --prefer-dist --no-progress --optimize-autoloader
bash bin/build-zip.sh
bash bin/audit-zip.sh

git diff --check
```

## CI jobs (`.github/workflows/ci.yml`)

| Job | Trigger | PHP | Command |
|---|---|---|---|
| phpcs | push `main`, all PRs | 8.3 | `composer phpcs` |
| unit | push `main`, all PRs | 8.3 | `composer test:unit` |
| integration | push `main`, all PRs | 8.3 + mysqli | `install-wp.sh` then `composer test:integration` |
| build | push `main`, all PRs | 8.1 | `composer install --no-dev` → `bin/build-zip.sh` → `bin/audit-zip.sh` → upload artifact |

**Environment pins**

- WooCommerce: `10.9.4` (`WC_VERSION` env)
- WordPress: pinned to installed `wp-phpunit` by `tests/bin/install-wp.sh`
- MariaDB service: `mariadb:11.4`
- No Composer cache step today (acceptable; install is fast)

## Release workflow (`.github/workflows/release.yml`)

- Trigger: push tag `v*`
- Verifies tag matches plugin header `Version:`
- Builds ZIP with `bin/build-zip.sh <tag-without-v>`
- Audits with `bin/audit-zip.sh`
- Publishes GitHub Release with the ZIP (`softprops/action-gh-release`)

Do **not** tag or publish a release as part of routine CI recovery.

## Version sources (current: 1.0.0)

| Source | Value |
|---|---|
| Plugin header `Version:` | 1.0.0 |
| `AIML_VERSION` | 1.0.0 |
| `readme.txt` Stable tag | 1.0.0 |
| Composer package version field | unset (name only) |
| Git tags | `v1.0.0` present |

All declared version sources agree at 1.0.0. Composer does not carry a
redundant version field.

## Release readiness gates

A future release is **not** release-ready unless all are green:

1. Unit
2. Integration (includes PluginGuard)
3. PHPCS (errors **and** warnings)
4. Build ZIP
5. ZIP audit (`bin/audit-zip.sh`)
6. `git diff --check`

## Diagnosing Action Scheduler bootstrap failures

Symptom: PHPUnit errors mentioning

`as_unschedule_all_actions() was called before the Action Scheduler data store was initialized`

especially on `@runTestsInSeparateProcesses` tests.

Check:

1. `tests/integration/bootstrap.php` still calls the AS store early helper
   before `WC_Install::install()` on `setup_theme`.
2. WooCommerce version still bundles AS ≥ 3.1.6 (doing_it_wrong present).
3. Do **not** “fix” by suppressing notices, skipping tests, or removing AS cleanup.

## Intentional PHPCS policy

- Production foreign hooks (e.g. reading `woocommerce_account_menu_items`) use a
  hook docblock + narrow `phpcs:ignore` with rationale.
- Tests may invoke WordPress / Woo / Rank Math / Fluent Forms hooks by design;
  `phpcs.xml.dist` excludes prefix / slash-hook false positives under `tests/`.
- Warnings fail CI (`ignore_warnings_on_exit=0`).

## Historical note

Last fully green CI before this recovery: run `30825136302`
(2026-08-03, Strategy F F9 merge). Subsequent A.6 / A.SEO / platform merges kept
local milestone validation green while full-repo GitHub PHPCS and the AS
harness notice accumulated as red CI on `main`.

## Architecture / scope

This recovery does **not** change translation architecture, Store schema,
`TARGET`, identity model, Router semantics, SEO behavior, Woo visitor behavior,
Rank Math behavior, or public API contracts. Changes are test harness, coding
standards, packaging, and documentation only.

## Closure (2026-08-09)

- Recovery merged to `main` via `--no-ff` (`merge: restore green CI and release baseline`).
- Fresh post-merge GitHub Actions on `main` fully green (`31335352770`).
- Release ZIP audit enforced in CI and Release workflows; artifact re-audited from the main run.
- No `v*` tag created; no GitHub Release published; plugin version remains `1.0.0`.
- Next step is a separate **release/version decision** from this green baseline — not another product milestone.
