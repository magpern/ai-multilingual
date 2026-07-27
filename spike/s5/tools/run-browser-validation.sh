#!/usr/bin/env bash
#
# Run Strategy F browser validation via Playwright Docker image.
# Requires Docker socket for WP-CLI helper calls. THROWAWAY — spike/s5 only.
set -euo pipefail

AIML_ROOT="/opt/biopentra/dev/ai-multilingual"
BV_DIR="$AIML_ROOT/spike/s5/browser-validation"
PLAYWRIGHT_IMAGE="${PLAYWRIGHT_IMAGE:-mcr.microsoft.com/playwright:v1.51.0-jammy}"
HOST_UID="$(id -u)"
HOST_GID="$(id -g)"

# Host prepares posts (WP-CLI); container runs browser only.
bash "$AIML_ROOT/spike/s5/tools/setup-browser-validation-manifest.sh"
bash "$AIML_ROOT/spike/s5/tools/write-auth-cookies-json.sh"

mkdir -p "$AIML_ROOT/spike/s5/browser-validation/test-results"
docker run --rm -v /opt/biopentra/dev/ai-multilingual:/app alpine chown -R "${HOST_UID}:${HOST_GID}" \
	/app/spike/s5/browser-validation/test-results \
	/app/spike/s5/corpus/browser-validation \
	/app/spike/s5/browser-validation/node_modules 2>/dev/null || true

docker run --rm \
	--user "${HOST_UID}:${HOST_GID}" \
	-v "$AIML_ROOT:/app" \
	-v /opt/biopentra/apps/wordpress/.admin-credentials:/run/secrets/wp-credentials:ro \
	-e WP_BASE_URL="${WP_BASE_URL:-https://dev.biopentra.eu}" \
	-e WP_CREDENTIALS_FILE=/run/secrets/wp-credentials \
	-e HOME=/tmp \
	-w /app/spike/s5/browser-validation \
	"$PLAYWRIGHT_IMAGE" \
	bash -lc 'npm install && npx playwright test tests/manifest-driven.spec.ts'

bash "$AIML_ROOT/spike/s5/tools/collect-browser-validation-results.sh"

echo "Evidence: $AIML_ROOT/spike/s5/corpus/browser-validation/"
