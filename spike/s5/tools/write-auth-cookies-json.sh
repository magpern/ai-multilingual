#!/usr/bin/env bash
# Write auth-cookies.json for Playwright (host-side). THROWAWAY — spike/s5 only.
set -euo pipefail

AIML_ROOT="/opt/biopentra/dev/ai-multilingual"
OUT="$AIML_ROOT/spike/s5/corpus/browser-validation/auth-cookies.json"
mkdir -p "$(dirname "$OUT")"

COOKIES="$(/opt/biopentra/dev/ai-multilingual/spike/s5/tools/wp-auth-cookies.sh 1)"
NAMES="$(cd /opt/biopentra/apps/wordpress && docker compose run --rm -T wpcli wp eval 'echo json_encode(["auth"=>AUTH_COOKIE,"logged_in"=>LOGGED_IN_COOKIE,"secure_auth"=>SECURE_AUTH_COOKIE]);' 2>/dev/null)"

python3 - <<PY
import json
from pathlib import Path
out = {
  "cookies": json.loads('''$COOKIES'''),
  "names": json.loads('''$NAMES'''),
}
Path("$OUT").write_text(json.dumps(out, indent=2))
print("Wrote", "$OUT")
PY
