# AI Multilingual v1.3.0 — Release Closure

**Status:** **RELEASED / CLOSED**
**Version:** 1.3.0
**Schema TARGET:** **7** (unchanged)
**Release commit (tagged):** `c88ba30681439d9e7113a20d7ebc03c942dd240d`
**Annotated tag:** `v1.3.0` (tag object points at release commit above)
**Preparation branch:** `release/v1.3.0-preparation` (PR #21)
**Reviewed preparation HEAD:** `6e228727182910a349ef416c36ee3aae4eaf2926`
**Independent review:** **RELEASE PREPARATION REVIEW: PASS**
**Preparation CI:** run `31576287997` SUCCESS
**Fresh main CI (pre-tag):** run `31577015085` SUCCESS
**Release workflow:** run `31577172928` SUCCESS
**GitHub Release:** https://github.com/magpern/ai-multilingual/releases/tag/v1.3.0
**Published artifact:** `ai-multilingual-1.3.0.zip` — **606799** bytes, **391** entries; audit **PASS**; version **1.3.0**; TARGET **7**
**Previous release:** `v1.2.0` @ `b67fc296e2b2170dea84228b1acda502e518f07a`
**Baseline before preparation:** `46851dc607172d32473b3755001a0d1d8327f05e`

## Version decision

**RELEASE VERSION DECISION: 1.3.0**

Minor release justified by OTL.0–OTL.6 Complete (additive operator lifecycle) on the TIQ/v1.2.0 baseline. Patch rejected (understates scope). Major rejected (no breaking public contracts; TARGET unchanged).

## Programs

| Program | Status |
|---|---|
| TIQ (TQ.0–TI.7) | **Complete** |
| OTL (OTL.0–OTL.6) | **Complete** |
| TSC | **Not started** |

## Upgrade result

v1.2.0 → v1.3.0 requires **no schema migration** (TARGET remains 7). Safe publication defaults unchanged (gate OFF, mode `manual`). No unintended republish/unpublish sweep. Focused Migrator/PluginGuard/OTL integration smoke **PASS**.

## Deployment

**Not deployed** by this release task. Tag-triggered workflow published the audited ZIP to GitHub Releases only. Site installation remains a separate operator action.

## Known limitations / debt (carried forward)

- Jobs→Operations reverse link Partial
- Bulk retry-failed Deferred
- Jobs-backed attention Deferred
- Path-B QA/assessment duplication Deferred
- Live Playwright local-only
- Mobile-first Deferred
- Selection/bulk-result cross-tab persistence Unsupported
- No durable publish-verification product
- TIQ Deferred/Partial surfaces remain as in v1.2.0 scope (QA detectors, RA14 score, Jobs Outcome B, auto-unpublish, scheduled publication, etc.)

## Exact next roadmap step

Make an explicit product decision on the next post-OTL program from the released v1.3.0 baseline. TSC may be considered as a separate site-neutral candidate, but must not be started implicitly.

## Tag vs closure

This closure docs commit (if any) advances `main` **after** the tagged release commit. The tag **`v1.3.0` remains on `c88ba30681439d9e7113a20d7ebc03c942dd240d`** and is not moved.
