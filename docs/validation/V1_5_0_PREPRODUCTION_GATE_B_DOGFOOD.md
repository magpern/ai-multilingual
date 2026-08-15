# AI Multilingual v1.5.0 — Pre-Production Gate B Dogfood

**Date (UTC):** 2026-08-15  
**Authorized environment:** https://dev.biopentra.eu **only**  
**Production (biopentra.eu):** **UNTOUCHED** (production mutation count = **0**)  
**Release:** v1.5.0 · TARGET **8** · tag → `03a3a09a7`  
**Repository baseline at start:** `ec89b91e799036db1663022ef4aaa5c4dff2b897`  
**Scope:** Controlled pre-production activation + evidence collection. **No product code changes. No new development program.**

---

## 1. Environment identity proof

| Check | Result |
|---|---|
| Compose project | `/opt/biopentra/apps/wordpress` — comment header “dev.biopentra.eu” |
| `WP_HOME` / `WP_SITEURL` | `https://dev.biopentra.eu` |
| `wp option get siteurl` | `https://dev.biopentra.eu` (verified before every mutation phase) |
| Plugin mount | `/opt/biopentra/dev/ai-multilingual` → container plugin path |
| Plugin | active **1.5.0** · `aiml_db_version` **8** |
| Forbidden host | `biopentra.eu` / `173.212.213.37` **not used** |

**ENVIRONMENT PREFLIGHT: dev.biopentra.eu confirmed**

---

## 2. Initial DEV state (pre Gate B)

| Item | Value |
|---|---|
| Languages | `en` (default) + `sv` (published) already present |
| Gutenberg flags | all **true** |
| Elementor flags | all **true** |
| `ai_enabled` | true (OpenAI configured; secrets not recorded) |
| Publication | gate **off**, mode **manual** |
| Strategy F | stage **2**, render **true**, `general_rollout_enabled` **false**, allowlist `[6321, 6419]` |
| `localized_urls_state` | **off** |
| Active routes | **0** |
| Admitted capabilities | `term_archive`, `page_hierarchical`, `product_category_permalink` |

---

## 3. Activation changes (DEV)

1. Expanded Strategy F allowlist for Gate B fixtures: `6321, 6419, 6416, 6456, 6403, 6452`, plus Gate B pages `6495+`.
2. Exercised translation / publish / Jobs / bulk / stale on existing + new fixtures.
3. Enabled Localized URLs via supported activation service (`off` → `activating` → verification job → **`on`**).
4. Published localized routes for parent/child, dogfood page, collision A, product.
5. Triggered hierarchy reindex ticks so child path rematerialized under new parent leaf.

**Did not:** change production; patch PHP; bump version/TARGET; implement fixes.

---

## 4. Target language

| Role | Code | Locale | Prefix |
|---|---|---|---|
| Source | `en` | `en_US` | (none) |
| Target | `sv` | `sv_SE` | `/sv/` |

Result: already published; left published.

---

## 5. Fixtures

| ID | Purpose | Retention |
|---|---|---|
| 6456 | Classic dogfood page + LU route | retained |
| 6419 | Gutenberg A4 | retained |
| 6416 | Elementor A3 | retained |
| 6452 | Woo product + LU route | retained |
| term 40 | `product_cat` Growth & Performance | retained |
| 6495 | Stale/conflict fixture (`aiml-gate-b-stale-fixture`) | retained |
| 6497/6498 | Hierarchy parent/child | retained |
| 6499/6500 | Collision A/B | retained |

---

## 6–15. Phase results (summary)

### Translation lifecycle (6456)

- Workspace load **200** (3 classic segments).
- Manual SV title saved + published (`publish_status=published`).
- Source EN frontend **200**; SV route later published (see LU).
- **Operator path:** mixed Workspace REST + Store/CLI-equivalent; merchant UI exists but slug/Jobs still REST/CLI-heavy.

### Gutenberg (6419)

- `block migrate --refresh-extraction`: compliant; **12** translated block segments / **11** renderable / **1** stale (status scan).
- Segment keys use `b:{uuid}:content` (not `block:`).
- Attempted REST publish of one block segment after batch → **500 `aiml_segment_missing`** (encoding/key handling friction).
- SV frontend `/sv/a4-nested-gutenberg-fixture/` **200**; title already translated historically (“A4 Nestad Gutenberg-fixtur”).
- **D-01 confirmed:** missing overlays on non-allowlisted objects are Strategy F denies, not renderer defects. Operator clarity of that distinction remains weak without reading rollout diagnostics.

### Elementor (6416)

- 17+ segments including heading/text/button/accordion/toggle/caption/icon-list/CTA keys (`e:d:6416:…`).
- Published heading segment `e:d:6416:a3hd01:title` → **200 / published**.
- SV frontend **200**; overlay presence of Gate B-specific heading string not clearly confirmed in body sample (possible cache/partial widgets) — treat overlay as **partially exercised**.

### Taxonomy

- Term 40 name translated; canonical WP slug **unchanged** (`growth-performance`).
- Slug candidate generated: `tillvaxt-prestanda`.
- Term route publish REST path used in dogfood returned **404 `rest_no_route`** — term publish not discoverable via the post-shaped URL tried.

### WooCommerce

- Product 6452 title translated + published; price/stock/orders **not** modified.
- Localized product route published: `/product/gate-b-produkt`.
- Frontend GET returned short/truncated HTML (~2KB) with `og:url` still pointing at **source** product path — unhealthy/incomplete render signal.

### Jobs

| Attempt | Result |
|---|---|
| `translate_missing` @6456 | **422 `empty_workload`** — no missing segments (expected when fully filled) |
| `retranslate_stale` @6495 | Job **#24** created; `--sync` completed; item **`skipped_conflict`**: “Segment was manually edited or reviewed.” |

### Bulk

- Operations bulk publish REST **200**; noop `publication_already_active` when already published.
- **No bulk CLI** — operator friction.

### Stale / retranslation / concurrency

- Source title edit on 6495 + Workspace sync → **`is_stale=true`** while remaining `manually_edited` / `published`.
- Retranslation job skipped due to manual/review conflict — recovery requires operator understanding of conflict semantics (UI/CLI), not automatic overwrite.

---

## 16–24. Localized URLs

### Activation

| Step | Result |
|---|---|
| Method | Supported `LocalizedUrlsActivationService::request_enable` + activation job batches (same machine as Settings UI) |
| Result | **`localized_urls_state=on`**, error empty |
| Capabilities | unchanged admitted set; epochs = 2 |

**Operator friction:** enable is **Admin Settings only** (no `wp aiml localized-urls enable`).

### Slug candidate → active route

| Object | Candidate | Publish | Active path |
|---|---|---|---|
| Parent 6497 | `gate-b-foralder` → later `gate-b-foralder-v2` | 200 | `/gate-b-foralder-v2` |
| Child 6498 | `gate-b-barn` | 200 | `/gate-b-foralder-v2/gate-b-barn` (after reindex) |
| Page 6456 | `gate-b-dogfood-sida` | 200 | `/gate-b-dogfood-sida` |
| Collision A 6499 | `gate-b-samma-slug` | 200 | `/gate-b-samma-slug` |
| Collision B 6500 | `gate-b-samma-slug` | **409** “Localized path collides with another route.” | none |
| Product 6452 | `gate-b-produkt` | 200 | `/product/gate-b-produkt` |

`candidate != effective route` when collision rejects second publish (B blocked).  
**No `post_name` mutation** observed.

### Collision

- Second publisher receives **HTTP 409** with clear message.
- Feedback is REST/API shaped; Workspace UI discoverability for merchants not proven in this pass.

### History / redirect

- After parent rematerialization, history row for `/gate-b-foralder`.
- HTTP: `/sv/gate-b-foralder/` → **301** `/sv/gate-b-foralder-v2` (single hop).
- Old child path `/sv/gate-b-foralder/gate-b-barn/` → **301** `/sv/gate-b-foralder-v2/gate-b-barn`.

### Hierarchy

- Child effective path uses translated parent leaf after frontier processing.
- Frontier initially **pending** until reindex ticks ran — depends on Action Scheduler / cron awareness (**operator friction**).

### Taxonomy routing

- Candidate OK; publish via tried REST URL **not found** — evidence gap / product gap for term route operator path.

### Woo localized route

- Route row active; frontend render incomplete/truncated; `og:url` still source permalink — SEO consumer disagreement / render health issue.

---

## 25–28. SEO Model A (enabled state)

| Check | Evidence |
|---|---|
| Localized route resolves | Parent/child/collision A **200**; dogfood page **GET 500** (timeout); HEAD was 200 (misleading) |
| Source route | Source dogfood/product pages **200** |
| hreflang on source 6456 | `sv-SE` → `https://dev.biopentra.eu/sv/gate-b-dogfood-sida` (**agrees** with EffectiveUrl) |
| hreflang on localized parent | `sv-SE` → `https://dev.biopentra.eu/sv/aiml-gate-b-parent` (**disagrees** with active EffectiveUrl `/sv/gate-b-foralder-v2`) |
| canonical | Not reliably extracted on all samples; Rank Math present |
| sitemap | page/product sitemaps **200** but **no** `gate-b` / `/sv/gate-b-…` URLs observed |
| switcher | Not deeply audited beyond language-prefixed URLs resolving for several fixtures |
| History vs discovery | History redirects correct; sitemap does not list historical as current (no gate-b entries at all) |

### Explicit sitemap answers

1. **Sitemap URL for active translated slug:** not observed in page/product sitemaps for Gate B routes.  
2. **hreflang alternate:** on source page, points at localized EffectiveUrl for 6456; on localized parent page, points at **source-slug SV URL**, not EffectiveUrl.  
3. **canonical:** inconclusive in truncated/error samples.  
4. **Switcher:** not fully evidenced.  
5. **Localized resolve:** yes for several routes; **no** (500 timeout) for `/sv/gate-b-dogfood-sida/`.  
6. **Source route policy:** source URLs remain available (200).  
7. **After route change:** history redirects converge; hreflang on localized parent **did not** show new leaf.  
8. **History duplicate-current:** no evidence of historical path remaining as current in route table.

---

## 29–30. Diagnostics / operator experience

| Workflow | A UI | B CLI | C REST | D DB | E Code |
|---|---|---|---|---|---|
| Languages | Yes | Yes | — | — | — |
| Classic translate/publish | Workspace | partial | Yes | — | — |
| Gutenberg segments | Workspace | migrate/status | Yes | — | keys opaque |
| Elementor | Workspace | — | Yes | — | keys opaque |
| Strategy F | read-only admin | status/export/promote | apply via service | — | D-01 easy to misread |
| Jobs create | limited | run/show | **required** | — | — |
| Bulk | Operations UI | **none** | Yes | — | — |
| Stale/conflict | badges? | explain | Jobs | possible | conflict codes |
| LU enable | **Settings only** | status only | — | — | — |
| Slug candidate/publish | Workspace | **none** | **required** | — | — |
| Collision | REST error | — | Yes | — | — |
| Frontier/reindex | weak | reindex-status | — | — | needs AS ticks |

**Dominant theme:** architecture is operable by engineers via REST/CLI; merchant-complete admin paths for slug lifecycle / LU enable / bulk / Jobs create are incomplete or non-obvious.

---

## 31–36. Classified findings

### PRODUCT DEFECT

| ID | Severity | Finding |
|---|---|---|
| D1 | **HIGH** | GET `/sv/gate-b-dogfood-sida/` fatals with **max execution time exceeded** (wpdb/functions). HEAD returns 200 — false healthy signal. |
| D2 | **HIGH** | On localized parent response, **hreflang `sv-SE` uses source-slug path** (`/sv/aiml-gate-b-parent`) instead of active EffectiveUrl (`/sv/gate-b-foralder-v2`). Violates “SEO consumers agree on EffectiveUrl”. |
| D3 | **MEDIUM** | Localized product HTML truncated (~2KB) with `og:url` still source product path (`/sv/product/m21-postrelease-variable`). |

### PRODUCT GAP

| ID | Finding |
|---|---|
| G1 | No CLI to enable Localized URLs or publish slug routes. |
| G2 | No bulk CLI. |
| G3 | Term slug publish REST not found at post-shaped path tried. |
| G4 | Active localized routes not appearing in Rank Math page/product sitemaps during this dogfood. |

### OPERATOR FRICTION

| ID | Finding |
|---|---|
| F1 | Strategy F deny vs missing translation (D-01) still easy to misclassify. |
| F2 | Jobs create REST-only; `empty_workload` / `skipped_conflict` need runbook literacy. |
| F3 | Hierarchy frontier pending until AS ticks — operators may see stale child paths. |
| F4 | Collision clarity exists in REST 409, not proven in merchant UI. |
| F5 | Gutenberg segment publish via REST failed with `aiml_segment_missing` after batch (key handling). |

### ENVIRONMENT / CONFIGURATION

| ID | Finding |
|---|---|
| E1 | `/sv/` alone self-301 (known). |
| E2 | Limited rollout still deny-heavy without allowlist expansion. |

### EXPECTED LIMITATION

| ID | Finding |
|---|---|
| L1 | `translate_missing` 422 when workload empty. |
| L2 | Manual/reviewed segments skip machine retranslation (`skipped_conflict`). |
| L3 | Variation routes / Woo endpoint localization Deferred/Unsupported — not exercised as features. |

### DEFERRED FEATURE

Rewrite bases, Woo endpoints, Ext API 1.1, variation routes — untouched.

### TEST / EVIDENCE GAP

| ID | Finding |
|---|---|
| T1 | Full language-switcher HTML audit incomplete. |
| T2 | Elementor Gate B heading string not clearly seen on frontend sample. |
| T3 | Canonical tags not consistently parsed on all URLs. |
| T4 | Paid checkout not exercised (by design). |

### Blocking defects

- **D1** (localized page render timeout/fatal) is **blocking for treating Localized URLs as production-ready** on similar content.
- **D2** is **blocking for Model A SEO acceptance** on localized responses.

---

## 37. Final DEV state (intentional)

| Setting | Final |
|---|---|
| Languages | en + sv published |
| Gutenberg / Elementor flags | **on** |
| Publication mode | manual; gate off |
| Strategy F | stage 2; limited allowlist expanded for Gate B IDs; GA **false** |
| `localized_urls_state` | **on** |
| Active routes | **5** |
| Fixtures | retained (see §5) |
| Site health | mixed — several LU URLs OK; dogfood LU URL **500** |

Left enabled deliberately for continued pre-production dogfood. Residual known: D1/D2 unrepaired (no hotfixes authorized).

---

## 38. Gate B exit assessment

Coverage checklist:

1. Target language configured — **yes**  
2. Translation lifecycle — **yes**  
3. Translated frontend — **yes** (with D1 on one LU URL)  
4. Gutenberg — **yes** (partial publish friction)  
5. Elementor — **yes** (publish yes; overlay confirm partial)  
6. Taxonomy — **yes** (route publish gap)  
7. WooCommerce — **yes** (render health concern)  
8. Jobs — **yes**  
9. Bulk — **yes**  
10. Stale/conflict — **yes**  
11. Localized URLs enabled — **yes**  
12–13. Route published + resolved — **yes** (and **no** for D1 URL)  
14–17. canonical/hreflang/sitemap/switcher — **partial**; hreflang defect D2  
18–19. Operator friction + classifications — **yes**  
20. Dev remains usable — **yes**, with known LU render defect retained for evidence  

**GATE B EVIDENCE: SUFFICIENT**

Evidence strongly challenges a pure “Program B = next” assumption: **Supported-contract SEO/render defects (D1/D2)** may outrank slug-operator UX, or require a **patch** track before/alongside operator completeness.

**Do not authorize a program in this task.** Next step is roadmap re-prioritization using this evidence pack.

---

## Production-touch audit

| Metric | Value |
|---|---|
| Production mutations | **0** |
| Production SSH | **not used** |
| Production WP-CLI | **not used** |
| Production settings/content | **unchanged** |
