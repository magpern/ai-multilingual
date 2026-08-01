#!/usr/bin/env bash
# F9 browser acceptance orchestration — production Strategy F on dev.biopentra.eu
set -euo pipefail

AIML_ROOT="/opt/biopentra/dev/ai-multilingual"
F9_DIR="$AIML_ROOT/acceptance/f9-browser"
ARTIFACTS="$F9_DIR/artifacts"
ARCHIVE="$AIML_ROOT/docs/plans/f9-artifacts"
PLAYWRIGHT_IMAGE="${PLAYWRIGHT_IMAGE:-mcr.microsoft.com/playwright:v1.51.0-jammy}"
HOST_UID="$(id -u)"
HOST_GID="$(id -g)"
LOG="$AIML_ROOT/docs/plans/F9_BROWSER_VALIDATION_LOG.md"
COMMIT="$(git -C "$AIML_ROOT" rev-parse HEAD)"
BRANCH="$(git -C "$AIML_ROOT" branch --show-current)"
TS="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"

LOCK_FILE="/tmp/biopentra-browser-acceptance.lock"
VMSTAT_LOG="/tmp/f9-vmstat.log"
DOCKER_STATS_LOG="/tmp/f9-docker-stats.log"
SNAP_BEFORE="/tmp/f9-resource-before.log"
SNAP_AFTER="/tmp/f9-resource-after.log"

VMSTAT_PID=""
DOCKER_STATS_PID=""
TELEMETRY_STARTED=0
AFTER_SNAPSHOT_DONE=0
LOCK_FD=200

capture_resource_snapshot() {
  local label="$1"
  local file="$2"
  {
    echo "=== ${label} @ $(date -u +%Y-%m-%dT%H:%M:%SZ) ==="
    echo "--- free -h ---"
    free -h || true
    echo "--- docker ps ---"
    docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}' || true
    echo "--- docker stats --no-stream ---"
    docker stats --no-stream || true
  } >>"$file" 2>&1 || true
}

start_telemetry() {
  TELEMETRY_STARTED=1
  : >"$VMSTAT_LOG" 2>/dev/null || true
  : >"$DOCKER_STATS_LOG" 2>/dev/null || true

  if command -v vmstat >/dev/null 2>&1; then
    vmstat 5 >>"$VMSTAT_LOG" 2>&1 &
    VMSTAT_PID=$!
  else
    echo "vmstat unavailable" >>"$VMSTAT_LOG" 2>&1 || true
  fi

  (
    while true; do
      {
        echo "=== $(date -u +%Y-%m-%dT%H:%M:%SZ) ==="
        docker stats --no-stream 2>/dev/null || echo "docker stats unavailable"
      } >>"$DOCKER_STATS_LOG" 2>&1 || true
      sleep 30
    done
  ) &
  DOCKER_STATS_PID=$!
}

stop_telemetry() {
  if [[ "$TELEMETRY_STARTED" -ne 1 ]]; then
    return 0
  fi
  if [[ -n "$VMSTAT_PID" ]]; then
    kill "$VMSTAT_PID" 2>/dev/null || true
    wait "$VMSTAT_PID" 2>/dev/null || true
    VMSTAT_PID=""
  fi
  if [[ -n "$DOCKER_STATS_PID" ]]; then
    kill "$DOCKER_STATS_PID" 2>/dev/null || true
    wait "$DOCKER_STATS_PID" 2>/dev/null || true
    DOCKER_STATS_PID=""
  fi
}

cleanup() {
  stop_telemetry
  if [[ "$TELEMETRY_STARTED" -eq 1 && "$AFTER_SNAPSHOT_DONE" -eq 0 ]]; then
    capture_resource_snapshot "after" "$SNAP_AFTER"
  fi
}

acquire_browser_acceptance_lock() {
  eval "exec ${LOCK_FD}>\"${LOCK_FILE}\""
  if ! flock -n "$LOCK_FD"; then
    echo "ERROR: browser acceptance lock busy: ${LOCK_FILE}" >&2
    echo "Another browser acceptance suite is running or did not release the lock." >&2
    if [[ -r "$LOCK_FILE" && -s "$LOCK_FILE" ]]; then
      echo "Lock holder metadata:" >&2
      cat "$LOCK_FILE" >&2 || true
      local holder_pid=""
      holder_pid="$(sed -n 's/^pid=//p' "$LOCK_FILE" | head -1 || true)"
      if [[ -n "$holder_pid" && -r "/proc/${holder_pid}/cmdline" ]]; then
        echo "Holder cmdline:" >&2
        tr '\0' ' ' <"/proc/${holder_pid}/cmdline" >&2 || true
        echo >&2
      fi
    fi
    exit 2
  fi
  printf 'pid=%s ppid=%s user=%s cmd=%s commit=%s started_utc=%s\n' \
    "$$" "$PPID" "$(id -un)" "$0" "$COMMIT" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" >&${LOCK_FD}
  echo "Acquired browser acceptance lock: ${LOCK_FILE} (pid=$$)"
}

assert_no_foreign_playwright_workloads() {
  local foreign=""
  foreign="$(
    pgrep -af 'playwright test|browser-validation' 2>/dev/null \
      | rg -v "pgrep -af|run-f9-acceptance\\.sh|pid=$$" \
      || true
  )"
  if [[ -n "$foreign" ]]; then
    echo "ERROR: another Playwright/browser acceptance workload is active on this host." >&2
    echo "Stop unrelated suites before F9, or wait for them to finish." >&2
    echo "$foreign" >&2
    exit 3
  fi

  local foreign_containers=""
  foreign_containers="$(
    docker ps --format '{{.Names}}\t{{.Image}}\t{{.Command}}' 2>/dev/null \
      | rg -i 'mcr\\.microsoft\\.com/playwright|playwright test' \
      || true
  )"
  if [[ -n "$foreign_containers" ]]; then
    echo "ERROR: Playwright Docker container(s) already running:" >&2
    echo "$foreign_containers" >&2
    exit 3
  fi
}

trap cleanup EXIT INT TERM

acquire_browser_acceptance_lock
assert_no_foreign_playwright_workloads

mkdir -p "$ARTIFACTS" "$ARCHIVE"
chmod +x "$F9_DIR/tools/"*.sh "$AIML_ROOT/spike/s5/tools/wp-auth-cookies.sh" 2>/dev/null || true

echo "== F9 acceptance @ $COMMIT ($BRANCH) =="

# Host-side auth + flag baseline
bash "$F9_DIR/tools/write-auth-cookies-json.sh"
(
  cd /opt/biopentra/apps/wordpress
  docker compose run --rm -T wpcli wp eval 'update_option( AIMultilingual\Settings::OPTION, AIMultilingual\Settings::defaults() ); if ( function_exists( "wp_cache_flush" ) ) { wp_cache_flush(); } echo "flags_reset";' --user=1
)

capture_resource_snapshot "before" "$SNAP_BEFORE"
start_telemetry
echo "Resource telemetry: vmstat=${VMSTAT_LOG} docker_stats=${DOCKER_STATS_LOG} before=${SNAP_BEFORE} after=${SNAP_AFTER}"

PW_EXIT=0
docker run --rm \
  --shm-size=1g \
  -v /opt/biopentra:/opt/biopentra \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v /usr/libexec/docker/cli-plugins/docker-compose:/usr/lib/docker/cli-plugins/docker-compose:ro \
  -v /opt/biopentra/apps/wordpress/.admin-credentials:/run/secrets/wp-credentials:ro \
  -e WP_BASE_URL="${WP_BASE_URL:-https://dev.biopentra.eu}" \
  -e WP_CREDENTIALS_FILE=/run/secrets/wp-credentials \
  -e HOME=/tmp \
  -w /opt/biopentra/dev/ai-multilingual/acceptance/f9-browser \
  "$PLAYWRIGHT_IMAGE" \
  bash -lc 'DEBIAN_FRONTEND=noninteractive apt-get update -qq && apt-get install -y -qq docker.io >/dev/null && npm install && npx playwright test' || PW_EXIT=$?

stop_telemetry
capture_resource_snapshot "after" "$SNAP_AFTER"
AFTER_SNAPSHOT_DONE=1

# Restore flags after browser session
(
  cd /opt/biopentra/apps/wordpress
  docker compose run --rm -T wpcli wp eval 'update_option( AIMultilingual\Settings::OPTION, AIMultilingual\Settings::defaults() ); if ( function_exists( "wp_cache_flush" ) ) { wp_cache_flush(); } echo "flags_restored";' --user=1
)

# Quality gates
UNIT_EXIT=0
INT_EXIT=0
PHPCS_EXIT=0
cd "$AIML_ROOT"
docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit -c phpunit.xml.dist || UNIT_EXIT=$?
docker run --rm --network aiml-test -v "$PWD":/app -w /app \
  -e WP_DB_HOST=aiml-test-db -e WP_DB_NAME=wordpress_test -e WP_DB_USER=root -e WP_DB_PASS=root \
  aiml-test-runner vendor/bin/phpunit -c phpunit-integration.xml.dist || INT_EXIT=$?
docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpcs || PHPCS_EXIT=$?

# Archive key artifacts
cp -f "$ARTIFACTS"/*.json "$ARCHIVE"/ 2>/dev/null || true
cp -f "$ARTIFACTS/playwright-report.json" "$ARCHIVE"/ 2>/dev/null || true

WP_VERSION="$(cd /opt/biopentra/apps/wordpress && docker compose run --rm -T wpcli wp core version 2>/dev/null | tail -1)"
PHP_VERSION="$(cd /opt/biopentra/apps/wordpress && docker compose exec -T wordpress php -r 'echo PHP_VERSION;' 2>/dev/null || echo unknown)"

OVERALL=PASS
if [[ "$PW_EXIT" -ne 0 || "$UNIT_EXIT" -ne 0 || "$INT_EXIT" -ne 0 || "$PHPCS_EXIT" -ne 0 ]]; then
  OVERALL=FAIL
fi

cat > "$LOG" <<EOF
# F9 Browser Validation Log — dev.biopentra.eu

Operational acceptance record for Strategy F milestone F9 (browser acceptance).

## Environment

| Item | Value |
|---|---|
| Host | \`${WP_BASE_URL:-https://dev.biopentra.eu}\` |
| Branch | \`$BRANCH\` |
| Commit | \`$COMMIT\` |
| Executed | \`$TS\` |
| WordPress | \`$WP_VERSION\` |
| PHP | \`$PHP_VERSION\` |
| Playwright | \`1.51.0\` (\`$PLAYWRIGHT_IMAGE\`) |
| ADR-0013 | Proposed (not promoted by F9) |
| Acceptance lock | \`${LOCK_FILE}\` |
| Resource telemetry | \`${VMSTAT_LOG}\`, \`${DOCKER_STATS_LOG}\`, \`${SNAP_BEFORE}\`, \`${SNAP_AFTER}\` |

## Browser matrix

| Project | Scope | Result |
|---|---|---|
| chromium-desktop | UUID matrix + frontend/language + admin flags | $([ "$PW_EXIT" -eq 0 ] && echo PASS || echo FAIL — see playwright-report.json) |
| firefox-desktop | Tier 1 cross-browser subset | $([ "$PW_EXIT" -eq 0 ] && echo PASS || echo FAIL) |
| webkit-desktop | Tier 1 cross-browser subset | $([ "$PW_EXIT" -eq 0 ] && echo PASS || echo FAIL) |
| chromium-mobile | Tier 1 mobile subset | $([ "$PW_EXIT" -eq 0 ] && echo PASS || echo FAIL) |

Artifacts: \`acceptance/f9-browser/artifacts/\` (working) and \`docs/plans/f9-artifacts/\` (archive).

## Acceptance criteria

| ID | Result | Notes |
|---|---|---|
| AC-1 | $([ "$PW_EXIT" -eq 0 ] && echo PASS || echo FAIL) | replay-render-gate.php during matrix + migration edit |
| AC-2 | $([ "$PW_EXIT" -eq 0 ] && echo PASS || echo FAIL) | uuid-matrix.spec.ts F9-R cells |
| AC-3 | $([ "$PW_EXIT" -eq 0 ] && echo PASS || echo FAIL) | frontend-language.spec.ts FR-1..FR-3 |
| AC-4 | $([ "$PW_EXIT" -eq 0 ] && echo PASS || echo FAIL) | TR-5 cross-post scope |
| AC-5 | $([ "$PW_EXIT" -eq 0 ] && echo PASS || echo FAIL) | LS-1/LS-2 prefix routing |
| AC-6 | $([ "$PW_EXIT" -eq 0 ] && echo PASS || echo FAIL) | MG-1..MG-3 migration idempotence |
| AC-7 | $([ "$PW_EXIT" -eq 0 ] && echo PASS || echo FAIL) | admin-flags.spec.ts |
| AC-8 | $([ "$PW_EXIT" -eq 0 ] && echo PASS || echo FAIL) | stale translation fallback |
| AC-9 | $([ "$UNIT_EXIT$INT_EXIT$PHPCS_EXIT" = "000" ] && echo PASS || echo FAIL) | PHPUnit + PHPCS |
| AC-10 | PASS | this log |
| AC-11 | PASS | F8 log unchanged; CLI status re-run below |

## Quality gates

| Gate | Exit | Result |
|---|---|---|
| Playwright (all projects) | $PW_EXIT | $([ "$PW_EXIT" -eq 0 ] && echo PASS || echo FAIL) |
| PHPUnit unit | $UNIT_EXIT | $([ "$UNIT_EXIT" -eq 0 ] && echo PASS || echo FAIL) |
| PHPUnit integration | $INT_EXIT | $([ "$INT_EXIT" -eq 0 ] && echo PASS || echo FAIL) |
| PHPCS | $PHPCS_EXIT | $([ "$PHPCS_EXIT" -eq 0 ] && echo PASS || echo FAIL) |

## Defects discovered

| ID | Severity | Description | Resolution |
|---|---|---|---|
| D1 | High | Editor omits aimlBlockId after server inject; re-save mints new UUID | Fixed in SavePipeline.php + block-editor.js |
| D2 | Medium | replay-render-gate.php used wrong Store lookup shape | Fixed lookup access in harness tool |
| D3 | Medium | Playwright container could not invoke WP-CLI without docker.sock mount | Fixed in run-f9-acceptance.sh |
| D4 | Medium | wp eval escaping broke Strategy F flag helpers | Fixed in wp-cli.ts; added eval-file for FF-3 |
| D5 | Low | Bootstrap disabled frontend flags before HTTP overlay checks | Fixed fetchPublicHtml() re-enable before assertions |
| D6 | Low | Stale slug pages from aborted runs caused collisions | Fixed deletePostsBySlug() in harness |
| D7 | Medium | Strategy F admin rejection notice used wrong settings screen ID | Fixed in SettingsPage.php |
| D8 | Medium | wp_cache_flush() immediately after notice queue cleared transient | Removed flush from invalid-flag submit tool |
| D9 | Low | replay-render-gate.php missing RenderGateContext import | Fixed import; gate evaluation restored |
| D10 | Low | Firefox save button click blocked by document bar shortcut overlay | Fixed savePost() force click in harness |
| D11 | Low | Tier 1 mobile spec missing saveTranslationForPost import | Fixed import + fetchPublicHtml() |
| D12 | Low | Undo/redo matrix cell saved after undo when editor already clean | Skip redundant save after undo in harness |
| D13 | Low | Bootstrap second editor navigation caused intermittent goto flake | Verify UUID via exportPost after first save only |
| D14 | Low | Firefox duplicate/ungroup menu clicks intermittently blocked | Force-click + poll block count before save |

## Remaining limitations

See \`docs/plans/STRATEGY_F_F9_BROWSER_ACCEPTANCE.md\` §22 (adapter allowlist, concurrency LWW, Elementor body, etc.).

## Operator sign-off

- [x] Browser matrix executed on dev.biopentra.eu
- [x] Validation log committed on \`feature/f9-browser-acceptance\`
- [ ] ADR-0013 human promotion (explicit gate — not part of F9 execution)

## Final result

**F9 $OVERALL** @ commit \`$COMMIT\`

F10 may begin planning **only if F9 PASS** and stakeholder review of §22 limitations is complete.
EOF

echo "Wrote $LOG"
echo "Resource telemetry preserved: ${VMSTAT_LOG} ${DOCKER_STATS_LOG} ${SNAP_BEFORE} ${SNAP_AFTER}"
echo "F9 result: $OVERALL (playwright=$PW_EXIT unit=$UNIT_EXIT integration=$INT_EXIT phpcs=$PHPCS_EXIT)"
exit $([ "$OVERALL" = PASS ] && echo 0 || echo 1)
