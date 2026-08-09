# A.SEOe Evidence — Crawler Analysis

**Method:** Observational HTTP only (evidence / future acceptance — not implementation).

| Probe | Result |
|---|---|
| `/robots.txt` | 200; ends with Rank Math `Sitemap:` line |
| `/sitemap_index.xml` | 200 Rank Math index |
| `/sitemap.xml` | 301 → index |
| `/wp-sitemap.xml` | 301 → index |
| Type sitemaps | page, product, product_cat present |
| xhtml:link in type sitemaps | **Absent** |
| `/sv/` in sitemap locs | **Absent** |
| `/sv/` front page | Pre-existing 301 self-loop (not A.SEOe) |
| Document hreflang on SV page | Present (A.SEOb) |

## Validation strategy implications

Acceptance must prove after implementation:

- Single sitemap owner / single index  
- Valid XML + namespaces  
- Published-language locs and/or xhtml alternates per admitted SE2/SE3  
- Preview/noindex excluded  
- FP=0, leakage=0, duplicate locs/alternates=0  
- `/sv/` home loop recorded as env debt if it blocks crawling that URL — use non-home SV URLs  
