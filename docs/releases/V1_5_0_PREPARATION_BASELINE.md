# v1.5.0 Release Preparation Baseline

**Branch:** `release/v1.5.0-preparation`  
**Baseline main HEAD:** `3e3e9d7510bf8a00caab462647870a6cd512f54e`  
**Previous tag:** `v1.4.0` → `ee49cc906babfd34b67fd0998f1eb7553a03358f`  
**Version decision:** **1.5.0**  
**TARGET:** 8 (unchanged)  
**Programs:** TIQ Complete; OTL Complete; TSC Complete; **MSEO.0–MSEO.5 Gate A hardened** (release + dogfood complete program under Gates C–D)  
**Preparation contents:** version metadata, CHANGELOG, readme Stable tag, release notes, release scope — no new product architecture beyond MSEO work already on `main`

## Release version decision

| Option | Verdict |
|---|---|
| Patch `1.4.x` | **Rejected** — understates complete Multilingual SEO & Localized URLs program |
| Minor **1.5.0** | **Selected** — additive localized URL capability (default OFF); MSEO.0–5; TARGET remains 8; no schema migration |
| Major `2.0.0` | **Rejected** — no breaking public contract; TARGET unchanged |

## Deployment terminology

- **Gate C** publishes GitHub Release ZIP only.  
- **Gate D** may perform **DEV DOGFOOD DEPLOYMENT** to dev.biopentra.eu.  
- **PRODUCTION DEPLOYMENT** is **not** authorized by this release preparation.
