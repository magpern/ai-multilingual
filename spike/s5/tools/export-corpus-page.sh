#!/usr/bin/env bash
#
# Spike S5 corpus tooling — exports one dev-site post's raw post_content into
# spike/s5/corpus/authored/, for a page that was authored through the real
# Gutenberg editor. THROWAWAY. Branch spike/s5 only.
#
# Why this exists: the dev site has no real Gutenberg corpus to harvest (see
# docs/spikes/S5-gutenberg-segment-identity.md, corpus section) — every fixture
# used to draw conclusions must come from genuine editor output, so this script
# is the one approved path from "a page you authored in wp-admin" to "a fixture
# file the spike's tests can load". Hand-written block markup must never be
# substituted here.
#
# Usage (run from anywhere; cds into the WordPress compose directory itself):
#   spike/s5/tools/export-corpus-page.sh <post_id> <slug> [provenance]
#
#   provenance defaults to "editor" (this tool's original assumption: the
#   page was authored through the real Gutenberg browser editor) and writes
#   MANIFEST.md's original header. Pass "automated" if the page was created
#   any other way (WP-CLI, a script, hand-composed markup fed to
#   `wp post create`) — this writes an HONEST header instead, so a future
#   misuse of this tool cannot reproduce the false-provenance problem
#   documented in PROVENANCE_NOTICE.md / MANIFEST-AUTOMATED.md for the
#   existing corpus in this directory.
#
# Example:
#   spike/s5/tools/export-corpus-page.sh 4701 nested-group-columns
#   spike/s5/tools/export-corpus-page.sh 4701 nested-group-columns automated
#
# Writes:
#   spike/s5/corpus/authored/<slug>.html   (raw post_content, byte-for-byte)
# Appends one line to:
#   spike/s5/corpus/authored/MANIFEST.md
set -euo pipefail

if [ "$#" -lt 2 ] || [ "$#" -gt 3 ]; then
	echo "Usage: $0 <post_id> <slug> [editor|automated]" >&2
	exit 1
fi

POST_ID="$1"
SLUG="$2"
PROVENANCE="${3:-editor}"

if [ "$PROVENANCE" != "editor" ] && [ "$PROVENANCE" != "automated" ]; then
	echo "provenance must be 'editor' or 'automated', got: $PROVENANCE" >&2
	exit 1
fi

if ! [[ "$POST_ID" =~ ^[0-9]+$ ]]; then
	echo "post_id must be numeric, got: $POST_ID" >&2
	exit 1
fi

if ! [[ "$SLUG" =~ ^[a-z0-9][a-z0-9-]*$ ]]; then
	echo "slug must be lowercase-kebab-case, got: $SLUG" >&2
	exit 1
fi

SPIKE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../" && pwd)"
CORPUS_DIR="$SPIKE_ROOT/corpus/authored"
OUT_FILE="$CORPUS_DIR/$SLUG.html"
MANIFEST="$CORPUS_DIR/MANIFEST.md"
WORDPRESS_COMPOSE_DIR="/opt/biopentra/apps/wordpress"

mkdir -p "$CORPUS_DIR"

if [ -f "$OUT_FILE" ]; then
	echo "Refusing to overwrite existing fixture: $OUT_FILE" >&2
	echo "Remove it first if you intend to re-export this page." >&2
	exit 1
fi

POST_TYPE="$(cd "$WORDPRESS_COMPOSE_DIR" && docker compose run --rm -T wpcli wp post get "$POST_ID" --field=post_type 2>/dev/null)"

if [ -z "$POST_TYPE" ]; then
	echo "wp post get returned nothing for post_id=$POST_ID — does it exist?" >&2
	exit 1
fi

STATUS="$(cd "$WORDPRESS_COMPOSE_DIR" && docker compose run --rm -T wpcli wp post get "$POST_ID" --field=post_status 2>/dev/null)"

if [ "$STATUS" != "draft" ] && [ "$STATUS" != "publish" ]; then
	echo "post_id=$POST_ID has unexpected status '$STATUS' — expected draft or publish." >&2
	exit 1
fi

( cd "$WORDPRESS_COMPOSE_DIR" && docker compose run --rm -T wpcli wp post get "$POST_ID" --field=content 2>/dev/null ) > "$OUT_FILE"

BYTES="$(wc -c < "$OUT_FILE" | tr -d ' ')"
SHA1="$(sha1sum "$OUT_FILE" | cut -d' ' -f1)"

if [ "$BYTES" -eq 0 ]; then
	rm -f "$OUT_FILE"
	echo "Exported content was empty — refusing to add an empty fixture. Is the page actually saved with block content?" >&2
	exit 1
fi

if ! grep -q '<!-- wp:' "$OUT_FILE"; then
	echo "WARNING: $OUT_FILE contains no '<!-- wp:' block markers — this does not look like Gutenberg block content." >&2
fi

touch "$MANIFEST"
if [ ! -s "$MANIFEST" ]; then
	if [ "$PROVENANCE" = "automated" ]; then
		{
			echo "# Corpus manifest — automated/provisional fixtures"
			echo
			echo "Every row here was created via WP-CLI or a script, NOT the real"
			echo "Gutenberg browser editor. This is provisional evidence only — see"
			echo "PROVENANCE_NOTICE.md in this directory before using it as evidence"
			echo "about editor serialization behaviour."
			echo
			echo "| Slug | Post ID | Bytes | SHA1 |"
			echo "|---|---|---|---|"
		} > "$MANIFEST"
	else
		{
			echo "# Corpus manifest — editor-authored fixtures"
			echo
			echo "Every row here was exported from a page created through the real"
			echo "Gutenberg editor on the dev site via export-corpus-page.sh. None of"
			echo "this markup was hand-written."
			echo
			echo "| Slug | Post ID | Bytes | SHA1 |"
			echo "|---|---|---|---|"
		} > "$MANIFEST"
	fi
fi

echo "| $SLUG | $POST_ID | $BYTES | $SHA1 |" >> "$MANIFEST"

echo "Exported post_id=$POST_ID ($BYTES bytes) -> $OUT_FILE"
echo "Manifest updated: $MANIFEST"
