# AI Multilingual v1.5.1 — DEV Runtime Re-Acceptance

**Date (UTC):** 2026-08-15  
**Authorized environment:** https://dev.biopentra.eu **only**  
**Production (biopentra.eu):** **UNTOUCHED** (production mutation count = **0**)  
**Release:** v1.5.1 · TARGET **8** · tag → `6298df08b3b1456e4875ecdb860b71506d5ae313`  
**Repository HEAD during run:** `caa0a9fa13769b34a92ebd0c7c49d67eb24ef587`  
**Scope:** Runtime correctness verification of Gate B defect families D1–D3. **No ZIP install. No mount swap. No product code changes.**

---

## 1. Why no physical DEV ZIP installation

`dev.biopentra.eu` bind-mounts the canonical development repository:

`/opt/biopentra/dev/ai-multilingual` → `/var/www/html/wp-content/plugins/ai-multilingual`

The published GitHub Release artifact was already independently verified at release publication:

| Field | Value |
|---|---|
| Asset | `ai-multilingual-1.5.1.zip` |
| SHA-256 | `6e88a679ddadec0ec371e28ab2209b008ba13a9511ac4832a5de82bd56d739c7` |
| Evidence | [V1_5_1_RELEASE_CLOSURE.md](../releases/V1_5_1_RELEASE_CLOSURE.md) |

V151AC22 is therefore satisfied by:

1. independently verified published v1.5.1 GitHub Release artifact identity; and  
2. DEV runtime verification of the corresponding corrective code on the bind-mounted repository.

---

## 2. Repository / tag preflight

| Check | Result |
|---|---|
| `main` == `origin/main` | Yes @ `caa0a9fa13769b34a92ebd0c7c49d67eb24ef587` |
| Working tree | Clean at start |
| Plugin version | **1.5.1** |
| `Migrator::TARGET` | **8** |
| Tag `v1.5.1` | Annotated → `6298df08b3b1456e4875ecdb860b71506d5ae313` |
| Drift after tag | Docs-only only (`1e8718bf7`, `caa0a9fa1`) — **no production-code divergence** |

**DEV code-identity verdict:** PASS — DEV runs repository 1.5.1 corrective implementation (includes `OutboundLocalizationSuspender`, Router re-entrancy fix, SEO EffectiveUrl agreement).

---

## 3. DEV environment identity

| Check | Result |
|---|---|
| Compose project | `/opt/biopentra/apps/wordpress` — “dev.biopentra.eu” |
| `WP_HOME` / `WP_SITEURL` | `https://dev.biopentra.eu` |
| `wp option get siteurl` | `https://dev.biopentra.eu` |
| Plugin mount | `/opt/biopentra/dev/ai-multilingual` |
| Plugin | **active** **1.5.1** |
| `aiml_db_version` | **8** |
| Corrective markers | `OutboundLocalizationSuspender.php` present; Router uses suspender/re-entrancy |
| Forbidden host | `biopentra.eu` **not used** |

**ENVIRONMENT PREFLIGHT: PASS**

---

## 4. Current DEV configuration (unchanged from Gate B intent)

| Item | Value |
|---|---|
| Source language | `en` / `en_US` (default) |
| Target language | `sv` / `sv_SE` (published) |
| Gutenberg flags | registration / UUID / extraction / frontend rendering **true** |
| Elementor flags | extraction / frontend rendering **true** |
| Publication | gate **off**, mode **manual** |
| Strategy F | stage **2**, `rollout_render_enabled` **true**, `general_rollout_enabled` **false**, allowlist includes Gate B IDs (`6456`, `6497`, `6498`, `6452`, …) |
| `localized_urls_state` | **on** |
| Active routes | **5** |
| Admitted capabilities | `term_archive`, `page_hierarchical`, `product_category_permalink` |

Active routes retained:

| route_id | source_id | source_path | localized_path |
|---|---|---|---|
| 1 | 6497 | `/aiml-gate-b-parent` | `/gate-b-foralder-v2` |
| 2 | 6498 | `/aiml-gate-b-parent/aiml-gate-b-child` | `/gate-b-foralder-v2/gate-b-barn` |
| 3 | 6456 | `/aiml-v1-4-0-dogfood-acceptance` | `/gate-b-dogfood-sida` |
| 4 | 6499 | `/aiml-gate-b-coll-a` | `/gate-b-samma-slug` |
| 5 | 6452 | `/product/m21-postrelease-variable` | `/product/gate-b-produkt` |

Secrets (API keys) were present in settings options and are **not recorded**.

---

## 5. Defect family results

### D1 — Localized GET timeout / HTTP 500

| | Before (Gate B) | After (this run) |
|---|---|---|
| URL | `GET /sv/gate-b-dogfood-sida/` | same |
| Status | timeout / **500** (max execution) | **200** |
| Size | truncated/error | **138723** bytes |
| Elapsed | ≥30s fatal | **~1.84s** |
| HTML | incomplete / fatal | complete (`</html>` present); title `GateB Dogfood Sida` |
| Logs this run | max-exec fatals | **no** new max-exec / AIML fatals for this URL |

**D1: PASS**

### D2 — hreflang ≠ EffectiveUrl (localized parent)

| | Before (Gate B) | After (this run) |
|---|---|---|
| Source URL | `/aiml-gate-b-parent/` | same (**200**) |
| EffectiveUrl (SV) | `/sv/gate-b-foralder-v2` | same (active route) |
| Localized GET | `/sv/gate-b-foralder-v2/` **200** | **200**, 136859 bytes |
| hreflang `sv-SE` (localized page) | `…/sv/aiml-gate-b-parent` (**wrong**) | `https://dev.biopentra.eu/sv/gate-b-foralder-v2` |
| hreflang `en-US` | source parent | `https://dev.biopentra.eu/aiml-gate-b-parent` |
| x-default | source parent | `https://dev.biopentra.eu/aiml-gate-b-parent` |

PASS condition: target-language hreflang == target-language EffectiveUrl → **met**.

**D2: PASS**

### D3a — Woo og:url used source identity

| | Before (Gate B) | After (this run) |
|---|---|---|
| Localized product | `/sv/product/gate-b-produkt/` | same |
| EffectiveUrl | `/sv/product/gate-b-produkt` | active route confirmed |
| `og:url` | source `/sv/product/m21-postrelease-variable` | `https://dev.biopentra.eu/sv/product/gate-b-produkt` |
| Discoverability | route active | route active |

**D3a: PASS**

### D3b — Woo localized HTML truncated

| | Before (Gate B) | After (this run) |
|---|---|---|
| Localized GET | ~2KB truncated | **200**, **141209** bytes, `</html>` present |
| Markers | incomplete | `single-product`, gallery, add-to-cart, title `GateB Produkt Rutt` |
| Source PDP | 200 | **200**, 141255 bytes (comparable health) |
| AIML timeout/fatal | associated with render health failure | **none** on this run |

**D3b: PASS**

---

## 6. Surrounding regression checks

| Check | Result |
|---|---|
| Source dogfood page | `/aiml-v1-4-0-dogfood-acceptance/` → **200** |
| Source parent / child paths | parent **200**; child path available via hierarchy |
| Source product | `/product/m21-postrelease-variable/` → **200** |
| History parent | `/sv/gate-b-foralder/` → **301** `…/sv/gate-b-foralder-v2` (`x-redirect-by: AIML`, one hop) |
| History child | `/sv/gate-b-foralder/gate-b-barn/` → **301** `…/sv/gate-b-foralder-v2/gate-b-barn` (one hop) |
| Hierarchy | localized parent + child **200**; child path uses translated parent leaf |
| Canonical | Rank Math `link rel=canonical` not observed in sampled frontend HTML; `og:url` agrees with EffectiveUrl on localized fixtures |
| Hreflang | agrees with EffectiveUrl on D1/D2/D3 fixtures (source + localized) |
| X-default | points at source-language URL (existing policy) |
| Switcher | `[aiml_switcher]` for parent 6497 emits SV → `https://dev.biopentra.eu/sv/gate-b-foralder-v2` (**== EffectiveUrl**) |
| Sitemap Model A | No localized primary `<loc>` for `/sv/…` observed (correct Model A). Gate B fixtures still absent from Rank Math page/product sitemaps; **no** `xhtml:link` alternates observed site-wide — **pre-existing G4 / evidence gap**, not a new D1–D3 regression |
| Strategy F | Limited rollout still deny-heavy off-allowlist (`deny:post_not_allowlisted` counters). Expected denials — not a v1.5.1 defect |
| Localized URLs OFF | Not re-toggled. Inertness covered by automated suite (e.g. `V151ModelAConsumerTest` LU-off path) + release evidence |

---

## 7. Log / error audit

| Observation | Classification |
|---|---|
| Max-execution fatals at **16:48–16:51Z** (Gate B window) for dogfood LU URLs | **PRE-EXISTING** (before 1.5.1 correction) |
| Re-acceptance GETs **18:17–18:18Z** for D1–D3 URLs | **200/301 only**; no new AIML fatals |
| No redirect loops | OK |
| No recurring term_link failure in this window | OK |

---

## 8. Remaining items (not blocking V151AC22)

### Remaining product gaps / friction (from Gate B; not re-opened as 1.5.1 blockers)

- G1–G3: CLI/REST operator paths for LU enable / term publish / bulk  
- G4: Rank Math sitemap still does not list Gate B fixtures / no XHTML alternates observed  
- F1–F5: Strategy F clarity, Jobs/REST friction, hierarchy frontier timing, collision UI, Gutenberg key handling  

### Evidence gaps

- Theme-placed language switcher widget not present on sampled templates; switcher proven via `[aiml_switcher]` shortcode authority  
- `link rel=canonical` not consistently present in frontend HTML samples (Rank Math / theme dependent)  

### Environment / config

- Strategy F remain limited (intentional Gate B state)  
- LU left **on** with fixtures retained  

---

## 9. Acceptance decision

Mandatory corrective conditions:

- D1 PASS  
- D2 PASS  
- D3a PASS  
- D3b PASS  
- Source routes healthy  
- History one-hop; no loop/chain  
- Hierarchy correct  
- Switcher agrees with EffectiveUrl where exercised  
- Model A coherent (no localized primary locs); sitemap XHTML for fixtures remains pre-existing gap  
- No new blocking AIML error  

**V151AC22: PASS**

**V1.5.1 corrective lifecycle: COMPLETE**

---

## 10. Production-touch audit

| Metric | Value |
|---|---|
| Production mutations | **0** |
| Production SSH | not used |
| Production WP-CLI | not used |
| DEV ZIP install | **not performed** |
| Mount swap | **not performed** |

---

## 11. Exact next step

**FRESH POST-v1.5.1 ROADMAP PRIORITIZATION** using Gate B evidence, corrective implementation evidence, and this runtime re-acceptance.

Do **not** start Program B automatically.
