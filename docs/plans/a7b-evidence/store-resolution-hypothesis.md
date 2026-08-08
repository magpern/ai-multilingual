# A.7b — Store-resolution hypothesis

**Question:** May the A.7a shop-page Store host be reused as a **technical Store anchor** for admitted Woo archive chrome across shop / category / tag / search — without becoming the canonical content owner?

**Canonical owner of admitted strings:** WooCommerce (plugin-owned archive chrome).  
**Shop page role (if any):** Store `source_id` anchor only — never described as content owner.

## Current bridge behavior ([IntegrationFrontendBridge.php](../../../src/Integration/IntegrationFrontendBridge.php))

| Request context | `get_queried_object()` | `resolve_source_id` today |
|---|---|---|
| Shop page (Elementor page 3755) | `WP_Post` shop | Shop post ID |
| `product_cat` / `product_tag` archive | `WP_Term` | `wc_get_page_id( 'shop' )` |
| Product search | Often **not** a post/term | **`0` → overlay hooks not registered** |
| Single product | `WP_Post` product | Product ID (wrong host for shared archive chrome) |

Shop ID is obtained via **`wc_get_page_id( 'shop' )`** in the term branch — not a hardcoded constant in production resolution.

## Evidence checklist (per candidate)

| Requirement | Verdict for catalog orderby labels | Notes |
|---|---|---|
| Deterministic lookup on shop | **Pass** (queried post = shop) | Only if Woo orderby UI is present; Elementor shop currently does **not** render classic orderby |
| Deterministic lookup on category/tag | **Pass** with existing term→shop host mapping | Same technical anchor as A.7a C3–C6 |
| Deterministic lookup on product search | **Gap today** | Bridge returns 0; A.7b must **extend bridge context selection** to map Woo product-search (and any Woo archive with null queried object) → `wc_get_page_id( 'shop' )`. This is **not** a new source type / schema / Integration API v1 interface change |
| No duplicate rows per archive | **Pass** | One Store row per `p:woocommerce:catalog_orderby:{key}:label` under shop `source_id` |
| No false ownership attribution | **Pass if gated** | Workspace `field_label` / `parent_context` / `ownership_class` must identify **WooCommerce archive chrome**, never “Shop page content”. Shop page is Store anchor only |
| No dependence on accidental host page | **Pass** | Production resolve must use `wc_get_page_id( 'shop' )` only |
| Stable if Woo shop page setting changes | **Pass with lifecycle** | New shop page ID becomes anchor; extract/sync must target current shop page; no ownership claim on the old page |
| No Store/schema/API redesign | **Pass** | Remains `SOURCE_POST` + existing `Store::get` |

## Rejected approaches

| Approach | Why rejected |
|---|---|
| New `SOURCE_TERM` / global source type | Store redesign — forbidden |
| Duplicate orderby rows onto every category/product | False multiplicity; ownership noise |
| Invent site-global Store ownership class as schema | Store redesign |
| Hardcode shop post ID `3755` | Brittle; fails shop-page change |
| Treat shop page as canonical content owner of “Sort by price” | False ownership |

## Candidate outcomes

| Candidate | Store-resolution | Disposition |
|---|---|---|
| `woocommerce_catalog_orderby` option labels | Technical shop-page anchor **PASS** (with search bridge extension in A7B.4) | **Supported** (if ownership/hooks PASS) |
| `woocommerce_catalog_orderedby` status labels | Same | **Supported** |
| Result-count “Showing …” templates | N/A — fails dynamic-text / no pre-interpolation data filter | **Deferred** (architecture gap: gettext/template-only) |
| `no-products-found` message | N/A — no official string data filter before template `__()` | **Deferred** |
| Blocksy / Elementor / storefront / loop-card | N/A | **Deferred** (wrong owner) |

## Verdict

**Reuse of the A.7a shop-page Store host as a technical Store anchor is ALLOWED** for admitted Woo catalog-orderby label units, provided:

1. Workspace metadata never attributes those units as shop-page document content.  
2. Resolution always uses `wc_get_page_id( 'shop' )`.  
3. Product-search context is taught to the frontend bridge as the same technical anchor (implementation WP — not a Store redesign).  
4. Candidates that would need a new source type, shared-definition **Store** model, or per-archive duplication remain **Deferred**.

**No Store redesign in A.7b.**
