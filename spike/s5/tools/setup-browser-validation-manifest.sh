#!/usr/bin/env bash
#
# Host-side setup for browser validation: create tagged posts, snapshot a
# genuine pre-operation baseline file for each, and write manifest.json.
#
# ARCHITECTURE (Phase 3 fix): all WP-CLI calls happen HERE on the host via
# `docker compose run wpcli`. The Playwright container (spawned separately,
# see run-browser-validation.sh) has no access to the Docker socket (the repo
# security rules forbid mounting docker.sock) and therefore MUST NOT invoke
# WP-CLI itself. It only performs browser operations against posts that
# already exist, using cookie auth. All post creation, baseline snapshotting,
# and post-operation export/analysis happens host-side, before and after the
# Playwright run respectively (see collect-browser-validation-results.sh).
#
# THROWAWAY. Branch spike/s5 only.
set -euo pipefail

AIML_ROOT="/opt/biopentra/dev/ai-multilingual"
MANIFEST="$AIML_ROOT/spike/s5/corpus/browser-validation/manifest.json"
FIXTURES="$AIML_ROOT/spike/s5/browser-validation/fixtures"
CREATE="$AIML_ROOT/spike/s5/tools/create-browser-validation-post.sh"
EXPORT="$AIML_ROOT/spike/s5/tools/export-browser-validation-post.sh"

mkdir -p "$(dirname "$MANIFEST")"
echo '[]' > "$MANIFEST"

add_case() {
	local slug="$1" title="$2" fixture="$3" operation="$4"
	local extra_json="${5:-{}}"
	local json post_id
	json="$("$CREATE" "$slug" "$title" "$FIXTURES/$fixture")"
	post_id="$(echo "$json" | python3 -c 'import json,sys; print(json.load(sys.stdin)["post_id"])')"

	# Snapshot the pre-operation baseline to a FILE (not another live post
	# fetch) so post-operation analysis can diff against a frozen "before"
	# state instead of comparing the post to itself.
	"$EXPORT" "$post_id" "${slug}-baseline" > /dev/null

	python3 - <<PY
import json
from pathlib import Path
p = Path("$MANIFEST")
items = json.loads(p.read_text())
extra = json.loads('''$extra_json''')
item = {
  "slug": "$slug",
  "title": "$title",
  "fixture": "$fixture",
  "operation": "$operation",
  "post_id": int("$post_id"),
  "baseline_container_path": "/aiml/spike/s5/corpus/browser-validation/${slug}-baseline-post-$post_id.html",
}
item.update(extra)
items.append(item)
p.write_text(json.dumps(items, indent=2))
PY
	echo "Created $slug post_id=$post_id op=$operation"
}

# --- Block-type coverage (noop save) ---
add_case "bv-paragraph" "S5 BV — Paragraph" "core-paragraph.html" "noop_save"
add_case "bv-heading" "S5 BV — Heading" "core-heading.html" "noop_save"
add_case "bv-list" "S5 BV — List" "core-list.html" "noop_save"
add_case "bv-buttons" "S5 BV — Buttons" "core-buttons.html" "noop_save"
add_case "bv-group" "S5 BV — Group" "core-group.html" "noop_save"
add_case "bv-columns" "S5 BV — Columns" "core-columns.html" "noop_save"
add_case "bv-quote" "S5 BV — Quote" "core-quote.html" "noop_save"
add_case "bv-separator" "S5 BV — Separator" "core-separator.html" "noop_save"
add_case "bv-html" "S5 BV — HTML" "core-html.html" "noop_save"
add_case "bv-pullquote" "S5 BV — Pullquote" "core-pullquote.html" "noop_save"
add_case "bv-table" "S5 BV — Table" "core-table.html" "noop_save"
add_case "bv-spacer" "S5 BV — Spacer" "core-spacer.html" "noop_save"
add_case "bv-shortcode" "S5 BV — Shortcode" "core-shortcode.html" "noop_save"
add_case "bv-image" "S5 BV — Image" "core-image.html" "noop_save"
add_case "bv-cover" "S5 BV — Cover" "core-cover.html" "noop_save"
add_case "bv-media-text" "S5 BV — Media text" "core-media-text.html" "noop_save"
add_case "bv-noop-cycles" "S5 BV — No-op cycles" "operations-multi-paragraph.html" "noop_save_x3"
add_case "bv-nested" "S5 BV — Nested subtree" "nested-subtree.html" "noop_save"

# --- Tier 1: mandatory identity operations ---
add_case "op-text-edit" "S5 Ops — Text edit" "core-paragraph.html" "text_edit"
add_case "op-full-rewrite" "S5 Ops — Full rewrite" "core-paragraph.html" "full_rewrite"
add_case "op-duplicate" "S5 Ops — Duplicate" "duplicate-single.html" "duplicate_block"
add_case "op-duplicate-button" "S5 Ops — Duplicate button" "core-buttons.html" "duplicate_button"
add_case "op-duplicate-nested" "S5 Ops — Duplicate nested subtree" "nested-subtree.html" "duplicate_nested_subtree"
add_case "op-copy-paste-same" "S5 Ops — Copy paste same post" "core-paragraph.html" "copy_paste_same_post"
add_case "op-reorder-adjacent" "S5 Ops — Reorder adjacent" "operations-multi-paragraph.html" "reorder_adjacent"
add_case "op-reorder-nonadjacent" "S5 Ops — Reorder non-adjacent" "operations-multi-paragraph.html" "reorder_nonadjacent"
add_case "op-wrap-group" "S5 Ops — Wrap in group" "core-paragraph.html" "wrap_in_group"
add_case "op-unwrap-group" "S5 Ops — Unwrap from group" "core-group.html" "unwrap_from_group"
add_case "op-move-into-columns" "S5 Ops — Move between columns" "core-columns.html" "move_between_columns"
add_case "op-transform-p2h" "S5 Ops — Transform paragraph to heading" "core-paragraph.html" "transform_p2h"
add_case "op-transform-p2q" "S5 Ops — Transform paragraph to quote" "core-paragraph.html" "transform_p2q"
add_case "op-transform-h2p" "S5 Ops — Transform heading to paragraph" "core-heading.html" "transform_h2p"
add_case "op-split" "S5 Ops — Split paragraph" "core-paragraph.html" "split_paragraph"
add_case "op-merge" "S5 Ops — Merge paragraphs" "operations-multi-paragraph.html" "merge_paragraphs"
add_case "op-undo-redo" "S5 Ops — Undo redo" "core-paragraph.html" "undo_redo_text_edit"
add_case "op-delete-undo" "S5 Ops — Delete then undo" "core-paragraph.html" "delete_then_undo"

# --- Duplicate-UUID repair inputs (browser-produced, not synthetic) ---
add_case "dup-paragraph" "S5 Dup — Paragraph" "duplicate-single.html" "duplicate_block"
add_case "dup-button" "S5 Dup — Button" "core-buttons.html" "duplicate_button"
add_case "dup-group-subtree" "S5 Dup — Group subtree" "nested-subtree.html" "duplicate_nested_subtree"
add_case "dup-identical-content" "S5 Dup — Identical content" "duplicate-single.html" "duplicate_block"
add_case "dup-modified-content" "S5 Dup — Modified content" "duplicate-single.html" "duplicate_then_edit_copy"

echo "Manifest written: $MANIFEST"
