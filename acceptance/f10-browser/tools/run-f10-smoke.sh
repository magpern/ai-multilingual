#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ ! -d node_modules/@playwright/test ]]; then
  npm install
  npx playwright install chromium
fi

export F10_POST_ID="${F10_POST_ID:-6321}"
export F10_LANGUAGE="${F10_LANGUAGE:-sv}"
export WP_BASE_URL="${WP_BASE_URL:-https://dev.biopentra.eu}"

echo "F10 smoke: 4 tests, estimated 2–4 minutes"
npm run test:smoke
