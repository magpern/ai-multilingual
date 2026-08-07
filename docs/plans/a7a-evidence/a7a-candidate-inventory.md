# A.7a — Candidate inventory + admission shells

**Milestone:** A.7a  
**Work package:** A7A.1  
**Evidence date:** 2026-08-07  
**Live WooCommerce:** 10.9.4  
**Validation log:** [A7A_WOOCOMMERCE_PRODUCT_CATALOG_VALIDATION_LOG.md](../A7A_WOOCOMMERCE_PRODUCT_CATALOG_VALIDATION_LOG.md)

Disposition in this WP is a **stub**; A7A.2 freezes identity; A7A.3+ may only code **Supported**.

---

## Inventory summary

| # | Candidate | Owner | Ownership class | Stub disposition | Notes |
|---|---|---|---|---|---|
| P1 | Product title | WP (`post_title` on `product`) | document (existing post field) | **Supported** (reuse post pipeline) | Admit `product` in Workspace |
| P2 | Product short description | WP (`post_excerpt`; Woo short desc) | document (existing excerpt) | **Supported** (reuse post pipeline) | Live meta `_product_short_description` empty |
| P3 | Product long description | WP (`post_content` classic) | document (existing content / `b:` / `e:` if present) | **Supported** (reuse post pipeline) | Live products classic; no double-key under Woo |
| P4 | Product tabs (visitor) | Woo (tab titles i18n; body reuses P3/attrs) | shared-definition (titles) / document (desc body) | **Deferred** | Tab titles are Woo `__()` strings; body covered by P3 |
| P5 | Attribute **names** | Woo product attribute label + slug | record | **Supported** (`p:`) | Live: custom attr `Strength` / slug `strength` |
| P6 | Attribute **values** | Woo options or taxonomy terms | record when term_id; else unsupported | **Deferred** (custom options); taxonomy path allowlisted if term_id | Live values `10mg`/`20mg` lack stable IDs — no source-in-identity |
| P7 | Variation attribute **names** | Woo variation attribute label | record | **Supported** (`p:`) | Distinct field from P5 |
| P8 | Variation attribute **values** | Woo variation options / terms | record when term_id; else unsupported | **Deferred** (custom options); taxonomy path if term_id | Same stability constraint as P6 |
| P9 | Woo notices (product page) | Woo i18n notices | shared-definition | **Deferred** | No stable per-product notice record keys proven |
| P10 | Woo-owned breadcrumbs | Theme / Blocksy (observed) | theme / unclear Woo ownership | **Deferred** | Ownership not deterministic as Woo-only |
| C1 | Shop archive title | WP shop page `post_title` (3755) | document (existing page title) | **Supported** (reuse post pipeline) | Shop page already Workspace-eligible as `page` |
| C2 | Shop archive description | WP shop page `post_content` when non-empty | document (existing page content) | **Supported** (reuse post pipeline; empty → no unit) | Live content length 0 |
| C3 | Category title | WP term name `product_cat` | record via `p:` (no term Store) | **Supported** (`p:` on shop page host) | Canonical extract host = shop page 3755 |
| C4 | Category description | WP term description `product_cat` | record via `p:` | **Supported** (`p:`; HTML format) | |
| C5 | Tag title | WP term name `product_tag` | record via `p:` | **Supported** (`p:` on shop page host) | |
| C6 | Tag description | WP term description `product_tag` | record via `p:` | **Supported** (`p:`; HTML; empty → skip) | Live tags often empty desc |

---

## Mandatory attribute split shells

### P5 — Attribute names

| Field | Content |
|---|---|
| Owner | WooCommerce product attribute label |
| Ownership class | `record` |
| Identity | TBD A7A.2 — expected `p:woocommerce:product:{product_id}:attribute_name:{attr_slug}` |
| Extraction | `WC_Product::get_attributes()` → label/slug allowlist |
| Overlay | `woocommerce_attribute_label` |
| Lifecycle | attr slug rename → new identity; no fuzzy rematch |
| Sanitization | plain |
| Diagnostics | Integration counters |
| Platform | Store via product Workspace |
| Browser | EN/SV product 3594 |
| Disposition | **Supported** (stub) |

### P6 — Attribute values

| Field | Content |
|---|---|
| Owner | WooCommerce attribute options / terms |
| Ownership class | `record` when taxonomy `term_id`; otherwise unsupported for custom option strings |
| Identity | TBD A7A.2 — taxonomy: `p:woocommerce:term:{taxonomy}:{term_id}:name`; custom options: **no stable ID** |
| Extraction | Taxonomy terms only when `is_taxonomy()` |
| Overlay | Official term/attribute value filters when Supported |
| Lifecycle | term delete → source; no fuzzy |
| Sanitization | plain |
| Disposition | **Deferred** for live custom options; taxonomy path reserved Supported if fixtures appear |

### P7 — Variation attribute names

| Field | Content |
|---|---|
| Owner | WooCommerce variation-enabled attribute label |
| Ownership class | `record` |
| Identity | TBD A7A.2 — `p:woocommerce:product:{product_id}:variation_attribute_name:{attr_slug}` (**distinct from P5**) |
| Extraction | Attributes with `get_variation() === true` |
| Overlay | `woocommerce_attribute_label` when variation context / same label filter with key disambiguation |
| Disposition | **Supported** (stub) |

### P8 — Variation attribute values

| Field | Content |
|---|---|
| Owner | WooCommerce variation option values / terms |
| Ownership class | Same as P6 |
| Identity | Distinct nested field from P6 when Supported |
| Disposition | **Deferred** for live custom options; taxonomy path reserved |

---

## Catalog shells (C1–C6)

### C1 / C2 — Shop title & description

Reuse existing page Extractor + Renderer for shop page **3755**. No `p:` required. Workspace already supports `page`.

### C3–C6 — Category/tag title & description

| Field | Content |
|---|---|
| Owner | WordPress terms (`product_cat` / `product_tag`) |
| Ownership class | `record` |
| Identity | TBD A7A.2 — `p:woocommerce:{taxonomy}:{term_id}:name` / `:description` |
| Extraction | On shop page context only (canonical host); `get_terms` allowlisted taxonomies |
| Overlay | `single_term_title` / `woocommerce_page_title` / `term_description` with Store resolve from **shop page source_id** |
| Sanitization | name=plain; description=HTML (`Store::FORMAT_HTML`) |
| Disposition | **Supported** (stub) |

---

## Explicitly out of A.7a (confirm absent)

Cart, checkout, account, emails, gateways, subscriptions, bookings, bundles, composites, add-ons, inventory, merchant UI, sort/pagination/layered-nav (A.7b).

---

## A7A.1 exit criteria

- [x] Every P1–P10 / C1–C6 has stub disposition  
- [x] Four attribute splits have independent shells  
- [x] Owner undetermined → Deferred (P9/P10)  
- [x] No production `src/` Woo code in this WP  
