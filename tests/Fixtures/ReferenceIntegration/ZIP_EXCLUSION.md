# ZIP exclusion — A.1 reference fixture

Paths that must remain excluded from production Release ZIPs:

- `tests/` (entire tree, including `tests/fixtures/aiml-reference-integration/`)
- `docs/plans/a1-evidence/` (evidence only; optional)

Evidence: `bin/build-zip.sh` copies only plugin root files, `src/`, `vendor/`, and `assets/`.
