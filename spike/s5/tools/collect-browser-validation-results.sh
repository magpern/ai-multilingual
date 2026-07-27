#!/usr/bin/env bash
#
# Export all browser-validated posts after Playwright/browser runs, diffing
# each against its OWN pre-operation baseline FILE (captured by
# setup-browser-validation-manifest.sh before the browser touched the post).
#
# Phase 3 fix: earlier versions of this script called
#   export-browser-validation-post.sh <post_id> <slug> <post_id>
# which compares a post's live DB content against ITSELF at the same instant
# (baseline_id == post_id) — a self-comparison that trivially reports
# "preserved" / "content_identical" regardless of what the browser did. See
# analyze-aiml-content.php's warning for the same self-comparison.
#
# THROWAWAY. Branch spike/s5 only.
set -euo pipefail

AIML_ROOT="/opt/biopentra/dev/ai-multilingual"
MANIFEST="$AIML_ROOT/spike/s5/corpus/browser-validation/manifest.json"
EXPORT="$AIML_ROOT/spike/s5/tools/export-browser-validation-post.sh"

python3 - <<'PY'
import json, subprocess
from pathlib import Path

CORPUS = Path("/opt/biopentra/dev/ai-multilingual/spike/s5/corpus/browser-validation")
manifest = json.loads((CORPUS / "manifest.json").read_text())
export = "/opt/biopentra/dev/ai-multilingual/spike/s5/tools/export-browser-validation-post.sh"
results = {}

for item in manifest:
    slug = item["slug"]
    post_id = item["post_id"]
    baseline_path = item.get("baseline_container_path", "")
    subprocess.run([export, str(post_id), slug, baseline_path], check=True)
    analysis = json.loads((CORPUS / f"{slug}-analysis.json").read_text())

    before_blocks = analysis.get("baseline_blocks", {})
    blocks = analysis.get("blocks", [])
    preservation = analysis.get("uuid_preservation", {})

    text_changed_any = any(
        before_blocks.get(str(i), {}).get("text_sha1") != b.get("text_sha1")
        for i, b in enumerate(blocks)
        if str(i) in before_blocks
    )
    block_count_before = len(before_blocks) if before_blocks else None
    block_count_after = analysis.get("eligible_blocks")
    block_count_changed = (
        block_count_before is not None and block_count_after is not None
        and block_count_before != block_count_after
    )

    results[slug] = {
        "post_id": post_id,
        "operation": item["operation"],
        "has_aimlBlockId": analysis.get("has_aimlBlockId"),
        "duplicate_uuids": analysis.get("duplicate_uuids"),
        "uuid_preservation": preservation,
        "content_identical": analysis.get("content_identical"),
        "content_sha1": analysis.get("content_sha1"),
        "baseline_source": analysis.get("baseline_source"),
        "block_count_before": block_count_before,
        "block_count_after": block_count_after,
        "block_count_changed": block_count_changed,
        "text_actually_changed": text_changed_any,
        "duplicate_uuid_detected": len(analysis.get("duplicate_uuids", {})) > 0,
    }

Path("/opt/biopentra/dev/ai-multilingual/spike/s5/corpus/browser-validation/summary.json").write_text(json.dumps(results, indent=2))
print("Wrote summary.json with", len(results), "cases")
PY
