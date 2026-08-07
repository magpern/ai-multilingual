# AIML Reference Integration (test/acceptance only)

This fixture proves Integration API v1. It is **not** a production merchant integration.

## Containment

- Path: `tests/fixtures/aiml-reference-integration/`
- Autoload: Composer `autoload-dev` only (`AIMultilingual\Tests\`)
- Production `Plugin.php` does **not** register this integration
- `bin/build-zip.sh` copies `src/` only — this path is never packaged

Deleting this fixture leaves production runtime unchanged.
