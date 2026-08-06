# Packaging note — A.R1 research artifacts

**EXPERIMENTAL / RESEARCH ONLY**

Paths that must be excluded from production Release ZIPs:

- `research/`
- `acceptance/ar1-elementor/`

These directories are not loaded by `Plugin.php`. Deleting them leaves runtime unchanged.
