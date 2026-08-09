# A.SEOe Evidence — Admission Matrix

**Status:** Planning freeze — evidence-driven dispositions
**Baseline:** `main` @ `4f1f231ec`
**Rule:** Every SE1–SE12 item started as **Candidate**. Disposition only after evidence.

---

## Final dispositions

| ID | Topic | Disposition | Evidence basis | ADR gate |
|---|---|---|---|---|
| SE1 | XML sitemap generation | **Supported** | Rank Math owns generation; AIML never generates; A.SEOe preserves singular Rank Math emission | None new |
| SE2 | Language-specific sitemap URLs | **Supported** | Official `xml_post_url` / `entry` / `url` filters + SB11 `url_for_language`; live gap = default-only locs | A.SEOa/SB11 |
| SE3 | Alternate-language sitemap relationships | **Supported** | Free RM lacks xhtml:link; seams `{$type}_urlset` + `sitemap/url` allow xmlns:xhtml + xhtml:link from SB11 without scrape | SB11 |
| SE4 | Published-language inclusion | **Supported** | SB11 public/routable set only | SB11 |
| SE5 | Preview exclusion | **Supported** | ADR-0008 + SB11 `include_preview=false` | ADR-0008 |
| SE6 | Robots integration | **Supported** | Preserve WP/RM/WC robots stack; RM owns `Sitemap:` line; AIML does not replace robots.txt; honesty gates only | None new |
| SE7 | Indexability policy | **Supported** | Respect noindex / unpublished / `include_noindex` default; never increase indexability via translations | None new |
| SE8 | Rank Math sitemap cooperation | **Supported** | Official providers/filters/extension points only; overlays without replacing ownership | Integration API v1 |
| SE9 | WooCommerce sitemap cooperation | **Supported** | Product + product_cat via RM providers; same language overlays; no second Woo provider; product_tag when owner-enabled | None new |
| SE10 | Attachment/media policy | **Deferred** | Image NS owned by RM Image_Parser; no safe multilingual attachment contract without Media Library mutation | Prefer deferral |
| SE11 | Canonical reusable sitemap/discovery contract for A.SEOf | **Deferred** | No SitemapDiscovery contract in `src/`; must not invent | Never invent |
| SE12 | Search-engine discovery validation | **Supported** | Bounded automated + observational validation strategy (not A.SEOf diagnostics UI) | None new |

---

## Explicitly not Supported in A.SEOe

| Topic | Disposition | Why |
|---|---|---|
| Parallel / AIML sitemap generator | Unsupported | Hard ownership rule |
| Duplicate sitemap index | Unsupported | Hard ownership rule |
| XML scraping / post-processing rewrite | Unsupported | Hard ownership rule |
| Shadow sitemap registry | Unsupported | Hard ownership rule |
| Multilingual attachment/media identity | Deferred | SE10 |
| Invented reusable discovery contract | Deferred | SE11 |
| News/video sitemap PRO modules | Deferred / out of wave | Inactive PRO; no free seams claimed |
| Slug/canonical/hreflang/title/OG ownership | Out of wave | A.SEOa–d |
| SEO diagnostics UI | Out of wave | A.SEOf |

---

## Supported set (frozen for implementation authorization)

**Supported:** SE1, SE2, SE3, SE4, SE5, SE6, SE7, SE8, SE9, SE12
**Deferred:** SE10, SE11

Implementation may claim Supported only within the frozen boundaries in the wave plan (Rank Math active path primary; WP core path only if Rank Math sitemap inactive and official `wp_sitemaps` seams suffice — else defer).
