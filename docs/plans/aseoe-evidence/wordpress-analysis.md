# A.SEOe Evidence — WordPress Analysis

| Concern | Finding |
|---|---|
| Core `wp_sitemaps` | Disabled by Rank Math (`wp_sitemaps_enabled` → false) |
| `/wp-sitemap.xml` | 301 → Rank Math `/sitemap_index.xml` |
| `robots_txt` filter | WP core owns dynamic body when no static file |
| `blog_public` | Core sitewide visibility |

When Rank Math sitemap is active (this site), WordPress is **not** the sitemap emission owner.  
Priority rule: Rank Math APIs first; WordPress APIs only if Rank Math inactive/missing.

If Rank Math sitemap module disabled later: degrade to official `wp_sitemaps` filters — never invent AIML generator. Candidate-local deferral if neither owner exposes a safe seam.
