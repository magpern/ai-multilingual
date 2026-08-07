# A.7a — Identity matrix (frozen)

**Work package:** A7A.2  
**Serializer:** `PluginIdentity` only for `p:` keys  
**integration_id:** `woocommerce` (matches `INTEGRATION_ID_PATTERN`)  
**No new identity family. No path identity. No source text in identity.**

Preference order proven: **post → `b:` → `e:` → `p:`**.

---

## Frozen matrix

| # | Disposition | Family | Exact identity / segment key |
|---|---|---|---|
| P1 | Supported | post field | `post_title` |
| P2 | Supported | post field | `post_excerpt` |
| P3 | Supported | post field / `b:` / `e:` | `post_content` or existing block/Elementor keys — **never** Woo `p:` for body |
| P4 | Deferred | — | — |
| P5 | Supported | `p:` | `p:woocommerce:product:{product_id}:attribute_name:{attr_slug}` |
| P6 | Deferred | — | Live custom options lack stable IDs; no A.7a coding |
| P7 | Supported | `p:` | `p:woocommerce:product:{product_id}:variation_attribute_name:{attr_slug}` |
| P8 | Deferred | — | Same as P6 |
| P9 | Deferred | — | — |
| P10 | Deferred | — | — |
| C1 | Supported | post field | Shop page `post_title` |
| C2 | Supported | post field | Shop page `post_content` (when non-empty) |
| C3 | Supported | `p:` | `p:woocommerce:product_cat:{term_id}:name` |
| C4 | Supported | `p:` | `p:woocommerce:product_cat:{term_id}:description` |
| C5 | Supported | `p:` | `p:woocommerce:product_tag:{term_id}:name` |
| C6 | Supported | `p:` | `p:woocommerce:product_tag:{term_id}:description` |

### PluginIdentity mapping

| Surface | `owner_type` | `owner_id` | `field` | nested |
|---|---|---|---|---|
| P5 | `product` | `{product_id}` | `attribute_name` | `{attr_slug}` |
| P7 | `product` | `{product_id}` | `variation_attribute_name` | `{attr_slug}` |
| C3 | `product_cat` | `{term_id}` | `name` | — |
| C4 | `product_cat` | `{term_id}` | `description` | — |
| C5 | `product_tag` | `{term_id}` | `name` | — |
| C6 | `product_tag` | `{term_id}` | `description` | — |

### Component rules

| Component | Rule |
|---|---|
| `product_id` / `term_id` | Decimal digit string |
| `attr_slug` | Woo attribute slug token; must pass PluginIdentity token rules |
| Taxonomies | Only `product_cat` and `product_tag` as `owner_type` |

### Examples (fixture product 3594, attr `strength`, cat 40, tag 45)

```
p:woocommerce:product:3594:attribute_name:strength
p:woocommerce:product:3594:variation_attribute_name:strength
p:woocommerce:product_cat:40:name
p:woocommerce:product_cat:40:description
p:woocommerce:product_tag:45:name
```

All lengths ≪ 191.

### P5 vs P7 distinctness

Field tokens `attribute_name` and `variation_attribute_name` are mandatory and distinct even when the human label is identical.

### Catalog Store host

C3–C6 units sync under **shop page source_id 3755** (`SOURCE_POST`). Archive overlay resolves Store against that host when the queried object is a `product_cat` / `product_tag` term (bridge context selection — not a new Store source type / not schema change).

### Stop check

| Risk | Result |
|---|---|
| Store redesign | Not required |
| New identity family | Not required |
| Shared-definition hack | Avoided (P4/P9 deferred) |
| Fuzzy / source-in-key for P6/P8 | Avoided by Deferred |

**A7A.2 frozen.** Implementation must not invent alternate keys.
