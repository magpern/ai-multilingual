# A.SEOe Evidence — WooCommerce Analysis

**Rank Math Woo sitemap helper:** `includes/modules/woocommerce/class-sitemap.php`

| Behavior | Detail |
|---|---|
| Product CPT | Via Post_Type provider; live `product-sitemap.xml` |
| Product categories | Via Taxonomy provider; live `product_cat-sitemap.xml` |
| Product tags | Setting **off** — no child sitemap |
| Variations / coupons | Excluded |
| Attribute taxonomies | Excluded |
| Shop archive | Linked via `wc_get_page_id('shop')` when indexable |
| Gallery images | `rank_math/sitemap/urlimages` |

Live product locs are default-language (`/product/...`) without `/sv/` prefix — same gap as pages.

A.SEOe must not create a Woo-specific second provider. Language overlays use the same Rank Math entry/url filters for product/product_cat when admitted.
