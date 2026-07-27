#!/usr/bin/env bash
#
# Create a draft page for Strategy F browser validation and inject aimlBlockId UUIDs.
#
# Usage:
#   create-browser-validation-post.sh <slug> <title> <content_file>
#
# Prints JSON: {"post_id": N, "inject": {...}}
#
# THROWAWAY. Branch spike/s5 only.
set -euo pipefail

if [ "$#" -ne 3 ]; then
	echo "Usage: $0 <slug> <title> <content_file>" >&2
	exit 1
fi

SLUG="$1"
TITLE="$2"
CONTENT_FILE="$3"
WORDPRESS_COMPOSE_DIR="/opt/biopentra/apps/wordpress"
AIML_ROOT="/opt/biopentra/dev/ai-multilingual"
INJECT_PHP="$AIML_ROOT/spike/s5/tools/inject-aiml-block-ids.php"

if [ ! -f "$CONTENT_FILE" ]; then
	echo "Content file not found: $CONTENT_FILE" >&2
	exit 1
fi

POST_ID="$( cd "$WORDPRESS_COMPOSE_DIR" && docker compose run --rm -T wpcli \
	wp post create - \
	--post_type=page \
	--post_status=draft \
	--post_title="$TITLE" \
	--post_name="$SLUG" \
	--porcelain 2>/dev/null < "$CONTENT_FILE" )"

INJECT_JSON="$( cd "$WORDPRESS_COMPOSE_DIR" && docker compose run --rm -T \
	-v "$AIML_ROOT:/aiml:ro" wpcli \
	wp eval-file /aiml/spike/s5/tools/inject-aiml-block-ids.php "$POST_ID" 2>/dev/null )"

printf '{"post_id": %s, "slug": "%s", "inject": %s}\n' "$POST_ID" "$SLUG" "$INJECT_JSON"
