# A.7b — Final admission records (B1 / B2)

## B1 — Catalog orderby option labels

| Field | Value |
|---|---|
| Owner | WooCommerce |
| Source | Default `woocommerce_catalog_orderby` option map |
| Identity | `p:woocommerce:catalog_orderby:{key}:label` via `PluginIdentity::build( 'woocommerce', 'catalog_orderby', $key, 'label' )` |
| Hook | `woocommerce_catalog_orderby` |
| Dynamic class | Static labels |
| Store anchor | Technical shop page via `wc_get_page_id( 'shop' )` |
| Overlay | Replace map **values** only; keys unchanged |
| Workspace | `surface=plugin_integration`; label “Catalog orderby: {key}”; parent_context “WooCommerce archive chrome” |
| Lifecycle | Missing key → omit; disabled → source; shop page change → new anchor ID |
| Sanitization | Plain |
| Disposition | **Supported** |

Allowlisted keys: `menu_order`, `popularity`, `rating`, `date`, `price`, `price-desc`, `relevance`.

## B2 — Catalog orderedby status labels

| Field | Value |
|---|---|
| Owner | WooCommerce |
| Source | Default `woocommerce_catalog_orderedby` map |
| Identity | `p:woocommerce:catalog_orderedby:{key}:label` via `PluginIdentity::build( 'woocommerce', 'catalog_orderedby', $key, 'label' )` |
| Hook | `woocommerce_catalog_orderedby` |
| Dynamic class | Static labels |
| Store anchor | Same technical shop page |
| Overlay | Replace map **values** only |
| Workspace | “Catalog orderedby: {key}”; Woo archive chrome context |
| Sanitization | Plain |
| Disposition | **Supported** |

Allowlisted keys: same as B1 (excluding keys Woo omits at runtime).
