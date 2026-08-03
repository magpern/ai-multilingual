#!/usr/bin/env bash
# Export post_content + production UUID analysis for F9 acceptance.
set -euo pipefail

POST_ID="${1:?post id}"
SLUG="${2:?slug}"
BASELINE="${3:-}"
AIML_ROOT="/opt/biopentra/dev/ai-multilingual"
OUT_DIR="$AIML_ROOT/acceptance/f9-browser/artifacts"
WORDPRESS="/opt/biopentra/apps/wordpress"

mkdir -p "$OUT_DIR"

CONTENT_FILE="$OUT_DIR/${SLUG}-post-${POST_ID}.html"
ANALYSIS_FILE="$OUT_DIR/${SLUG}-analysis.json"

(
  cd "$WORDPRESS"
  docker compose run --rm -T -v "$AIML_ROOT:/aiml:ro" wpcli \
    wp post get "$POST_ID" --field=content --user=1
) > "$CONTENT_FILE"

BASELINE_ARG=""
if [[ -n "$BASELINE" ]]; then
  BASELINE_ARG="$BASELINE"
fi

(
  cd "$WORDPRESS"
  if [[ -n "$BASELINE_ARG" ]]; then
    docker compose run --rm -T -v "$AIML_ROOT:/aiml:ro" wpcli \
      wp eval-file "/aiml/acceptance/f9-browser/tools/analyze-aiml-content.php" "$POST_ID" "$BASELINE_ARG" --user=1
  else
    docker compose run --rm -T -v "$AIML_ROOT:/aiml:ro" wpcli \
      wp eval-file "/aiml/acceptance/f9-browser/tools/analyze-aiml-content.php" "$POST_ID" --user=1
  fi
) > "$ANALYSIS_FILE"

echo "Exported $CONTENT_FILE and $ANALYSIS_FILE"
