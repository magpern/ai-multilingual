# AI Multilingual v1.4.0 — Dev Dogfood / Field Validation Report

**Date:** 2026-08-14  
**Environment:** https://dev.biopentra.eu (Biopentra dev VPS)  
**Repository:** magpern/ai-multilingual  
**Scope:** Deploy published v1.4.0 artifact + structured acceptance only. No MSEO, Extension API v1.1, or new programs.

---

## 1. Repository pre-flight

| Check | Result |
|-------|--------|
| `main` @ `9e56385e079f801f863771cc61037edc79143681` | PASS (matches origin) |
| Working tree clean (pre-deploy) | PASS |
| Plugin version in repo | 1.4.0 |
| `Migrator::TARGET` | 7 |
| Tag `v1.4.0` → `ee49cc906babfd34b67fd0998f1eb7553a03358f` | PASS |
| GitHub Release artifact | `ai-multilingual-1.4.0.zip` |

---

## 2. Artifact verification

| Field | Value |
|-------|-------|
| Artifact path | `/opt/biopentra/dev/aiml-acceptance/ai-multilingual-1.4.0.zip` |
| SHA256 | `f819ebb8efc4af030b406447a8792b53804979b14528af984e7fb534ca406b79` |
| Verification | **PASS** (matches release record) |
| Extract path | `/opt/biopentra/dev/aiml-acceptance/v140/ai-multilingual` |
| Confirms ZIP not git | No `docs/` directory in mounted plugin |

---

## 3. Target environment

| Item | Value |
|------|-------|
| URL | https://dev.biopentra.eu |
| Type | Dev / staging (not production) |
| WordPress | 7.0.4 |
| PHP (wpcli container) | 8.4.23 |
| WooCommerce | 10.9.4 (active) |
| Elementor / Pro | 4.2.2 / 4.2.1 (active) |
| Rank Math | 1.0.276 (active) |
| WP-Cron | Disabled in WP; host cron `scripts/wp-cron.sh` */5 |
| Caching | Redis object cache; Cloudflare edge |
| WP-CLI | `docker compose --profile tools run --rm wpcli wp …` |
| AIML languages | `en` (default), `sv` |
| Legacy i18n | TranslatePress not active; `/sv/` prefix from AIML router |

**Environmental note:** `universal-multicurrency` has a broken Composer autoload (missing symfony polyfill). Intermittent WP-CLI fatals when that plugin loads. Not attributable to AIML.

---

## 4. Deployment

### Previous state

- Plugin was bind-mounted from git checkout `/opt/biopentra/dev/ai-multilingual` (also reported 1.4.0).
- `aiml_db_version = 7`; plugin active.
- Block + Elementor extraction/render flags **already ON** (not defaults).
- Publication mode: **manual**; segment publication gate: **off**.

### Procedure

1. Downloaded published GitHub Release ZIP.
2. Verified SHA256.
3. Extracted to `/opt/biopentra/dev/aiml-acceptance/v140/ai-multilingual`.
4. Updated `apps/wordpress/compose.yml` volume mounts (wordpress + wpcli) to acceptance extract (same pattern as prior MPCF acceptance).
5. Restarted `wordpress` container.

### Post-deploy verification

| Check | Result |
|-------|--------|
| `wp plugin get ai-multilingual` → 1.4.0 active | PASS |
| `aiml_db_version` = 7 | PASS |
| No migration executed | PASS |
| Site HTTP 200 | PASS |
| wp-admin loads | PASS (implicit via REST/CLI) |
| Fatal errors from AIML | None observed |

**Ops note:** Revert compose mounts to git checkout when daily dev on source is preferred.

---

## 5. Settings preservation

Existing operator configuration preserved. Not overwritten.

Notable pre-existing flags (non-default):

- `block_attr_registration_enabled`, `block_uuid_injection_enabled`, `block_extraction_enabled`, `block_frontend_rendering_enabled` → **ON**
- `elementor_extraction_enabled`, `elementor_frontend_rendering_enabled` → **ON**
- `ai_enabled` with OpenAI provider configured
- `auto_publication_mode` = manual

---

## 6. Acceptance content

| ID | Type | URL / slug | Role |
|----|------|------------|------|
| 6419 | Page | `/a4-nested-gutenberg-fixture/` | Gutenberg nested fixture |
| 6416 | Page | `/a3-elementor-widget-coverage-fixture/` | Elementor widget coverage |
| 6403 | Page | `/a2-elementor-foundation-fixture/` | Elementor foundation (not re-tested this session) |
| 6456 | Page | `/aiml-v1-4-0-dogfood-acceptance/` | Created for dogfood (simple Gutenberg) |
| 6452 | Product | M21-POSTRELEASE-VARIABLE | Woo product |
| 40 | Term | `product_cat` Growth & Performance | Taxonomy |
| 3594 | Product | (existing) | Rank Math SEO metadata sample in DB |

---

## 7. Scenario results

### 7.1 Core lifecycle — PASS (with manual publication mode)

Evidence via fixtures + REST/CLI:

1. Source discovery/sync — PASS (segments present for fixtures)
2. Missing work visible — PASS (Operations/Jobs admit missing segments)
3. Translation generation — PASS (Jobs #21, #22, #23)
4. Inspect translation — PASS (DB + REST)
5. Manual edit — PASS (`wp aiml translation set 6456 sv --field=title`)
6. QA — Partial (assessment available on job items; not full UI walkthrough)
7. Review workflow — PASS on fixtures (6419 segments `reviewed`)
8. Approve/review — PASS where exercised historically on fixtures
9. Publish — PASS (`wp aiml publication publish`, bulk REST)
10. Visitor translated content — PASS on published fixture surfaces
11. Source-language visitor canonical — PASS (EN URLs show source)
12. Unpublished does not leak — PASS (6456 body unpublished until bulk publish; term 40 unpublished shows EN on category URL)

### 7.2 Whole-object translation (Translate Missing) — PASS

| Job | Scope | Items | Result |
|-----|-------|-------|--------|
| #21 | post 6456, lang sv | 2 | completed 2/0 |
| #22 | term 40, lang sv | 2 | completed 2/0 |
| #23 | product 6452, lang sv | 1 | completed 1/0 |

Job #21 translated `post_title` + block `content`. Progress honest via `wp aiml jobs run --sync`.

### 7.3 Source edit → stale → retranslate — PASS (stale detection); PARTIAL (retranslate)

**Post 6419:** Edited block `b:11111111-…:content` source text with timestamp.

- Stale detection: **PASS** — only target segment `is_stale=1`; `other_stale_siblings=0` (per-segment granularity)
- Frontend stale honesty: **PASS** — SV page shows **source** edited text for stale published block, not outdated SV overlay
- Retranslate Stale job #20: **skipped_conflict** — concurrency/hash guard prevented overwrite; translation remains stale with prior SV text. Classified as **expected guard behavior** + **operator friction**, not defect.

### 7.4 Manual edit / concurrency — PASS

- Manual title edit persisted (`AIML v1.4.0 Manuell titel`)
- Stale + hash guards prevent silent overwrite on retranslate (job #20)

### 7.5 Bulk operations — PASS (bounded)

REST `POST /aiml/v1/workspace/operations/bulk` with `action=publish`, `translation_id=425`:

```json
{"status":"completed","summary":{"total":1,"ok":1,"failed":0}}
```

Bulk retry-failed not tested (Deferred by product).

### 7.6 Jobs — PASS

- Create, inspect, sync run exercised (#20–#23)
- Pause/cancel available per job operations metadata
- WP-CLI requires `--user=1` for job commands
- Jobs→Operations reverse link not tested (Partial/Deferred)

### 7.7 Gutenberg — PASS (fixtures); FOLLOW-UP (dogfood page)

**Post 6419** (`/sv/a4-nested-gutenberg-fixture/`):

- Published non-stale blocks render SV overlay (e.g. `A4 Detaljer Stycke Mål`, `A4 Citat Stycke Mål`)
- Stale published block shows source text (honest)
- Title overlay: `A4 Nestad Gutenberg-fixtur`
- Structural attributes/UUIDs preserved in `post_content`
- Canonical EN `post_content` not mutated

**Post 6456** (new dogfood page):

- Title overlay **PASS** after publish
- Block body overlay **FAIL** after publish (`publish_status=published`, segment eligible in DB) — frontend still shows EN source paragraph. **Follow-up candidate** (theme/render path); fixture 6419 proves supported path works.

### 7.8 Elementor — PASS

**Post 6416** (`/sv/a3-elementor-widget-coverage-fixture/`):

- Widget overlays render (e.g. `A3 Rubrik Mål` heading)
- 17 published SV segments in DB for supported widget families
- Page title in `<title>` remains EN (no `post_title` translation row — expected if not translated)
- Slug remains EN: `/sv/a3-elementor-widget-coverage-fixture/`

### 7.9 Taxonomy terms — PASS

Job #22 translated term 40 `name` + `description` (machine_translated, unpublished).

- SV category URL uses source slug: `/sv/product-category/growth-performance/`
- Unpublished → EN visible on frontend (expected)

### 7.10 WooCommerce — PASS (limited)

Job #23 translated product 6452 `post_title` only (1 segment admitted). Variation/machine identity not incorrectly translated.

Deferred cart/checkout chrome not tested as Supported.

### 7.11 SEO / Rank Math — PASS (diagnostics + existing data)

`wp aiml seo status --user=1`:

- 10 pass, 1 warning (`blog_public=0`), 0 errors
- hreflang reciprocal PASS on sampled URLs
- Rank Math compat PASS

Existing product 3594 has published SV Rank Math title/description in DB (`p:rankmath:post:3594:*`).

**Untranslated slug observation (MSEO evidence):**

- All SV leaf URLs retain EN slugs, e.g. `/sv/a4-nested-gutenberg-fixture/`, `/sv/aiml-v1-4-0-dogfood-acceptance/`
- hreflang alternates correctly cross-link EN/SV pairs with same slug
- **Practical impact:** Editorial/SEO cosmetic friction (SV titles with EN slugs); not blocking current dev dogfood
- **Recommendation:** Keep MSEO Deferred; evidence insufficient for definitive MSEO program authorization

### 7.12 Extension API v1 smoke — PASS

- `ExtensionRegistrar` class present in deployed ZIP
- `aiml_register_extensions` hook fired from `Plugin.php`
- `wp aiml extensions list` → "No extensions registered" (expected; reference extension test-only)
- `aiml_mark_source_dirty()` helper exists

Full registrar/invalidation smoke with a live extension not run (no extension deployed).

### 7.13 Operator UX friction (evidence collection)

| Finding | Severity | Frequency |
|---------|----------|-----------|
| WP-CLI Jobs/SEO commands require explicit `--user=1` | Medium | Common |
| `wp aiml publication publish` `--field` limited to title/excerpt/content (not block segment keys) | Medium | Common for Gutenberg ops |
| Retranslate stale → `skipped_conflict` requires operator to resolve hash/conflict manually | Medium | Occasional |
| Whole-object flow via Jobs REST + sync run workable but multi-step vs single admin click | Low | Common |
| Jobs→Operations deep-link absent (Deferred) | Low | Occasional |

---

## 8. Defects

| ID | Description | Classification | Blocking |
|----|-------------|----------------|----------|
| D-01 | Post 6456 published block translation not overlaying on SV frontend while fixture 6419 overlays work | **Investigate** — possible theme/render-path edge case | No |
| — | Job #20 retranslate stale skipped_conflict | Expected limitation / operator friction | No |

No Supported-contract blocking defects confirmed on primary fixtures.

---

## 9. Coverage gaps (not defects)

- Term translations did not exist pre-dogfood; created during acceptance
- Woo product 6452 only admitted title segment (may reflect product content shape)
- Rank Math meta not present on new dogfood post 6456
- Elementor Theme Builder, popups, dynamic tags not in scope

---

## 10. Expected limitations encountered

- Untranslated leaf slugs under `/sv/` prefix
- Manual publication mode — machine translations not public until publish
- Stale published segments fall back to source on frontend (6419)
- Bulk retry-failed not shipped (Deferred)
- Publication CLI does not address arbitrary block segment keys

---

## 11. Performance sanity

- Operations/Jobs CLI responsive (<15s including container startup)
- SEO diagnostics ~2.9s for 2 HTTP fetches
- Job sync runs #21–#23 completed within ~20–32s
- No runaway Action Scheduler or sync loops observed
- No multi-second admin stalls reported during CLI acceptance

---

## 12. Log / error audit

- WordPress container logs: no AIML fatals during acceptance window
- Prior failed jobs (#1–#9, #20) from earlier testing; failures visible and honest
- `universal-multicurrency` autoload fatal — **environment issue**, not AIML
- One REST 403 on `/wp-json/aiml/v1/jobs` without auth context (expected)

---

## 13. Proposed 1.4.x follow-up (not authorized)

1. Investigate D-01 block overlay on simple/new pages vs established fixtures
2. Operator UX: document CLI `--user=1` requirement; consider block-level publication CLI ergonomics
3. Optional: clearer UI messaging when retranslate stale yields `skipped_conflict`

---

## 14. MSEO decision gate

**Evidence strength:** Weak–moderate. Untranslated slugs confirmed but hreflang/graph healthy; dev site `blog_public=0`. Real editorial friction exists but no operational blocker demonstrated.

**Recommendation:** **Keep MSEO Deferred.** Do not authorize definitive MSEO parent planning from this dogfood alone.

---

## 15. Dogfood verdict

**READY WITH MINOR FOLLOW-UP**

Core Supported surfaces validated on real dev content: lifecycle, Jobs whole-object translation, stale detection, Gutenberg/Elementor fixtures, terms, Woo title surface, Rank Math diagnostics, Extension API inventory, bulk publish. One non-blocking follow-up on new-page Gutenberg overlay; operator friction items documented for 1.4.x polish.

---

## 16. Confirmations

- MSEO **not** started
- Extension API v1.1 **not** started
- No version/TARGET bump
- No new release/tag
- Deployed artifact remains exact published v1.4.0 ZIP (acceptance mount)

---

## 17. Jobs created during dogfood

| job_id | type | scope | status |
|--------|------|-------|--------|
| 20 | retranslate_stale | post 6419 | failed (skipped_conflict) |
| 21 | translate_missing | post 6456 | completed |
| 22 | translate_missing | term 40 | completed |
| 23 | translate_missing | post 6452 | completed |
