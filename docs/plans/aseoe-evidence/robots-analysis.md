# A.SEOe Evidence — Robots Analysis

---

## Live stack (`/robots.txt`)

1. Cloudflare Managed Content (edge)  
2. WordPress dynamic `robots_txt`  
3. WooCommerce upload/add-to-cart Disallows  
4. Rank Math `Sitemap: https://dev.biopentra.eu/sitemap_index.xml`  
5. Rank Math custom `robots_txt_content` = **empty** → “let WordPress handle”

No physical `robots.txt` file on disk.

---

## Indexability surfaces

| Mechanism | Owner | Notes |
|---|---|---|
| `blog_public` | WordPress | Sitewide discourage; honesty required |
| Rank Math global robots | Rank Math titles options | Foreign |
| Per-object `rank_math_robots` | Rank Math post/term meta | Foreign |
| `rank_math/frontend/robots` | Rank Math Paper | Document meta robots (not sitemap product) |
| `rank_math/sitemap/include_noindex` | Rank Math sitemap | Default excludes noindex from sitemaps |
| Preview languages | AIML ADR-0008 / SB11 | Must not appear in public discovery |

---

## Hard principle

AIML must **never** make a source **more** indexable because a translated URL exists.  
Noindex / unpublished / preview must not become publicly discoverable through AIML sitemap overlays.

---

## AIML role (recommended)

- Do **not** replace `robots.txt` body  
- Preserve Rank Math `Sitemap:` directive ownership  
- Enforce preview/unpublished/noindex exclusion in sitemap overlays  
- Do not invent an AIML robots policy engine  
