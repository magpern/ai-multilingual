# A.6 — Widget analysis

## Active sidebars (live)

| Sidebar | Widgets |
|---|---|
| `sidebar-1` | `block-14`, `woocommerce_products-2` |
| `sidebar-woocommerce` | `block-15`, `block-16` |
| `ct-footer-sidebar-1..4` | `block-17..20` |
| `ct-footer-sidebar-5` | `block-28` |

Inactive demos also exist in `widget_block` (About Us / Account / Shipping / Contact Us starter copy).

---

## Block widgets (`widget_block` option)

Content is **Gutenberg block markup** stored in the options table, not in `wp_posts`.

Examples:

- Footer “About Us” / “Account” / “Shipping” / “Contact Us” — `core/heading` + `core/paragraph` / `core/list`
- Woo filter wrapper — `woocommerce/filter-wrapper` + heading “Filter by price”
- Cover / image blocks in sidebars

### Ownership

| Layer | Owner |
|---|---|
| Block types / leaf strings | Gutenberg (or Woo blocks) |
| Persistence location | WP `widget_block` option |
| AIML extract host today | Post-scoped only (`post` / `page` / `product`) |

### Why Deferred (D11)

1. No `source_id` post for widget instances.  
2. Scraping rendered sidebar HTML is Unsupported.  
3. Mapping option keys → synthetic posts would be a Store/host design change → focused ADR, not silent A.6 scope.

---

## Classic widgets

| Widget | Title | Owner | Disposition |
|---|---|---|---|
| `woocommerce_products-2` | Products | WooCommerce | Deferred (D12) |

No other classic text widgets are active in visitor sidebars.

---

## Implication for A.6

Widgets are **not** in the Supported freeze. Navigation (N1) does not depend on widget extraction.
