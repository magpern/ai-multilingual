# A.SEOd Evidence — Rank Math Analysis

**Version:** 1.0.275  
**AIML floor:** `RankMathIntegration::MIN_VERSION = '1.0.200'`  
**Modules relevant:** OpenGraph (core frontend), WooCommerce OpenGraph subclass, Slack enhanced Twitter labels

---

## Persistence keys (social)

| Logical | DB meta | Role |
|---|---|---|
| `facebook_title` | `rank_math_facebook_title` | OG title override |
| `facebook_description` | `rank_math_facebook_description` | OG description override |
| `facebook_image` / `facebook_image_id` | `rank_math_facebook_image(_id)` | OG image |
| `twitter_use_facebook` | `rank_math_twitter_use_facebook` | Reuse FB values (default true) |
| `twitter_title` / `twitter_description` | `rank_math_twitter_*` | Twitter overrides |
| `twitter_image` / `twitter_image_id` | `rank_math_twitter_*` | Twitter image |
| `twitter_card_type` | `rank_math_twitter_card_type` | Card type |

Homepage options: `titles.homepage_facebook_title|description|image|image_id`.

Live home (ID 4444): has `rank_math_title` / `rank_math_description`; no `rank_math_facebook_*` listed → OG falls through to Paper.

---

## A.SEOc interaction

A.SEOc overlays `rank_math/frontend/title|description` for explicit SEO meta only.  
OG/Twitter Paper fallback **reuses** those filters — no second identity for the cascade path.

A.SEOd must:

- Prefer reusing `p:rankmath:{owner}:{id}:title|description` for Paper-path OG/Twitter text
- Treat explicit `facebook_*` / `twitter_*` text as separate fields if Partially Supported
- Never annex Rank Math social meta into AIML as source of truth beyond Store overlays
- Never reopen SC dispositions

---

## Forbidden seams

- HTML scrape of Rank Math head
- Writing `rank_math_facebook_*` / `rank_math_twitter_*` as AIML translation DB
- Competing second OG emitter on `wp_head` that duplicates Rank Math tags
