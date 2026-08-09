# A.SEOe Evidence — Sitemap Analysis

**Source:** Rank Math 1.0.275 `includes/modules/sitemap/`
**Live host:** `https://dev.biopentra.eu/`

---

## Architecture

1. `Sitemap` bootstrap → Router + Sitemap_Index + Redirect_Core_Sitemaps
2. Providers: Post_Type, Taxonomy, Author (+ `rank_math/sitemap/providers`)
3. `Generator` builds index and type maps → `Sitemap_Xml`
4. URLs: `/sitemap_index.xml`, `/{type}-sitemap.xml`
5. `/sitemap.xml` and `/wp-sitemap.xml` → 301 to Rank Math index

---

## Live index children (observational)

| Child | Present |
|---|---|
| `page-sitemap.xml` | Yes (~49 locs) |
| `product-sitemap.xml` | Yes (~25 locs, includes `/shop/`) |
| `product_cat-sitemap.xml` | Yes |
| `post-sitemap.xml` | Absent (0 published posts) |
| `product_tag-sitemap.xml` | Absent (setting off) |
| `author-sitemap.xml` | 404 (authors noindex) |

---

## Language behavior (critical gap)

- Sitemap `<loc>` values are **default-language URLs only** (no `/sv/` prefixes).
- **No** `xhtml:link` / `hreflang` in free Rank Math sitemap XML.
- `/sv/sitemap_index.xml` returns the **same** default-language index XML (not a separate language index).
- Document-level hreflang already correct via A.SEOb SB11 — sitemap discovery does not yet mirror that graph.

---

## Extension path (official only)

To add language-aware discovery without a second provider:

1. Ensure `xmlns:xhtml` via `rank_math/sitemap/{type}_urlset` when emitting alternates
2. Inject `xhtml:link` rel=alternate via `rank_math/sitemap/url` (or enrich entry then render) using SB11 `for_path()`
3. Optionally add language-prefixed `<loc>` entries via `entry` / `xml_post_url` — only if evidence admits without duplicates

Never buffer/regenerate Rank Math XML outside these filters.
