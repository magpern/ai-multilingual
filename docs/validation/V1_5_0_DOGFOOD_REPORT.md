# AI Multilingual v1.5.0 — Dev Dogfood / Field Validation Report

**Date:** 2026-08-15  
**Environment:** https://dev.biopentra.eu  
**Repository:** magpern/ai-multilingual  
**Scope:** DEV DOGFOOD DEPLOYMENT of published `ai-multilingual-1.5.0.zip` only. **PRODUCTION DEPLOYMENT NOT PERFORMED.**

---

## 1. Published asset identity (Gate C)

| Field | Value |
|---|---|
| GitHub Release | https://github.com/magpern/ai-multilingual/releases/tag/v1.5.0 |
| Artifact | `ai-multilingual-1.5.0.zip` |
| SHA-256 | `cd380eb9513c9eb6b91d6ca67b0efc601fee573eceae6413a46b3d83c6eb89e6` |
| Byte size | 759714 |
| Archive entries | 475 |
| Audit | **PASS** (`bin/audit-zip.sh`) |
| Package version | 1.5.0 |
| Package TARGET | 8 |
| Forbidden paths | none (no tests/docs/node_modules) |

Dogfood source: **published GitHub Release asset only** (not git / feature / local ZIP).

---

## 2. Pre-dogfood

| Check | Result |
|---|---|
| Canonical mount | `/opt/biopentra/dev/ai-multilingual` |
| Repo HEAD | `03a3a09a7ee4e1a0d7624582dcfe07af90ce89d5` |
| Pre version (git mount) | 1.5.0 |
| `aiml_db_version` | 8 |
| HTTP home | 200 |

### Strategy F preflight

| Field | Value |
|---|---|
| `rollout_stage` | 2 |
| `rollout_render_enabled` | true |
| `general_rollout_enabled` | false |
| `allowed_post_ids` | [6321, 6419] |
| `allowed_post_types` | post, page |
| `allowed_language_codes` | sv |

Gutenberg overlay judgments must distinguish product defects from expected rollout denial for non-allowlisted posts.

---

## 3. Dogfood mount

| Item | Value |
|---|---|
| Extract path | `/opt/biopentra/dev/aiml-acceptance/v150/ai-multilingual` |
| Compose mounts | wordpress + wpcli → acceptance extract |
| Proof | plugin tree has **no** `docs/` directory |

### Post-deploy verification

| Check | Result |
|---|---|
| HTTP home | 200 |
| Plugin active | yes |
| Version | **1.5.0** |
| `aiml_db_version` | **8** |
| Migration | none |
| `AIML_VERSION` | 1.5.0 |
| EffectiveUrlService present | yes |
| WooProductPathBuilder present | yes |

### Localized URLs status (CLI)

- `localized_urls_state`: **off** (default)  
- `active_route_count`: 0  
- code/verified capability epoch: **2**  
- admitted: `term_archive`, `page_hierarchical`, `product_category_permalink`

### HTTP matrix

| URL | Result |
|---|---|
| `/` | 200 |
| `/sv/a4-nested-gutenberg-fixture/` | **200** (language prefix works) |
| `/cart/` | 200 |
| `/checkout/` | 302 (expected Woo) |
| `/my-account/` | 200 |
| `/sv/` alone | 301 self-location (environment/front-page friction; not introduced by MSEO.5 Gate A code) |

No AIML activation fatal observed. No blocking Supported-contract defect attributed to the v1.5.0 package for MSEO closeout (default OFF; content `/sv/{slug}/` OK; endpoints reachable).

---

## 4. Mount restoration (mandatory)

| Item | Value |
|---|---|
| Restored mount | `/opt/biopentra/dev/ai-multilingual` |
| Restored HEAD | `03a3a09a7ee4e1a0d7624582dcfe07af90ce89d5` |
| Restored version | **1.5.0** (matches main) |
| `aiml_db_version` | 8 |
| HTTP home | 200 |
| Plugin active | yes |
| Proof | `docs/` present including MSEO5 plan |
| Acceptance ZIP retained | `/opt/biopentra/dev/aiml-acceptance/v150/ai-multilingual-1.5.0.zip` |

**PRODUCTION DEPLOYMENT:** not performed.

---

## 5. Verdict

**DEV DOGFOOD DEPLOYMENT COMPLETE** — published asset identity verified; site healthy; mount restored.
