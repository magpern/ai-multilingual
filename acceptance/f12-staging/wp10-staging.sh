#!/usr/bin/env bash
set -euo pipefail

ROOT="/opt/biopentra/dev/ai-multilingual"
WP="/opt/biopentra/apps/wordpress"
EVIDENCE="${ROOT}/docs/plans/F12_WP10_STAGING_EVIDENCE.json"
HELPER="wp-content/plugins/universal-multilingual/acceptance/f12-staging/wp10-helper.php"
BASE_URL="https://dev.biopentra.eu"
SLUG="f12-staging-rollout-test"
SOURCE_TEXT="F12 Source Hello"
TRANS_TEXT="F12 Hej Staging"

wp() { docker compose -f "${WP}/compose.yml" run --rm wpcli wp "$@"; }
helper() { wp --user=1 eval-file "${HELPER}" "$@"; }
fetch() { curl -sL "${BASE_URL}/sv/${1}/" -H 'Cache-Control: no-cache' 2>/dev/null || true; }

cd "${WP}"
wp eval 'AIMultilingual\Rollout\RolloutCapabilities::grant_default_roles();' >/dev/null

CONFIG_BEFORE=$(wp eval 'echo wp_json_encode(get_option("aiml_rollout_config"));')
POST_ID=$(helper create_post)
OTHER_ID=$(helper create_other)

helper apply '{"rollout_render_enabled":false,"rollout_stage":0,"allowed_post_ids":[],"allowed_language_codes":[],"render_cache_enabled":false}' >/dev/null
HTML0=$(fetch "${SLUG}")
STAGE0=$([[ "${HTML0}" == *"${SOURCE_TEXT}"* && "${HTML0}" != *"${TRANS_TEXT}"* ]] && echo PASS || echo FAIL)

helper apply "{\"rollout_render_enabled\":true,\"rollout_stage\":1,\"allowed_post_ids\":[${POST_ID}],\"allowed_language_codes\":[\"sv\"],\"render_cache_enabled\":false}" >/dev/null
HTML1=$(fetch "${SLUG}")
STAGE1=$([[ "${HTML1}" == *"${SOURCE_TEXT}"* && "${HTML1}" != *"${TRANS_TEXT}"* ]] && echo PASS || echo FAIL)

helper apply "{\"rollout_render_enabled\":true,\"rollout_stage\":2,\"allowed_post_ids\":[${POST_ID}],\"allowed_language_codes\":[\"sv\"],\"render_cache_enabled\":false}" >/dev/null
HTML2=$(fetch "${SLUG}")
STAGE2_ALLOW=$([[ "${HTML2}" == *"${TRANS_TEXT}"* ]] && echo PASS || echo FAIL)
HTML_OTHER=$(fetch "f12-staging-other")
STAGE2_DENY=$([[ "${HTML_OTHER}" == *"${SOURCE_TEXT}"* && "${HTML_OTHER}" != *"${TRANS_TEXT}"* ]] && echo PASS || echo FAIL)

helper set_frontend_render 0 >/dev/null
HTML_KG=$(fetch "${SLUG}")
KILL_GLOBAL=$([[ "${HTML_KG}" != *"${TRANS_TEXT}"* ]] && echo PASS || echo FAIL)
helper set_frontend_render 1 >/dev/null

helper apply '{"rollout_render_enabled":false,"rollout_stage":2,"allowed_post_ids":['"${POST_ID}"'],"allowed_language_codes":["sv"],"render_cache_enabled":false}' >/dev/null
HTML_KR=$(fetch "${SLUG}")
KILL_ROLLOUT=$([[ "${HTML_KR}" != *"${TRANS_TEXT}"* ]] && echo PASS || echo FAIL)

helper apply "{\"rollout_render_enabled\":true,\"rollout_stage\":2,\"allowed_post_ids\":[${POST_ID}],\"allowed_language_codes\":[\"sv\"],\"render_cache_enabled\":true}" >/dev/null
helper emergency_stop >/dev/null
HTML_KE=$(fetch "${SLUG}")
KILL_EMERGENCY=$([[ "${HTML_KE}" != *"${TRANS_TEXT}"* ]] && echo PASS || echo FAIL)

POLICY_BEFORE=$(helper policy_version)
helper restore_snapshot >/dev/null || true
POLICY_AFTER=$(helper policy_version)
RESTORE=$([[ "${POLICY_AFTER}" -gt "${POLICY_BEFORE}" ]] && echo PASS || echo FAIL)

helper apply '{"rollout_render_enabled":false,"rollout_stage":0,"allowed_post_ids":[],"allowed_language_codes":[],"render_cache_enabled":false}' >/dev/null
helper delete_post "${POST_ID}" >/dev/null
helper delete_post "${OTHER_ID}" >/dev/null

FALSE_POSITIVE=0
[[ "${STAGE2_DENY}" == PASS && "${HTML_OTHER}" == *"${TRANS_TEXT}"* ]] && FALSE_POSITIVE=1
[[ "${STAGE0}" == PASS && "${HTML0}" == *"${TRANS_TEXT}"* ]] && FALSE_POSITIVE=$((FALSE_POSITIVE + 1))
[[ "${STAGE1}" == PASS && "${HTML1}" == *"${TRANS_TEXT}"* ]] && FALSE_POSITIVE=$((FALSE_POSITIVE + 1))

cat > "${EVIDENCE}" <<EOF
{
  "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "environment": "dev.biopentra.eu",
  "branch": "$(git -C "${ROOT}" branch --show-current)",
  "head": "$(git -C "${ROOT}" rev-parse HEAD)",
  "wordpress": "$(wp core version 2>/dev/null | tr -d '\r')",
  "php": "$(wp eval 'echo PHP_VERSION;' 2>/dev/null | tr -d '\r')",
  "schema_version": $(wp eval 'echo (int)get_option("aiml_db_version");' 2>/dev/null | tr -d '\r'),
  "staging_only": {"post_id": ${POST_ID}, "other_post_id": ${OTHER_ID}, "target_language": "sv", "slug": "${SLUG}"},
  "results": {
    "stage_0": "${STAGE0}",
    "stage_1_shadow": "${STAGE1}",
    "stage_2_allow": "${STAGE2_ALLOW}",
    "stage_2_deny_other_post": "${STAGE2_DENY}",
    "kill_switch_global_render": "${KILL_GLOBAL}",
    "kill_switch_rollout_disabled": "${KILL_ROLLOUT}",
    "kill_switch_emergency": "${KILL_EMERGENCY}",
    "config_restore_policy_increment": "${RESTORE}",
    "rendered_false_positives": ${FALSE_POSITIVE}
  }
}
EOF

cat "${EVIDENCE}"
OVERALL=PASS
for r in "${STAGE0}" "${STAGE1}" "${STAGE2_ALLOW}" "${STAGE2_DENY}" "${KILL_GLOBAL}" "${KILL_ROLLOUT}" "${KILL_EMERGENCY}" "${RESTORE}"; do
  [[ "${r}" != PASS ]] && OVERALL=FAIL
done
[[ "${FALSE_POSITIVE}" != "0" ]] && OVERALL=FAIL
echo "WP10 overall: ${OVERALL}"
exit $([[ "${OVERALL}" == PASS ]] && echo 0 || echo 1)
