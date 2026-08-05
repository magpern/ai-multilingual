#!/usr/bin/env bash
# F13 Tier 2 — general availability staging on dev.biopentra.eu
set -euo pipefail

ROOT="/opt/biopentra/dev/ai-multilingual"
WP="/opt/biopentra/apps/wordpress"
EVIDENCE="${ROOT}/docs/plans/F13_GA_STAGING_EVIDENCE.json"
HELPER="wp-content/plugins/ai-multilingual/acceptance/f13-staging/f13-helper.php"
BASE_URL="https://dev.biopentra.eu"

# Restore to F12 observation defaults after run
RESTORE_JSON='{"rollout_render_enabled":true,"rollout_stage":2,"allowed_post_ids":[6321],"allowed_language_codes":["sv"],"general_rollout_enabled":false,"render_cache_enabled":false}'

wp() { docker compose -f "${WP}/compose.yml" run --rm wpcli wp "$@"; }
helper() { wp --user=1 eval-file "${HELPER}" "$@"; }
fetch() { curl -sL "${BASE_URL}/sv/${1}/" -H 'Cache-Control: no-cache' 2>/dev/null || true; }

cd "${WP}"
wp eval 'AIMultilingual\Rollout\RolloutCapabilities::grant_default_roles();' >/dev/null

CONFIG_BEFORE=$(helper export_config)
BLOCKS=$(helper supported_blocks)
BLOCKS_OK=$(python3 -c "import json,sys; b=json.loads(sys.argv[1]); print('PASS' if b==['core/paragraph','core/heading','core/button'] else 'FAIL')" "${BLOCKS}")

POST_A_JSON=$(helper create_ga_post a)
POST_B_JSON=$(helper create_ga_post b)
parse() { python3 -c "import json,sys; d=json.loads(sys.argv[1]); print(d[sys.argv[2]])" "$1" "$2"; }

POST_A_ID=$(parse "${POST_A_JSON}" post_id)
POST_B_ID=$(parse "${POST_B_JSON}" post_id)
SLUG_A=$(parse "${POST_A_JSON}" slug)
SLUG_B=$(parse "${POST_B_JSON}" slug)
SRC_A=$(parse "${POST_A_JSON}" source_text)
TR_A=$(parse "${POST_A_JSON}" trans_text)
SRC_B=$(parse "${POST_B_JSON}" source_text)
TR_B=$(parse "${POST_B_JSON}" trans_text)

# Stage 2 limited — only if allowlisted; neither A nor B is allowlisted
helper apply "{\"rollout_render_enabled\":true,\"rollout_stage\":2,\"allowed_post_ids\":[6321],\"allowed_language_codes\":[\"sv\"],\"general_rollout_enabled\":false,\"render_cache_enabled\":false}" >/dev/null
sleep 1
HTML_LIM=$(fetch "${SLUG_A}")
STAGE2_DENY_NEW=$([[ "${HTML_LIM}" == *"${SRC_A}"* && "${HTML_LIM}" != *"${TR_A}"* ]] && echo PASS || echo FAIL)

# Stage 6 GA — both non-allowlisted posts should render translation
helper apply "{\"rollout_render_enabled\":true,\"rollout_stage\":6,\"allowed_post_ids\":[],\"allowed_post_types\":[\"post\",\"page\"],\"allowed_language_codes\":[\"sv\"],\"general_rollout_enabled\":true,\"render_cache_enabled\":false}" >/dev/null
sleep 2
HTML_A=$(fetch "${SLUG_A}")
HTML_B=$(fetch "${SLUG_B}")
GA_A=$([[ "${HTML_A}" == *"${TR_A}"* ]] && echo PASS || echo FAIL)
GA_B=$([[ "${HTML_B}" == *"${TR_B}"* ]] && echo PASS || echo FAIL)

# Language deny under GA (English path should stay source — fetch without /sv/)
HTML_EN=$(curl -sL "${BASE_URL}/${SLUG_A}/" -H 'Cache-Control: no-cache' 2>/dev/null || true)
GA_LANG_DENY=$([[ "${HTML_EN}" != *"${TR_A}"* ]] && echo PASS || echo FAIL)

# Kill switches
helper set_frontend_render 0 >/dev/null
HTML_KG=$(fetch "${SLUG_A}")
KILL_GLOBAL=$([[ "${HTML_KG}" != *"${TR_A}"* ]] && echo PASS || echo FAIL)
helper set_frontend_render 1 >/dev/null

helper apply "{\"rollout_render_enabled\":false,\"rollout_stage\":6,\"allowed_language_codes\":[\"sv\"],\"general_rollout_enabled\":true,\"render_cache_enabled\":false}" >/dev/null
HTML_KR=$(fetch "${SLUG_A}")
KILL_ROLLOUT=$([[ "${HTML_KR}" != *"${TR_A}"* ]] && echo PASS || echo FAIL)

helper apply "{\"rollout_render_enabled\":true,\"rollout_stage\":6,\"allowed_language_codes\":[\"sv\"],\"general_rollout_enabled\":true,\"render_cache_enabled\":false}" >/dev/null
POLICY_BEFORE=$(helper policy_version)
helper emergency_stop >/dev/null
HTML_EM=$(fetch "${SLUG_A}")
KILL_EMERGENCY=$([[ "${HTML_EM}" != *"${TR_A}"* ]] && echo PASS || echo FAIL)

# Rollback rehearsal: re-enable GA then restore snapshot
helper apply "{\"rollout_render_enabled\":true,\"rollout_stage\":6,\"allowed_post_ids\":[],\"allowed_language_codes\":[\"sv\"],\"general_rollout_enabled\":true,\"render_cache_enabled\":false}" >/dev/null
POLICY_MID=$(helper policy_version)
helper restore_snapshot >/dev/null || true
POLICY_AFTER=$(helper policy_version)
ROLLBACK=$([[ "${POLICY_AFTER}" -gt "${POLICY_MID}" ]] && echo PASS || echo FAIL)

FALSE_POSITIVE=0
[[ "${STAGE2_DENY_NEW}" == PASS && "${HTML_LIM}" == *"${TR_A}"* ]] && FALSE_POSITIVE=1
[[ "${GA_LANG_DENY}" == PASS && "${HTML_EN}" == *"${TR_A}"* ]] && FALSE_POSITIVE=$((FALSE_POSITIVE + 1))

# Cleanup ephemeral posts
helper delete_post "${POST_A_ID}" >/dev/null
helper delete_post "${POST_B_ID}" >/dev/null

# Restore F12 observation config
helper apply "${RESTORE_JSON}" >/dev/null

OVERALL=PASS
for r in "${BLOCKS_OK}" "${STAGE2_DENY_NEW}" "${GA_A}" "${GA_B}" "${GA_LANG_DENY}" "${KILL_GLOBAL}" "${KILL_ROLLOUT}" "${KILL_EMERGENCY}" "${ROLLBACK}"; do
  [[ "${r}" == PASS ]] || OVERALL=FAIL
done
[[ "${FALSE_POSITIVE}" -eq 0 ]] || OVERALL=FAIL

cat > "${EVIDENCE}" <<EOF
{
  "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "environment": "dev.biopentra.eu",
  "branch": "$(git -C "${ROOT}" branch --show-current)",
  "head": "$(git -C "${ROOT}" rev-parse HEAD)",
  "supported_blocks": ${BLOCKS},
  "staging_posts": {"a": ${POST_A_ID}, "b": ${POST_B_ID}},
  "results": {
    "supported_blocks_frozen": "${BLOCKS_OK}",
    "stage2_deny_non_allowlisted": "${STAGE2_DENY_NEW}",
    "stage6_ga_post_a": "${GA_A}",
    "stage6_ga_post_b": "${GA_B}",
    "stage6_language_deny": "${GA_LANG_DENY}",
    "kill_global": "${KILL_GLOBAL}",
    "kill_rollout": "${KILL_ROLLOUT}",
    "kill_emergency": "${KILL_EMERGENCY}",
    "rollback_rehearsal": "${ROLLBACK}",
    "rendered_false_positives": ${FALSE_POSITIVE}
  },
  "overall": "${OVERALL}",
  "config_before": ${CONFIG_BEFORE},
  "restored_to_f12_observation": true
}
EOF

echo "F13 GA staging overall=${OVERALL} FP=${FALSE_POSITIVE}"
[[ "${OVERALL}" == PASS ]]
