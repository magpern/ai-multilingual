#!/usr/bin/env bash
#
# Third-party block browser validation: WooCommerce (dynamic) + Rank Math
# (static) blocks. Separate manifest file so it does not disturb Tier 1.
#
# THROWAWAY. Branch spike/s5 only.
set -euo pipefail

AIML_ROOT="/opt/biopentra/dev/ai-multilingual"
MANIFEST="$AIML_ROOT/spike/s5/corpus/browser-validation/manifest-thirdparty.json"
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

add_case "tp-rankmath-toc-noop" "S5 3P — Rank Math TOC noop" "thirdparty-rankmath-toc.html" "noop_save"
add_case "tp-rankmath-toc-dup" "S5 3P — Rank Math TOC duplicate" "thirdparty-rankmath-toc.html" "duplicate_thirdparty_toc"
add_case "tp-woo-account-noop" "S5 3P — Woo customer-account noop" "thirdparty-woo-customer-account.html" "noop_save"
add_case "tp-woo-account-dup" "S5 3P — Woo customer-account duplicate" "thirdparty-woo-customer-account.html" "duplicate_thirdparty_woo"

echo "Third-party manifest written: $MANIFEST"
