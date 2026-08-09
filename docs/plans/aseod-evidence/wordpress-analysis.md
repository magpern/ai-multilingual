# A.SEOd Evidence — WordPress Analysis

**Finding:** WordPress core does not emit OpenGraph or Twitter Card meta tags by default.

| Concern | Owner |
|---|---|
| `wp_head` document title | Core / Rank Math (`pre_get_document_title`) — A.SEOc |
| `rel=canonical` | Core + AIML DocumentSeoHead — A.SEOb |
| Social meta | Rank Math when active |

No WordPress filter is a primary A.SEOd implementation seam while Rank Math owns social emission.  
If Rank Math is inactive, A.SEOd degrades: no AIML-invented OG pipeline (never fatal; native theme/WP behavior remains).
