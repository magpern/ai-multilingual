# A.SEOd Evidence — WooCommerce Analysis

**Rank Math module:** `includes/modules/woocommerce/class-opengraph.php`

| Hook | Behavior | Multilingual? |
|---|---|---|
| `language_attributes` | Adds `product:` OG namespace on singular products | Namespace only |
| `rank_math/opengraph/facebook` prio 50 | `product:brand`, `product:price:*`, `product:availability` | Machine values — leave |
| `rank_math/opengraph/facebook/add_additional_images` | Category thumb / optional gallery | Image binary — see social-image-analysis |
| Slack Twitter labels | Price / Availability `twitter:labelN` / `twitter:dataN` | Machine / HTML snippets — leave |

`og:type` = `product` on product singulars (Rank Math Facebook `get_type()`).

Product SEO title/description remain A.SEOc. A.SEOd overlays only admitted social text filters; does not translate price/availability/SKU.
