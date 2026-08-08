# A.7b — Admission matrix

**Frozen for planning.** Implementation may only code **Supported** rows.

## Supported (Woo-owned + deterministic filter + Store-anchor PASS)

| ID | Surface | Owner | Identity (PluginIdentity) | Extract | Overlay | Dynamic class | Disposition |
|---|---|---|---|---|---|---|---|
| B1 | Catalog ordering option labels | Woo | `p:woocommerce:catalog_orderby:{key}:label` where `{key}` ∈ Woo orderby keys | Allowlisted keys from `woocommerce_catalog_orderby` default set (+ `relevance` when search) on **shop-page extract host** | Filter `woocommerce_catalog_orderby` — replace **values** (labels) only; never keys | Static labels | **Supported** |
| B2 | Catalog “sorted by” status labels | Woo | `p:woocommerce:catalog_orderedby:{key}:label` | From `woocommerce_catalog_orderedby` defaults | Filter `woocommerce_catalog_orderedby` | Static labels | **Supported** |

Functional keys never translated: `menu_order`, `popularity`, `rating`, `date`, `price`, `price-desc`, `relevance`.

## Deferred (evidence)

| ID | Surface | Reason |
|---|---|---|
| B3 | Result-count “Showing …” / “Showing all …” templates | Woo-owned but **runtime-interpolated** via `_n` / `_nx` with no official pre-interpolation data filter — defer (no scrape / no gettext hijack) |
| B4 | No-products-found notice string | Woo template `__()` only; no official string data filter | 
| B5 | Pagination Prev / Next / Load More | **Blocksy** owns rendered strings |
| B6 | Elementor shop chrome / taxonomy-filter “All” | **Elementor** |
| B7 | Storefront search / chips / refine bar | **biopentra-storefront** |
| B8 | Loop-card CTAs, strength, load-more, live-search i18n | **biopentra-loop-card** |
| B9 | Woo layered nav / price filter widgets | **Not in use** on live site |
| B10 | Product loop card shell | Elementor loop-item / loop-card |
| B11 | Shop/category/tag titles & descriptions | **A.7a** — do not re-admit |
| B12 | Product attribute names on PDP | **A.7a** |

## Out of A.7b (family)

Cart, checkout, account, emails, gateways, extensions, merchant UI, theme redesign, loop-card redesign.

## Ownership class note

B1/B2 are Woo shared label tables with **stable keys**. They are admitted as Integration API `p:` units with Workspace labels that name **WooCommerce catalog ordering**, Store rows anchored on the shop page **technically** only ([store-resolution-hypothesis.md](store-resolution-hypothesis.md)).
