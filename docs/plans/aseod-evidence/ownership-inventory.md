# A.SEOd Evidence — Ownership Inventory

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `e4cd9ab36` (A.SEOc complete)  
**Rank Math:** 1.0.275  

---

## Emitter ownership (social tags)

| Surface | Primary emitter | Persistence | AIML role today | Wave |
|---|---|---|---|---|
| `og:title` | Rank Math Facebook OpenGraph | `rank_math_facebook_title` else Paper SEO title | None (cascade may inherit A.SEOc title when FB empty) | A.SEOd |
| `og:description` | Rank Math Facebook OpenGraph | `rank_math_facebook_description` else Paper SEO description | None (same cascade) | A.SEOd |
| `og:url` | Rank Math Facebook OpenGraph | Derived from `Paper::get_canonical()` | Indirect via A.SEOb `rank_math/frontend/canonical` | A.SEOd verify |
| `og:image*` | Rank Math Image helper | `rank_math_facebook_image(_id)` / featured / content | None | A.SEOd |
| `og:type` | Rank Math Facebook OpenGraph | Computed (website/article/product) | None — machine | Leave owner |
| `og:locale` | Rank Math Facebook OpenGraph | `get_locale()` → Facebook_Locale sanitize | Indirect via `Router::filter_locale` | A.SEOd |
| `og:locale:alternate` | **Not emitted by Rank Math 1.0.275** | N/A | None | A.SEOd if admitted |
| `twitter:card` | Rank Math Twitter OpenGraph | `rank_math_twitter_card_type` / defaults | None — config | Leave owner |
| `twitter:title` | Rank Math Twitter OpenGraph | FB meta when `twitter_use_facebook` (default true), else `rank_math_twitter_title`, else Paper | None | A.SEOd |
| `twitter:description` | Rank Math Twitter OpenGraph | Same reuse path | None | A.SEOd |
| `twitter:image` | Rank Math Twitter OpenGraph | Same reuse path | None | A.SEOd |
| `product:*` OG extras | Rank Math WooCommerce OpenGraph | Product data | None — machine | Leave owner |
| Canonical / hreflang | AIML DocumentSeoHead + A.SEOb | SB11 | Already owned | **Not A.SEOd** |
| SEO title / meta description | Rank Math + A.SEOc overlays | `rank_math_title` / `rank_math_description` | A.SEOc Supported | **Not A.SEOd** |

---

## Non-owners (must not annex)

| Actor | Finding |
|---|---|
| WordPress core | No default OG/Twitter emitters |
| WooCommerce core | No OG emitters; Rank Math Woo module adds product OG |
| Elementor | No social meta ownership observed for this inventory |
| Blocksy / theme | No competing `og:` emitters observed on live samples |
| AIML | No `SocialMeta` / OpenGraph class in `src/`; no OG/Twitter filters registered |

---

## Official Rank Math seams (implementation only)

- Actions: `rank_math/opengraph/facebook`, `rank_math/opengraph/twitter`
- Per-tag filters: `rank_math/opengraph/facebook/{prop}`, `rank_math/opengraph/twitter/{prop}` (`:` → `_`)
- URL: `rank_math/opengraph/url`
- Image: `rank_math/opengraph/{network}/image`, `image_array`, add_images actions
- SEO fallback: `rank_math/frontend/title`, `rank_math/frontend/description`, `rank_math/frontend/canonical`

Never scrape rendered HTML as the implementation mechanism.
