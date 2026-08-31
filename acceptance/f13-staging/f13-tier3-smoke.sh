#!/usr/bin/env bash
# F13 Tier 3 — targeted frontend smoke (F12-proven blocks under GA). Not F9 35-suite.
set -euo pipefail

ROOT="/opt/biopentra/dev/ai-multilingual"
WP="/opt/biopentra/apps/wordpress"
EVIDENCE="${ROOT}/docs/plans/F13_TIER3_BROWSER_SMOKE_EVIDENCE.json"
HELPER="wp-content/plugins/universal-multilingual/acceptance/f13-staging/f13-helper.php"
BASE_URL="https://dev.biopentra.eu"
RESTORE_JSON='{"rollout_render_enabled":true,"rollout_stage":2,"allowed_post_ids":[6321],"allowed_language_codes":["sv"],"general_rollout_enabled":false,"render_cache_enabled":false}'

COHORT_SLUG="f10-translator-validation"
SOURCE_MARKER="F10 validation source"
TRANS_MARKER="F10 smoke 1785785570151"

wp() { docker compose -f "${WP}/compose.yml" run --rm wpcli wp "$@"; }
helper() { wp --user=1 eval-file "${HELPER}" "$@"; }
fetch() { curl -sL "${BASE_URL}/sv/${1}/" -H 'Cache-Control: no-cache' 2>/dev/null || true; }

cd "${WP}"
wp eval 'AIMultilingual\Rollout\RolloutCapabilities::grant_default_roles();' >/dev/null

# Limited stage 2 allow (baseline regression)
helper apply "{\"rollout_render_enabled\":true,\"rollout_stage\":2,\"allowed_post_ids\":[6321],\"allowed_language_codes\":[\"sv\"],\"general_rollout_enabled\":false,\"render_cache_enabled\":false}" >/dev/null
sleep 1
HTML2=$(fetch "${COHORT_SLUG}")
STAGE2=$([[ "${HTML2}" == *"${TRANS_MARKER}"* ]] && echo PASS || echo FAIL)

# Stage 6 GA — same post must still render (not allowlist-dependent)
helper apply "{\"rollout_render_enabled\":true,\"rollout_stage\":6,\"allowed_post_ids\":[],\"allowed_language_codes\":[\"sv\"],\"general_rollout_enabled\":true,\"render_cache_enabled\":false}" >/dev/null
sleep 2
HTML6=$(fetch "${COHORT_SLUG}")
GA_PARAGRAPH=$([[ "${HTML6}" == *"${TRANS_MARKER}"* ]] && echo PASS || echo FAIL)

# Source must not appear as sole content when translation present; false positive check
FP=0
[[ "${HTML6}" != *"${TRANS_MARKER}"* && "${HTML6}" == *"${SOURCE_MARKER}"* ]] && FP=1

# Block inventory frozen
BLOCKS=$(helper supported_blocks)
BLOCKS_OK=$(python3 -c "import json,sys; b=json.loads(sys.argv[1]); print('PASS' if b==['core/paragraph','core/heading','core/button'] else 'FAIL')" "${BLOCKS}")

helper apply "${RESTORE_JSON}" >/dev/null

OVERALL=PASS
[[ "${STAGE2}" == PASS && "${GA_PARAGRAPH}" == PASS && "${BLOCKS_OK}" == PASS && "${FP}" -eq 0 ]] || OVERALL=FAIL

cat > "${EVIDENCE}" <<EOF
{
  "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "tier": 3,
  "scope": "targeted frontend smoke — F12-proven blocks under GA (not F9 35-suite)",
  "environment": "dev.biopentra.eu",
  "branch": "$(git -C "${ROOT}" branch --show-current)",
  "head": "$(git -C "${ROOT}" rev-parse HEAD)",
  "cohort_slug": "${COHORT_SLUG}",
  "supported_blocks": ${BLOCKS},
  "results": {
    "stage2_regression": "${STAGE2}",
    "stage6_ga_paragraph_render": "${GA_PARAGRAPH}",
    "supported_blocks_frozen": "${BLOCKS_OK}",
    "rendered_false_positives": ${FP}
  },
  "overall": "${OVERALL}",
  "note": "Heading/button adapters unchanged; paragraph path exercised on production cohort under GA."
}
EOF

echo "F13 Tier3 smoke overall=${OVERALL}"
[[ "${OVERALL}" == PASS ]]
