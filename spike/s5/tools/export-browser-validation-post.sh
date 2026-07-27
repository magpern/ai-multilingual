#!/usr/bin/env bash
#
# Export post_content and analysis for browser validation evidence.
#
# Usage:
#   export-browser-validation-post.sh <post_id> <slug> [baseline_post_id]
#
# THROWAWAY. Branch spike/s5 only.
set -euo pipefail

if [ "$#" -lt 2 ] || [ "$#" -gt 3 ]; then
	echo "Usage: $0 <post_id> <slug> [baseline_post_id]" >&2
	exit 1
fi

POST_ID="$1"
SLUG="$2"
BASELINE_ID="${3:-}"
WORDPRESS_COMPOSE_DIR="/opt/biopentra/apps/wordpress"
AIML_ROOT="/opt/biopentra/dev/ai-multilingual"
OUT_DIR="$AIML_ROOT/spike/s5/corpus/browser-validation"

mkdir -p "$OUT_DIR"

CONTENT_FILE="$OUT_DIR/${SLUG}-post-${POST_ID}.html"
ANALYSIS_FILE="$OUT_DIR/${SLUG}-analysis.json"

( cd "$WORDPRESS_COMPOSE_DIR" && docker compose run --rm -T wpcli \
	wp post get "$POST_ID" --field=content 2>/dev/null ) > "$CONTENT_FILE"

if [ -n "$BASELINE_ID" ]; then
	( cd "$WORDPRESS_COMPOSE_DIR" && docker compose run --rm -T \
		-v "$AIML_ROOT:/aiml:ro" wpcli \
		wp eval-file /aiml/spike/s5/tools/analyze-aiml-content.php "$POST_ID" "$BASELINE_ID" 2>/dev/null ) > "$ANALYSIS_FILE"
else
	( cd "$WORDPRESS_COMPOSE_DIR" && docker compose run --rm -T \
		-v "$AIML_ROOT:/aiml:ro" wpcli \
		wp eval-file /aiml/spike/s5/tools/analyze-aiml-content.php "$POST_ID" 2>/dev/null ) > "$ANALYSIS_FILE"
fi

BYTES="$( wc -c < "$CONTENT_FILE" | tr -d ' ' )"
SHA1="$( sha1sum "$CONTENT_FILE" | cut -d' ' -f1 )"

printf '{"content_file":"%s","analysis_file":"%s","bytes":%s,"sha1":"%s"}\n' \
	"$CONTENT_FILE" "$ANALYSIS_FILE" "$BYTES" "$SHA1"
