# Packaging note — A.R2 research artifacts

**EXPERIMENTAL / RESEARCH ONLY**

Paths that must be excluded from production Release ZIPs:

- `research/`
- `acceptance/ar2-nested-gutenberg/` (if created)

These directories are not loaded by `Plugin.php`. Deleting them leaves runtime unchanged.
