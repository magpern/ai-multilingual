# A.7a — Admission records (shells → completed at A7A.2+)

Per-candidate admission shells from A7A.1. Identity cells finalized in A7A.2. Only **Supported** rows may be coded in A7A.3+.

Integration package (A7A.3+): `src/Integration/WooCommerce/`  
Integration ID: `woocommerce`  
Disable filter: `aiml_woocommerce_integration_disabled`  
Min version: **10.0.0**

---

## Supported (coding allowed after A7A.2)

### P1 Product title

| Field | Value |
|---|---|
| Owner | WordPress `product.post_title` |
| Ownership class | document (existing post field) |
| Identity | Existing `post_title` segment (no `p:`) |
| Extraction | `Extractor` field pipeline |
| Overlay | `Renderer::filter_title` / `the_title` |
| Lifecycle | product delete → no visitor overlay; Store retained |
| Sanitization | plain (existing) |
| Platform | Workspace after admitting `product` post type |
| Disposition | **Supported** |

### P2 Product short description

| Field | Value |
|---|---|
| Owner | WordPress `product.post_excerpt` (Woo short description) |
| Ownership class | document |
| Identity | Existing `post_excerpt` |
| Extraction / Overlay | Extractor + `get_the_excerpt` Renderer |
| Disposition | **Supported** |

### P3 Product long description

| Field | Value |
|---|---|
| Owner | WordPress `product.post_content` (classic); Gutenberg/Elementor via `b:`/`e:` if present |
| Ownership class | document |
| Identity | Existing `post_content` / `b:` / `e:` — **never** re-key under Woo |
| Disposition | **Supported** |

### P5 Attribute names

| Field | Value |
|---|---|
| Owner | WooCommerce product attribute label |
| Ownership class | record |
| Identity | `p:woocommerce:product:{product_id}:attribute_name:{attr_slug}` via `PluginIdentity` |
| Extraction | Allowlisted from `WC_Product::get_attributes()` |
| Overlay | `woocommerce_attribute_label` |
| Sanitization | plain |
| Disposition | **Supported** |

### P7 Variation attribute names

| Field | Value |
|---|---|
| Owner | WooCommerce variation attribute label |
| Ownership class | record |
| Identity | `p:woocommerce:product:{product_id}:variation_attribute_name:{attr_slug}` |
| Extraction | Attributes with variation flag |
| Overlay | `woocommerce_attribute_label` (resolve variation key when attribute is variation-enabled) |
| Sanitization | plain |
| Disposition | **Supported** |

### C1 Shop archive title

| Field | Value |
|---|---|
| Owner | WP shop page title (ID **3755**) |
| Identity | Existing `post_title` on page |
| Disposition | **Supported** |

### C2 Shop archive description

| Field | Value |
|---|---|
| Owner | WP shop page content when non-empty |
| Identity | Existing `post_content` on page |
| Disposition | **Supported** (empty live → no unit emitted) |

### C3 Category title

| Field | Value |
|---|---|
| Owner | WP `product_cat` term name |
| Ownership class | record |
| Identity | `p:woocommerce:term:product_cat:{term_id}:name` |
| Host source | Shop page **3755** Store rows |
| Overlay | `single_term_title` / `woocommerce_page_title` |
| Sanitization | plain |
| Disposition | **Supported** |

### C4 Category description

| Field | Value |
|---|---|
| Owner | WP `product_cat` term description |
| Identity | `p:woocommerce:term:product_cat:{term_id}:description` |
| Overlay | `term_description` |
| Sanitization | HTML (`FORMAT_HTML`) |
| Disposition | **Supported** |

### C5 Tag title

| Field | Value |
|---|---|
| Owner | WP `product_tag` term name |
| Identity | `p:woocommerce:term:product_tag:{term_id}:name` |
| Disposition | **Supported** |

### C6 Tag description

| Field | Value |
|---|---|
| Owner | WP `product_tag` term description |
| Identity | `p:woocommerce:term:product_tag:{term_id}:description` |
| Disposition | **Supported** (skip empty) |

---

## Deferred (no coding)

### P4 Product tabs

Deferred — visitor tab **titles** are Woo shared i18n strings; description tab **body** already P3. No deterministic record-owned tab title identity without shared-definition scope.

### P6 Attribute values

Deferred for live **custom** option strings (`10mg`, `20mg`) — no stable ID without putting source text in identity. Taxonomy-term values remain future admission if fixtures use global attributes.

### P8 Variation attribute values

Deferred — same as P6.

### P9 Woo notices

Deferred — product notices are Woo i18n / shared-definition; no per-product stable notice record proven at A7A.1.

### P10 Woo-owned breadcrumbs

Deferred — breadcrumb chrome ownership not proven Woo-only (theme/Blocksy present).

---

## Out of scope confirmation

No cart / checkout / account / emails / extensions / merchant UI candidates admitted.
