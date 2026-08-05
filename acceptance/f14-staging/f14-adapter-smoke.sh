#!/usr/bin/env bash
# F14 targeted browser smoke for one admitted leaf adapter (not F9 35-suite).
# Usage: bash f14-adapter-smoke.sh <block_key>
# block_key: list-item | preformatted | verse | code
set -euo pipefail

ROOT="/opt/biopentra/dev/ai-multilingual"
WP="/opt/biopentra/apps/wordpress"
HELPER="wp-content/plugins/ai-multilingual/acceptance/f14-staging/f14-helper.php"
BASE_URL="https://dev.biopentra.eu"
KEY="${1:?block_key required}"
EVIDENCE="${ROOT}/docs/plans/F14_ADMISSION_${KEY//-/_}_EVIDENCE.json"
RESTORE_JSON='{"rollout_render_enabled":true,"rollout_stage":2,"allowed_post_ids":[6321],"allowed_language_codes":["sv"],"general_rollout_enabled":false,"render_cache_enabled":false}'

wp() { docker compose -f "${WP}/compose.yml" run --rm wpcli wp "$@"; }
helper() { wp --user=1 eval-file "${HELPER}" "$@"; }
fetch() { curl -sL "${BASE_URL}/sv/${1}/" -H 'Cache-Control: no-cache' 2>/dev/null || true; }

cd "${WP}"
wp eval 'AIMultilingual\Rollout\RolloutCapabilities::grant_default_roles();' >/dev/null

META_JSON=$(helper create_adapter_post "${KEY}")
parse() { python3 -c "import json,sys; d=json.loads(sys.argv[1]); print(d[sys.argv[2]])" "$1" "$2"; }
POST_ID=$(parse "${META_JSON}" post_id)
SLUG=$(parse "${META_JSON}" slug)
SRC=$(parse "${META_JSON}" source_text)
TR=$(parse "${META_JSON}" trans_text)
BLOCK=$(parse "${META_JSON}" block_name)

# Ensure block is in SUPPORTED_BLOCKS
BLOCKS=$(helper supported_blocks)
SUPPORTED=$(python3 -c "import json,sys; print('PASS' if sys.argv[2] in json.loads(sys.argv[1]) else 'FAIL')" "${BLOCKS}" "${BLOCK}")

# Stage 2 allow this post only
helper apply "{\"rollout_render_enabled\":true,\"rollout_stage\":2,\"allowed_post_ids\":[${POST_ID}],\"allowed_language_codes\":[\"sv\"],\"general_rollout_enabled\":false,\"render_cache_enabled\":false}" >/dev/null
sleep 2
HTML=$(fetch "${SLUG}")
RENDER=$([[ "${HTML}" == *"${TR}"* ]] && echo PASS || echo FAIL)

# Deny path: wrong language (en)
HTML_EN=$(curl -sL "${BASE_URL}/${SLUG}/" -H 'Cache-Control: no-cache' 2>/dev/null || true)
DENY=$([[ "${HTML_EN}" != *"${TR}"* ]] && echo PASS || echo FAIL)

FP=0
[[ "${DENY}" == PASS && "${HTML_EN}" == *"${TR}"* ]] && FP=1
[[ "${RENDER}" == PASS && "${HTML}" != *"${TR}"* ]] && FP=$((FP + 1))

helper delete_post "${POST_ID}" >/dev/null
helper apply "${RESTORE_JSON}" >/dev/null

OVERALL=PASS
[[ "${SUPPORTED}" == PASS && "${RENDER}" == PASS && "${DENY}" == PASS && "${FP}" -eq 0 ]] || OVERALL=FAIL

cat > "${EVIDENCE}" <<EOF
{
  "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "adapter": "${BLOCK}",
  "admission_key": "${KEY}",
  "branch": "$(git -C "${ROOT}" branch --show-current)",
  "head": "$(git -C "${ROOT}" rev-parse HEAD)",
  "post_id": ${POST_ID},
  "results": {
    "supported_blocks_contains": "${SUPPORTED}",
    "translated_render": "${RENDER}",
    "source_language_deny": "${DENY}",
    "rendered_false_positives": ${FP}
  },
  "overall": "${OVERALL}"
}
EOF

echo "F14 adapter smoke ${KEY} overall=${OVERALL} FP=${FP}"
[[ "${OVERALL}" == PASS ]]
