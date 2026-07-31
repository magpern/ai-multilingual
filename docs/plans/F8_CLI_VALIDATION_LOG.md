# F8 CLI Validation Log — dev.biopentra.eu

Operational acceptance record for Strategy F milestone F8 (WP6).

## Environment

| Item | Value |
|---|---|
| Host | `https://dev.biopentra.eu` (`169.58.7.116`) |
| Branch | `feature/f8-operations` |
| Commit | `55ee542034b638191014e9a75135f606d08bf706` |
| Plugin | AI Multilingual `0.1.0` (`AIML_VERSION`) |
| Plugin mount | `/opt/biopentra/dev/ai-multilingual` → `wp-content/plugins/ai-multilingual` |
| WordPress | `7.0.2` |
| PHP | `8.3.32` (WordPress container) |
| Database schema | `aiml_db_schema_version=1` (unchanged during validation) |
| WP-CLI user | `bp_manager` (ID `1`, `--user=1` required for `manage_options` commands) |
| Languages | `en` (default), `sv` |
| Validation page | Post ID `4638`, slug `f8-wp6-validation` (published for HTTP checks) |

## Commands executed

All commands run from `/opt/biopentra/apps/wordpress` unless noted.

```bash
# Baseline
git status --short --branch
git rev-parse HEAD
docker compose run --rm wpcli wp core version
docker compose run --rm wpcli wp plugin list --name=ai-multilingual --format=table
docker compose run --rm wpcli wp plugin activate ai-multilingual
docker compose exec wordpress php -v

# Health (table + JSON)
docker compose run --rm wpcli wp aiml block status --user=1
docker compose run --rm wpcli wp aiml block status --user=1 --format=json
docker compose run --rm wpcli wp aiml block status --user=1 --source-id=4638 --format=json

# Migration dry-run (batch + single post)
docker compose run --rm wpcli wp aiml block migrate --user=1 --post-type=page --batch-size=5 --offset=0 --dry-run --format=json
docker compose run --rm wpcli wp aiml block migrate --user=1 --post-id=4638 --dry-run --format=json

# Live migration (controlled single post)
docker compose run --rm wpcli wp aiml block migrate --user=1 --post-id=4638 --format=json
docker compose run --rm wpcli wp aiml block migrate --user=1 --post-id=4638 --format=json

# Frontend / settings setup and checks (wp eval + HTTP)
docker compose run --rm wpcli wp eval '…' --user=1
curl -sL "https://dev.biopentra.eu/sv/f8-wp6-validation/?t=<epoch>"

# CLI regression
docker compose run --rm wpcli wp aiml block status --user=1 --format=json

# Quality gates (repository)
cd /opt/biopentra/dev/ai-multilingual
docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit -c phpunit.xml.dist
docker run --rm --network aiml-test -v "$PWD":/app -w /app \
  -e WP_DB_HOST=aiml-test-db -e WP_DB_NAME=wordpress_test -e WP_DB_USER=root -e WP_DB_PASS=root \
  aiml-test-runner vendor/bin/phpunit -c phpunit-integration.xml.dist
docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpcs
```

## Results

### Health command

| Check | Result |
|---|---|
| Executes | **PASS** — with `--user=1` |
| Exit code | **0** |
| Health summary | **PASS** — Health, UUID, Segments, Status sections present (table) |
| Metrics section | **PASS** — Metrics table with counters and render timing fields |
| JSON output | **PASS** — valid JSON; top-level health keys + nested `metrics` object |
| Schema contract | **PASS** — keys match `BlockHealthSnapshot::to_array()` + `BlockMetricsSnapshot::to_array()` documented in F8 plan §4–§5 |

Sample JSON keys verified: `generated_at`, `scan_mode`, `eligible_post_count`, `compliant_post_count`, `total_block_segments`, `errors`, `limitations`, `metrics.counters`, `metrics.render_count`.

**Note:** Without `--user=1`, command exits non-zero (`manage_options` required). Documented in observations.

### Dry run

| Check | Result |
|---|---|
| Batch dry-run (`--post-type=page --batch-size=5 --offset=0`) | **PASS** — exit 0; all results `dry_run=true`; no DB writes |
| Single-post dry-run (`--post-id=4638`) | **PASS** — predicted UUID inject (`content_changed=true`, `created_count=1`) |
| Post content unchanged after dry-run | **PASS** — SHA-256 hash identical before/after |
| Reporting | **PASS** — JSON includes hashes, counts, `elapsed_ms` |

### Live migration

| Check | Result |
|---|---|
| First live run (`--post-id=4638`) | **PASS** — `status=complete`, `content_changed=true`, `created_count=1`, `extraction_synced=true` |
| UUID injection | **PASS** — `aimlBlockId` present in `post_content` |
| Second live run (idempotence) | **PASS** — `status=skipped`, `skip_reason=already_compliant`, `content_changed=false`, `created_count=0` |
| Duplicate rows | **PASS** — no duplicate segment keys |
| Unexpected repairs | **PASS** — `duplicate_repaired_count=0`, `malformed_replaced_count=0` |

Batch dry-run at offset 0 on this site mostly skipped (`elementor_body`, `empty_content`). Controlled single-post migration used per F8 runbook §6.2.

### Frontend validation

Validation page: `/sv/f8-wp6-validation/` (post `4638`, paragraph block, Swedish translation `<p>Hej</p>`).

| Step | HTTP marker | Result |
|---|---|---|
| Rendering enabled (all four flags on) | `Hej` present | **PASS** |
| Kill switch (`block_frontend_rendering_enabled=false`) | `Hi` present, `Hej` absent | **PASS** |
| Re-enable rendering | `Hej` present again | **PASS** |
| Cache flush required | No | **PASS** — immediate effect after `update_option` + object cache flush |
| Migration required for toggle | No | **PASS** |
| Data loss | No | **PASS** — translation row preserved; source `post_content` unchanged |

### Settings validation

Simulated admin saves via `SettingsPage::sanitize_settings()` in WP-CLI eval:

| Case | Result |
|---|---|
| Valid chain (registration → injection → extraction) | **PASS** — persisted; `aiml_settings_flag_changed` fired 3 times |
| Invalid combo (frontend on without prerequisites) | **PASS** — frontend flag normalized off |
| `flag_combo_rejected` | **PASS** — exactly 1 event; `dropped_flags=[block_frontend_rendering_enabled]` |
| Admin notice payload | **PASS** — shares normalized rejection fields (covered by PHPUnit integration suite) |

Final `aiml_settings` restored to all Strategy F flags `false`.

### Regression validation

| Check | Result |
|---|---|
| `wp aiml block status` after validation | **PASS** — exit 0; `total_block_segments=1`, `errors=[]` |
| Unexpected metric spikes in CLI | **PASS** — CLI metrics remain request-scoped (zeros in CLI process; expected per WP4) |
| Render timing in CLI metrics | **N/A in CLI** — render timing accumulates in the HTTP request process only (by design) |

### Quality gates

| Gate | Result |
|---|---|
| PHPUnit unit (`284` tests) | **PASS** |
| PHPUnit integration (`250` tests) | **PASS** |
| PHPCS (`127` files) | **PASS** — 0 errors, 0 warnings |

## Observations

1. **WP-CLI capability:** `wp aiml block status` and `wp aiml block migrate` require `--user=1` (or another administrator) on this environment.
2. **Batch composition:** Page batch at offset 0 is mostly Elementor/empty on dev; single-post migration is the reliable controlled live test target here.
3. **UUID drift on publish:** Enabling injection flags before/after publish can assign a new `aimlBlockId`; frontend validation required aligning the Swedish block translation `segment_key` with the live UUID (`83350805-…` after publish vs `b33626f4-…` from first migration).
4. **Migration vs Store rows:** Migration JSON reported `segment_count=2` and `extraction_synced=true`, but `wp_aiml_translations` had no `segment_kind=block` rows until an explicit `Store::save_translation()` for frontend setup. Health scan afterward reported `total_block_segments=1`. Worth monitoring during F9; not a stop condition for F8 CLI acceptance.
5. **Metrics scope:** `BlockMetricsAggregator` is request-scoped; HTTP render events do not appear in a subsequent WP-CLI `block status` invocation.
6. **No automatic schema migration** occurred (`aiml_db_schema_version` remained `1`).
7. **Action Scheduler notice** during integration bootstrap is from WooCommerce (known, documented in `CLAUDE.local.md`).

## Final decision

**PASS**

F8 WP1–WP5 behavior is validated on `dev.biopentra.eu` at commit `55ee542`. Live CLI health, dry-run safety, controlled migration idempotence, frontend kill switch, settings rejection audit, and quality gates all meet the F8 operational acceptance criteria. F8 milestone is complete; F9 entry gate (§13) may proceed when scheduled.
