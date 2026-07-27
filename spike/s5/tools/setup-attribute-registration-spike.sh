#!/usr/bin/env bash
#
# Attribute-registration spike: create posts for registered-vs-unregistered
# aimlBlockId comparison. Uses a SEPARATE manifest file so it does not disturb
# the Tier 1 manifest.json / summary.json already collected.
#
# Run this ONCE with the zzz-s5-attribute-registration-spike.php mu-plugin
# ACTIVE (registers aimlBlockId on core/paragraph + core/heading), then run
# the Playwright cases, export, and analyze. The "reg-*" slugs are the
# registered-attribute condition; compare against the existing unregistered
# "op-text-edit", "op-duplicate", "op-transform-p2h" results already in
# summary.json for the same operations.
#
# THROWAWAY. Branch spike/s5 only.
set -euo pipefail

AIML_ROOT="/opt/biopentra/dev/ai-multilingual"
MANIFEST="$AIML_ROOT/spike/s5/corpus/browser-validation/manifest-attr-spike.json"
FIXTURES="$AIML_ROOT/spike/s5/browser-validation/fixtures"
CREATE="$AIML_ROOT/spike/s5/tools/create-browser-validation-post.sh"
EXPORT="$AIML_ROOT/spike/s5/tools/export-browser-validation-post.sh"

mkdir -p "$(dirname "$MANIFEST")"
echo '[]' > "$MANIFEST"

add_case() {
	local slug="$1" title="$2" fixture="$3" operation="$4"
	local json post_id
	json="$("$CREATE" "$slug" "$title" "$FIXTURES/$fixture")"
	post_id="$(echo "$json" | python3 -c 'import json,sys; print(json.load(sys.stdin)["post_id"])')"
	"$EXPORT" "$post_id" "${slug}-baseline" > /dev/null
	python3 - <<PY
import json
from pathlib import Path
p = Path("$MANIFEST")
items = json.loads(p.read_text())
items.append({
  "slug": "$slug",
  "title": "$title",
  "fixture": "$fixture",
  "operation": "$operation",
  "post_id": int("$post_id"),
  "baseline_container_path": "/aiml/spike/s5/corpus/browser-validation/${slug}-baseline-post-$post_id.html",
})
p.write_text(json.dumps(items, indent=2))
PY
	echo "Created $slug post_id=$post_id op=$operation"
}

add_case "reg-text-edit" "S5 Attr-Reg — Text edit" "core-paragraph.html" "text_edit"
add_case "reg-duplicate" "S5 Attr-Reg — Duplicate" "duplicate-single.html" "duplicate_block"
add_case "reg-transform-p2h" "S5 Attr-Reg — Transform p2h" "core-paragraph.html" "transform_p2h"
add_case "reg-heading-edit" "S5 Attr-Reg — Heading text edit" "core-heading.html" "text_edit_heading"

echo "Attribute-registration spike manifest written: $MANIFEST"
