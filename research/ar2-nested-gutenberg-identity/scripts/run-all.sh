#!/usr/bin/env bash
# A.R2 research runner — EXPERIMENTAL
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
SCRIPT_REL="wp-content/plugins/ai-multilingual/research/ar2-nested-gutenberg-identity/scripts"
COMPOSE_DIR="/opt/biopentra/apps/wordpress"

chmod 777 "$ROOT/research/ar2-nested-gutenberg-identity/evidence" 2>/dev/null || true

run() {
  local script="$1"
  echo "==> $script"
  ( cd "$COMPOSE_DIR" && docker compose run --rm wpcli wp eval-file "$SCRIPT_REL/$script" )
}

run a40-inventory.php
run a41-uuid-stability.php
run a42-traversal.php
run a43-a46-families.php
run a47-render-perf.php
echo "A.R2 research scripts complete."
