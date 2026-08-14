# AI Multilingual v1.4.0 — Release Closure

**Status:** **RELEASED / CLOSED**
**Version:** 1.4.0
**Schema TARGET:** **7** (unchanged)
**Release commit (tagged):** `ee49cc906babfd34b67fd0998f1eb7553a03358f`
**Annotated tag:** `v1.4.0` (tag object points at release commit above)
**Preparation branch:** `release/v1.4.0-preparation` (PR #33)
**Reviewed preparation HEAD:** `8a44ae8a7`
**Independent review:** **RELEASE PREPARATION REVIEW: PASS**
**Preparation CI:** run `31780957062` SUCCESS
**Fresh main CI (pre-tag):** run `31781087884` SUCCESS
**Release workflow:** run `31781195588` SUCCESS
**GitHub Release:** https://github.com/magpern/ai-multilingual/releases/tag/v1.4.0
**Published artifact:** `ai-multilingual-1.4.0.zip` — **689560** bytes, **444** entries; audit **PASS**; version **1.4.0**; TARGET **7**
**Previous release:** `v1.3.0` @ `c88ba30681439d9e7113a20d7ebc03c942dd240d`
**Baseline before preparation:** `505b818117bd830611f004ffe4bd16ac275d5286`

## Version decision

**RELEASE VERSION DECISION: 1.4.0**

Minor release justified by TSC.0–TSC.6 Complete and formal Extension API v1 on the v1.3.0 TIQ/OTL baseline. Patch rejected (understates scope). Major rejected (no breaking public contracts; TARGET unchanged).

## Programs

| Program | Status |
|---|---|
| TIQ (TQ.0–TI.7) | **Complete** |
| OTL (OTL.0–OTL.6) | **Complete** |
| TSC (TSC.0–TSC.6) | **Complete** |

## Upgrade result

v1.3.0 → v1.4.0 requires **no schema migration** (TARGET remains 7). Safe publication defaults unchanged (gate OFF, mode `manual`). Gutenberg/Elementor flags remain OFF by default. Extension API registration is additive and code-driven. Full CI regression **PASS** at release preparation.

## Deployment

**Not deployed** by this release task. Tag-triggered workflow published the audited ZIP to GitHub Releases only. Site installation remains a separate operator action.

## Known limitations / debt (carried forward)

- Public Elementor registration Deferred
- Public CPT/taxonomy admission Deferred
- Yoast adapter Deferred
- Site Health extension diagnostics Deferred
- Generic overlay registration Unsupported
- Translated slugs / SE11 Deferred
- Some Elementor/Gutenberg advanced surfaces Deferred
- Live Playwright local-only
- External CDN cache operator/infrastructure scope
- Does not imply automatic translation of all content

## Exact next roadmap step

Make an explicit product decision on the next post-TSC program from the released v1.4.0 baseline. Do not start a new milestone implicitly.

## Tag vs closure

This closure docs commit advances `main` **after** the tagged release commit. The tag **`v1.4.0` remains on `ee49cc906babfd34b67fd0998f1eb7553a03358f`** and is not moved.
