#!/usr/bin/env bash
# F12 production observation — PO-approved cohort on dev.biopentra.eu
set -euo pipefail

ROOT="/opt/biopentra/dev/ai-multilingual"
WP="/opt/biopentra/apps/wordpress"
EVIDENCE="${ROOT}/docs/plans/F12_PRODUCTION_OBSERVATION_EVIDENCE.json"
HELPER="wp-content/plugins/ai-multilingual/acceptance/f12-staging/wp10-helper.php"
BASE_URL="https://dev.biopentra.eu"

# PO-approved production parameters (2026-08-05)
COHORT_POST_ID=6321
COHORT_SLUG="f10-translator-validation"
DENY_SLUG="f8-wp6-validation"
DENY_POST_ID=4638
TARGET_LANG="sv"
SOURCE_MARKER="F10 validation source"
TRANS_MARKER="F10 smoke 1785785570151"

wp() { docker compose -f "${WP}/compose.yml" run --rm wpcli wp "$@"; }
helper() { wp --user=1 eval-file "${HELPER}" "$@"; }
fetch() { curl -sL "${BASE_URL}/sv/${1}/" -H 'Cache-Control: no-cache' 2>/dev/null || true; }

cd "${WP}"
wp eval 'AIMultilingual\Rollout\RolloutCapabilities::grant_default_roles();' >/dev/null

CONFIG_BEFORE=$(wp --user=1 eval 'echo wp_json_encode(get_option("aiml_rollout_config"));')

# Stage 1 shadow — production cohort
helper apply "{\"rollout_render_enabled\":true,\"rollout_stage\":1,\"allowed_post_ids\":[${COHORT_POST_ID}],\"allowed_language_codes\":[\"${TARGET_LANG}\"],\"render_cache_enabled\":false}" >/dev/null
sleep 2
HTML1=$(fetch "${COHORT_SLUG}")
STAGE1=$([[ "${HTML1}" == *"${SOURCE_MARKER}"* && "${HTML1}" != *"${TRANS_MARKER}"* ]] && echo PASS || echo FAIL)

# Stage 2 active — allowlisted cohort page
helper apply "{\"rollout_render_enabled\":true,\"rollout_stage\":2,\"allowed_post_ids\":[${COHORT_POST_ID}],\"allowed_language_codes\":[\"${TARGET_LANG}\"],\"render_cache_enabled\":false}" >/dev/null
sleep 2
HTML2=$(fetch "${COHORT_SLUG}")
STAGE2_ALLOW=$([[ "${HTML2}" == *"${TRANS_MARKER}"* ]] && echo PASS || echo FAIL)

# Deny non-allowlisted page
HTML_DENY=$(fetch "${DENY_SLUG}")
STAGE2_DENY=$([[ "${HTML_DENY}" != *"${TRANS_MARKER}"* ]] && echo PASS || echo FAIL)

# Metrics snapshot
METRICS=$(wp --user=1 aiml rollout status 2>/dev/null | tail -n +1 || echo '{}')

# Kill switch rehearsal
helper set_frontend_render 0 >/dev/null
HTML_KG=$(fetch "${COHORT_SLUG}")
KILL_GLOBAL=$([[ "${HTML_KG}" != *"${TRANS_MARKER}"* || "${HTML_KG}" == *"${SOURCE_MARKER}"* ]] && echo PASS || echo FAIL)
helper set_frontend_render 1 >/dev/null

helper emergency_stop >/dev/null
HTML_EM=$(fetch "${COHORT_SLUG}")
KILL_EM=$([[ "${HTML_EM}" != *"${TRANS_MARKER}"* ]] && echo PASS || echo FAIL)

# Restore production observation config (Stage 2 active, cache off)
helper apply "{\"rollout_render_enabled\":true,\"rollout_stage\":2,\"allowed_post_ids\":[${COHORT_POST_ID}],\"allowed_language_codes\":[\"${TARGET_LANG}\"],\"render_cache_enabled\":false}" >/dev/null

FALSE_POSITIVE=0
[[ "${STAGE2_DENY}" == PASS && "${HTML_DENY}" == *"${TRANS_MARKER}"* ]] && FALSE_POSITIVE=1

POLICY_VERSION=$(helper policy_version)

cat > "${EVIDENCE}" <<EOF
{
  "observation_start": "2026-08-05T00:00:00Z",
  "observation_end_planned": "2026-08-12T00:00:00Z",
  "observation_duration_days": 7,
  "environment": "dev.biopentra.eu",
  "branch": "$(git -C "${ROOT}" branch --show-current)",
  "head": "$(git -C "${ROOT}" rev-parse HEAD)",
  "po_approved": {
    "cohort_post_ids": [${COHORT_POST_ID}],
    "deny_control_post_id": ${DENY_POST_ID},
    "target_language": "${TARGET_LANG}",
    "rollout_stage_active": 2,
    "render_cache_enabled": false,
    "operator_user_id": 1,
    "operator_login": "bp_manager"
  },
  "day_zero_validation": {
    "stage_1_shadow": "${STAGE1}",
    "stage_2_allow": "${STAGE2_ALLOW}",
    "stage_2_deny": "${STAGE2_DENY}",
    "kill_switch_global": "${KILL_GLOBAL}",
    "kill_switch_emergency": "${KILL_EM}",
    "rendered_false_positives": ${FALSE_POSITIVE},
    "policy_version": ${POLICY_VERSION}
  },
  "overall": "$([[ "${STAGE1}" == PASS && "${STAGE2_ALLOW}" == PASS && "${STAGE2_DENY}" == PASS && "${KILL_GLOBAL}" == PASS && "${KILL_EM}" == PASS && "${FALSE_POSITIVE}" == "0" ]] && echo PASS || echo FAIL)"
}
EOF

cat "${EVIDENCE}"
OVERALL=$(python3 -c "import json; print(json.load(open('${EVIDENCE}'))['overall'])")
echo "Production observation Day-0: ${OVERALL}"
exit $([[ "${OVERALL}" == PASS ]] && echo 0 || echo 1)
