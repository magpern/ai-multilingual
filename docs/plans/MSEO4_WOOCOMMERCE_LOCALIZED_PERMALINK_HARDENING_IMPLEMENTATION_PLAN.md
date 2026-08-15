# MSEO.4 — WooCommerce Localized Permalink Hardening — Implementation Plan

**Status:** **Architecture Frozen** — authoritative specification for MSEO.4 implementation  
**Milestone:** MSEO.4 — WooCommerce Localized Permalink Hardening  
**Parent:** [MSEO_PARENT_IMPLEMENTATION_PLAN.md](MSEO_PARENT_IMPLEMENTATION_PLAN.md)  
**ADR:** [0023-localized-url-overlay-architecture.md](../adr/0023-localized-url-overlay-architecture.md) (**Accepted**)  
**External review:** **FREEZE** (A1–A8)  
**STATE:** B · **TARGET 8** (no migration) · **Version:** 1.4.0  
**Planning materialization:** docs-only on `main`  
**Implementation branch:** `feature/mseo4-woocommerce-localized-permalink-hardening` (create after freeze push)  
**Baseline:** `main` @ `bcaf5c1e9016ae7dfe400c8e56af976903c6d9f3`  
**Depends on:** MSEO.0–MSEO.3 COMPLETE; ADR-0023 Accepted  

**This document is the authoritative implementation specification for MSEO.4.** Do not start MSEO.5.

---

## 1. Repository baseline

| Item | Value |
|---|---|
| HEAD | `bcaf5c1e9016ae7dfe400c8e56af976903c6d9f3` |
| Version | 1.4.0 |
| TARGET | 8 |
| Plain products | MSEO.2 Supported |
| product_cat archives | MSEO.3 Supported when admitted |
| `%product_cat%` product permalinks | Classified, not supported — this milestone |
| Woo CI | 10.9.4 |

---

## 2. Exact objective

Safely admit localized **product** URLs when Woo product permalink structure includes `%product_cat%`, using Woo `get_permalink()` as absolute source truth, MSEO.3 admitted term routes for real category leaves, bounded `product_dep` / `woo_product_config` maintenance, implemented≠admitted capability epoch, untranslated Woo bases/endpoints, and generation-vs-recognition safety on config change.

---

## 3. External amendments A1–A8 (frozen)

### A1 — No AIML deepest-category clone
Source = `get_permalink($product)`. `WooProductCategoryAuthority` captures Woo’s selected term via temporary observation of `wc_product_post_type_link_product_cat` during permalink generation (try/finally cleanup). Never reconstruct source URLs from structure alone or cloned deepest-cat logic.

### A2 — Over-inclusive dependency discovery
Candidates = products assigned to T or descendants of T. Discovery MUST NOT reproduce Woo selection. Rematerialize decides; unchanged = strict no-op.

### A3 — Distinct global config frontier
`woo_product_config` / `1` (never `product_dep/0`). Mode `all_products` + fingerprint in checkpoint.

### A4 — Route-semantic fingerprint only
Normalized Settings JSON payload of product-permalink-identity inputs only; hash; exclude irrelevant archive bases unless proven to alter product path identity.

### A5 — Generation vs recognition on config change
Fingerprint mismatch → not generatable (EffectiveUrl = source; SEO must not prefer old localized). Old localized routes remain temporarily recognizable → one redirect to EffectiveUrl. No mass 404. No chains. Self-redirect guard when recognized_path == destination.

### A6 — Uncategorized is Woo-owned
Not AIML term route / FORMAT_SLUG / provider content. Preserve Woo source-shape; localize product leaf only without inventing AIML category identity for placeholder.

### A7 — SEO consumer matrix
EffectiveUrl authority. Characterize before hooks. Prefer regression if consumers already use get_permalink/home_url/term_link/relationships.

### A8 — First public = end MSEO4.5; admission model B
Global admit while per-object may fall back. Ordinary fallbacks non-blocking; systemic/config/integrity/system_error block.

---

## 4. Permalink shape matrix

| ID | Shape | Verdict |
|---|---|---|
| A | `/product/{slug}/` plain | Supported (MSEO.2) |
| B | `/shop/{slug}/` plain | Supported (MSEO.2) |
| C | `/shop/%product_cat%/{slug}/` | Supported after MSEO.4 admit |
| D | `/products/{slug}/` custom plain | Supported (MSEO.2) |
| E | `/products/%product_cat%/{slug}/` | Supported after MSEO.4 admit |
| F | Bare `/%product_cat%/{slug}/` | Supported if Woo `get_permalink` emits it |
| G | Translated Woo bases | Unsupported |
| H | Cart/checkout/account endpoints | Unsupported (excluded) |
| I | Variation-specific routes | Unsupported |
| J | Pretty layered-nav translation | Deferred |

---

## 5. Authorities

| Authority | Contract |
|---|---|
| Source path | `get_permalink` only |
| Category | Capture adapter; fail closed on disagreement / nondeterminism |
| Path builder | `WooProductPathBuilder` sole `%product_cat%` concatenation |
| Public gate | `RoutingCapabilityAdmission` + fingerprint match + `CODE_CAPABILITY_EPOCH` bump |

Invariant: AIML MUST NEVER emit a localized product path whose source-shape interpretation disagrees with Woo `get_permalink()`.

---

## 6. Frontier namespaces (TARGET 8)

| Type | Id | Worker |
|---|---|---|
| `post` / `term` | object id | HierarchyReindexJob only |
| `product_dep` | product_cat term_id | WooProductRouteReindexJob only |
| `woo_product_config` | `1` | WooProductRouteReindexJob only |

`find_workable( array $parent_source_types )` required. ≤100 products/tick. Generation root-local.

---

## 7. Per-object outcomes

`synchronized` · `source_fallback_missing_component` · `source_fallback_collision` · `source_fallback_authority_disagreement` · `source_fallback_nondeterministic_filter` · `skipped_not_public` · `skipped_unsupported` · `invalid_data` · `system_error`

---

## 8. SEO consumer matrix (MSEO4.0 must finalize seams)

| Surface | Starting posture |
|---|---|
| Canonical / hreflang / sitemap / home_url | Existing EffectiveUrl path → regression |
| Woo / Rank Math breadcrumbs | Characterize; gap-only seam |
| Schema Product/BreadcrumbList URLs | Characterize; gap-only seam |

---

## 9. Work packages MSEO4.0–MSEO4.6

| WP | Scope | Public? |
|---|---|---|
| MSEO4.0 | Characterization + fingerprint freeze | No |
| MSEO4.1 | Authority + path builder + implemented capability | No |
| MSEO4.2 | Frontier filter + product_dep + woo_product_config + triggers | No |
| MSEO4.3 | Rematerialize / history / collision / config transition | No |
| MSEO4.4 | EffectiveUrl / Router recognition / SEO (gated) | No* |
| MSEO4.5 | Verify + atomic admit + diagnostics — **FIRST PUBLIC** | After admit |
| MSEO4.6 | PluginGuard / perf / browser / evidence | — |

---

## 10. Requirements M4R1–M4R68

| ID | Requirement | Class |
|---|---|---|
| M4R1 | Plain product regression | Supported |
| M4R2 | Shop/custom plain bases | Supported |
| M4R3 | `%product_cat%` shapes Supported after admit | Supported |
| M4R4 | Translated Woo bases | Unsupported |
| M4R5 | Source path from get_permalink | Supported |
| M4R6 | No AIML deepest-cat clone | Supported |
| M4R7 | Capture-adapter category authority | Supported |
| M4R8 | Capture filter try/finally cleanup | Supported |
| M4R9 | Source-shape disagreement fail-closed | Supported |
| M4R10 | Nondeterministic filter fail-closed (object) | Supported |
| M4R11 | WooProductPathBuilder sole concatenation | Supported |
| M4R12 | Category segments from MSEO.3 term routes | Supported |
| M4R13 | Missing component → source_fallback | Supported |
| M4R14 | Uncategorized Woo-owned | Supported |
| M4R15 | product_category_permalink capability id | Supported |
| M4R16 | CODE_CAPABILITY_EPOCH advance per convention | Supported |
| M4R17 | Implemented ≠ admitted | Supported |
| M4R18 | Atomic admit after full verify | Supported |
| M4R19 | Deploy while ON no instant expose | Supported |
| M4R20 | Verify fail does not disable MSEO.2/3 | Supported |
| M4R21 | Route-semantic fingerprint in Settings | Supported |
| M4R22 | Irrelevant settings do not change fingerprint | Supported |
| M4R23 | Fingerprint mismatch suspends generation | Supported |
| M4R24 | Config recognition one-hop redirect | Supported |
| M4R25 | No mass 404 on config change | Supported |
| M4R26 | Self-redirect guard | Supported |
| M4R27 | No redirect chains G1→G2→G3 | Supported |
| M4R28 | HISTORY_MAX=5; idempotent no-op | Supported |
| M4R29 | No candidate mutation on maintenance | Supported |
| M4R30 | No post_name / term.slug mutation | Supported |
| M4R31 | Collision hold + degraded | Supported |
| M4R32 | No auto-suffix on maintenance | Supported |
| M4R33 | Over-inclusive dependency discovery | Supported |
| M4R34 | Rematerialize no-op when unchanged | Supported |
| M4R35 | product_dep / term_id namespace | Supported |
| M4R36 | woo_product_config / 1 singleton | Supported |
| M4R37 | find_workable(types) isolation | Supported |
| M4R38 | Hierarchy job claims only post/term | Supported |
| M4R39 | Woo job claims only product_dep/woo_product_config | Supported |
| M4R40 | ≤100 products/tick | Supported |
| M4R41 | 1k multi-tick category change | Supported |
| M4R42 | 1k multi-tick config change | Supported |
| M4R43 | Same-root generation supersede | Supported |
| M4R44 | Cross-root overlap converges | Supported |
| M4R45 | Assignment / cat / config triggers | Supported |
| M4R46 | Overlap with MSEO.3 hierarchy worker | Supported |
| M4R47 | Variations → parent EffectiveUrl | Supported |
| M4R48 | Shop = existing page behavior | Supported |
| M4R49 | Endpoints excluded | Supported |
| M4R50 | Layered-nav query preserved | Supported |
| M4R51 | Pretty filter translation | Deferred |
| M4R52 | SEO consumer characterization | Supported |
| M4R53 | Canonical/hreflang/sitemap Model A | Supported |
| M4R54 | Admission model B + outcome taxonomy | Supported |
| M4R55 | First public end MSEO4.5 | Supported |
| M4R56 | Diagnostics / admin status | Supported |
| M4R57 | PluginGuard MSEO.4 | Supported |
| M4R58 | No rewrite/flush | Supported |
| M4R59 | get_permalink wins over AIML interpretation | Supported |
| M4R60 | No cloned deepest-cat in production | Supported |
| M4R61 | Capture-adapter category authority | Supported |
| M4R62 | Source-shape disagreement fail-closed | Supported |
| M4R63 | Over-inclusive discovery + rematerialize no-op | Supported |
| M4R64 | woo_product_config / 1 not product_dep/0 | Supported |
| M4R65 | Normalized route-semantic fingerprint | Supported |
| M4R66 | Generation vs recognition on config transition | Supported |
| M4R67 | Woo-owned uncategorized placeholder | Supported |
| M4R68 | SEO consumer matrix | Supported |

**Count: M4R1–M4R68.**

---

## 11. Acceptance M4AC1–M4AC55

| ID | Criterion |
|---|---|
| M4AC1 | TARGET remains 8 |
| M4AC2 | No migration |
| M4AC3 | Version 1.4.0 |
| M4AC4 | Plain product regression green |
| M4AC5 | Supported `%product_cat%` path deterministic via get_permalink |
| M4AC6 | Capture adapter selects same term Woo uses |
| M4AC7 | Capture cleanup on success/failure/Throwable |
| M4AC8 | Forced source-shape disagreement fail-closed |
| M4AC9 | Hierarchical product_cat localized correctly when admitted |
| M4AC10 | Missing/unadmitted component → source_fallback |
| M4AC11 | Maintenance does not mutate candidate |
| M4AC12 | Category change schedules bounded product_dep |
| M4AC13 | No full catalog scan per request |
| M4AC14 | 1k products multi-tick category |
| M4AC15 | 1k products multi-tick config |
| M4AC16 | Overlapping workers converge |
| M4AC17 | Collision holds prior route |
| M4AC18 | History only on actual transition |
| M4AC19 | Permalink setting change invalidates/reverifies |
| M4AC20 | No instant public exposure after deploy |
| M4AC21 | Admission atomic after verification |
| M4AC22 | Variations use parent URL |
| M4AC23 | Cart/checkout/account unchanged |
| M4AC24 | Woo endpoints unchanged |
| M4AC25 | Layered-nav query preserved |
| M4AC26 | Breadcrumb URLs correct per matrix |
| M4AC27 | Schema URLs correct where owned |
| M4AC28 | Canonical correct |
| M4AC29 | Hreflang correct |
| M4AC30 | Sitemap Model A correct |
| M4AC31 | Source-slug redirect one-hop |
| M4AC32 | History redirect one-hop |
| M4AC33 | OFF fallback correct |
| M4AC34 | No post_name mutation |
| M4AC35 | No term.slug mutation |
| M4AC36 | No rewrite rules |
| M4AC37 | No translated bases |
| M4AC38 | Discovery over-inclusive tests 1–4 |
| M4AC39 | Fingerprint irrelevant/relevant/normalized |
| M4AC40 | Config transition recognition without mass 404 |
| M4AC41 | Self-redirect guard |
| M4AC42 | Uncategorized Woo-owned |
| M4AC43 | Per-object outcome taxonomy |
| M4AC44 | Global admit despite one colliding SKU |
| M4AC45 | Frontier cross-namespace isolation |
| M4AC46 | G1/G2/G3 direct-to-current history |
| M4AC47 | Nondeterministic filter fail-closed |
| M4AC48 | HierarchyReindexJob never claims product_dep |
| M4AC49 | Woo job never claims post/term DFS |
| M4AC50 | PluginGuard MSEO.4 green |
| M4AC51 | MSEO.2/3 regression green |
| M4AC52 | Rank Math absent path |
| M4AC53 | Rank Math present path |
| M4AC54 | Browser checklist documented |
| M4AC55 | MSEO.5 not started |

**Count: M4AC1–M4AC55.**

---

## 12. Non-goals / Deferred / Unsupported

- Translated product / product-category / product-tag / shop bases  
- Cart/checkout/account endpoint translation  
- Variation-specific routes  
- Pretty layered-nav URL translation  
- Arbitrary Woo extension URLs  
- Provider slug generation  
- Extension API URL registration  
- Tag / release / deploy  
- MSEO.5+

---

## 13. Schema / ADR verdict

STATE B · TARGET 8 · **no migration** · ADR-0023 **sufficient**.

---

## 14. First public boundary

**End of MSEO4.5** after successful atomic admission of `product_category_permalink`.

---

## 15. STOP

Do not start MSEO.5 in this milestone.
