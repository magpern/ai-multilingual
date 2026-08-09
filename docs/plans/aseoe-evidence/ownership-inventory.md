# A.SEOe Evidence — Ownership Inventory

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `4f1f231ec` (A.SEOd complete)  
**Rank Math:** 1.0.275; sitemap module **active**

---

## Emitter ownership (discovery)

| Surface | Primary emitter | AIML role today | Wave |
|---|---|---|---|
| XML sitemap index | Rank Math (`/sitemap_index.xml`) | None | A.SEOe |
| Type sitemaps (`page`, `product`, `product_cat`, …) | Rank Math providers | None | A.SEOe |
| WP core `wp_sitemaps` | **Disabled** by Rank Math; `/wp-sitemap.xml` → 301 to Rank Math index | None | A.SEOe |
| `robots.txt` body | WP dynamic + Woo Disallows + Cloudflare edge | None | A.SEOe |
| `Sitemap:` robots directive | Rank Math `Sitemap_Index::add_sitemap_directive` | None | A.SEOe |
| Document hreflang | AIML `DocumentSeoHead` (A.SEOb) | Owned | **Not A.SEOe** |
| Canonical / titles / OG | A.SEOb / A.SEOc / A.SEOd | Owned | **Not A.SEOe** |

---

## Hard ownership rule (frozen for plan)

AIML cooperates with Rank Math (active owner). AIML must **not** become a second sitemap provider.

Unsupported: parallel generator, duplicate index, XML scrape, post-processing rewrite, shadow registry.

---

## Official Rank Math seams (implementation candidates)

| Hook | Role |
|---|---|
| `rank_math/sitemap/entry` | Mutate/drop entry |
| `rank_math/sitemap/url` | Final `<url>` XML string |
| `rank_math/sitemap/{$type}_urlset` | Urlset opening / namespaces |
| `rank_math/sitemap/xml_post_url` | Permalink override |
| `rank_math/sitemap/providers` | Extra providers — **do not use to replace RM** |
| `rank_math/sitemap/include_noindex` | Noindex inclusion gate |
| `rank_math/sitemap/exclude_post_type` / `exclude_taxonomy` | Exclusions |
| `robots_txt` | Sitemap directive (RM) / body (WP) |

---

## Non-owners

| Actor | Finding |
|---|---|
| WordPress core sitemaps | Disabled when Rank Math sitemap active |
| Elementor / Blocksy | No sitemap ownership observed |
| AIML `src/` | **Zero** sitemap/robots product code |
