# A.SEOf Evidence — Redirect / Routing Health (SF9)

## Known debt

`/sv/` (and `/sv`) front-page responds with **301 → itself** (self-loop). Proven pre-existing across A.SEOd/A.SEOe; not caused by SEO overlays.

Live 2026-08-09: `curl -sI https://dev.biopentra.eu/sv/` → `301` `location: https://dev.biopentra.eu/sv/`.

## Safe diagnostic shape

- Bounded redirect-chain follow (max depth, e.g. 5–10)
- Detect loop / excessive chain / cross-host surprise
- Report severity + URL + hop list
- **Must not** fix Router / front-page ownership inside A.SEOf

## Bounds

No site-wide crawler. Prefer current-object URL + optional small allowlisted samples. No SEO-specific Jobs pipeline.

**Disposition:** SF9 **Supported** as detect/report only.
