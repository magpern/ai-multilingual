# A.SEOe Evidence — Preview Analysis

**Contracts:** ADR-0008, SA10, SB9, SB11

| Requirement | Mechanism |
|---|---|
| Preview languages excluded from public sitemaps | SB11 `for_public_request()` / `for_path(..., false)` |
| Preview URLs never become sitemap `<loc>` or xhtml alternates | Same |
| Authorized translator preview stays out of discovery product | PreviewService unchanged; no sitemap registration for preview |

A.SEOe must not widen discovery to preview-capable viewers’ languages on public sitemap responses.
