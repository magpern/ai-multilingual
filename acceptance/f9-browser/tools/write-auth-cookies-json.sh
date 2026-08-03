#!/usr/bin/env bash
# Write auth-cookies.json for F9 Playwright (host-side).
set -euo pipefail

AIML_ROOT="/opt/biopentra/dev/ai-multilingual"
OUT="$AIML_ROOT/acceptance/f9-browser/artifacts/auth-cookies.json"
mkdir -p "$(dirname "$OUT")"

COOKIES="$("$AIML_ROOT/spike/s5/tools/wp-auth-cookies.sh" 1)"
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
