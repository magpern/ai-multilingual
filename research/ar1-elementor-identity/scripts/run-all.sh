#!/usr/bin/env bash
# A.R1 EXPERIMENTAL — run research scripts via WP-CLI (explicit load only).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
WP="/opt/biopentra/apps/wordpress"
SCRIPT_REL="wp-content/plugins/universal-multilingual/research/ar1-elementor-identity/scripts"

run() {
  local name="$1"
  echo "=== Running $name ==="
  (cd "$WP" && docker compose run --rm wpcli wp eval-file "/var/www/html/${SCRIPT_REL}/${name}")
}

run er0-inventory.php
run er1-stability.php
run er3-taxonomy.php
run er4-hooks-and-candidate-b.php
run er5-ownership.php
run er6-performance.php
echo "=== A.R1 research scripts complete ==="
