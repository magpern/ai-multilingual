# A.1 Reference Integration — Admission Record

| Field | Value |
|---|---|
| Integration ID | `aiml_reference` |
| Package | `tests/Fixtures/ReferenceIntegration` (test/acceptance only) |
| Ownership | `record-owned` via post meta |
| Supported versions | Simulated `>= 1.0.0` |
| Surfaces | `title` (meta `_aiml_ref_title`); nested `label:primary` (`_aiml_ref_nested_label`) |
| Identity | `p:aiml_reference:record:<post_id>:title` / `…:label:primary` via `PluginIdentity` |
| Extraction | Allowlisted meta only; `Store::source_hash` freshness |
| Overlay hooks | `aiml_reference_integration_title`, `aiml_reference_integration_nested_label` |
| Sanitization | Plain text |
| Lifecycle | missing / inactive / unsupported_version / disabled / compatible |
| Copy/delete | Document-local owner ID = post ID; history retained on disable |
| Cache/output | Official filters only; local source fallback |
| Performance | See `docs/plans/a1-evidence/a1-performance.json` |
| Disposition | **Experimental** (fixture — not a Supported merchant integration) |
| Limitations | Not shipped in production ZIP; not registered from `Plugin.php` |

## Containment evidence

- Not referenced from `src/Plugin.php`
- Lives under `tests/` (dev autoload only)
- `bin/build-zip.sh` packages `src/` only
