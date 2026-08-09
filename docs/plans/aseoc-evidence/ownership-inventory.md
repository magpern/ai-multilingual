# A.SEOc Evidence — Ownership Inventory

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `488e62f93` (tag `a-seob-canonical-hreflang-complete`)  
**Live:** https://dev.biopentra.eu — Rank Math SEO **1.0.275** active  
**Plugin path:** `wp-content/plugins/seo-by-rank-math/`

---

## 1. Surface ownership

| Surface | Foreign owner | AIML role in A.SEOc | Wave |
|---|---|---|---|
| Rank Math SEO options / templates | Rank Math | Read + cooperate; never annex | A.SEOc |
| `rank_math_title` / `rank_math_description` post/term meta | Rank Math | Overlay translations via official filters; **not** AIML persistence | A.SEOc |
| Frontend `<title>` / meta description emission | Rank Math (`pre_get_document_title`, head) | Filter `rank_math/frontend/title\|description` | A.SEOc |
| Schema JSON-LD emission | Rank Math rich-snippet module | Bounded entity text overlays only | A.SEOc (partial) |
| Canonical URL | Rank Math when active | Already overlaid in A.SEOb | A.SEOb (done) |
| Document hreflang | AIML | Unchanged | A.SEOb |
| OG / Twitter tags | Rank Math OpenGraph | Inventory only | A.SEOd |
| XML sitemaps | Rank Math sitemap module | Inventory only | A.SEOe |
| Post/page/product title content | WP / Woo + AIML Store | `%title%` token already may resolve translated title | Existing / A.7* |
| Excerpt / short description | WP / Woo + AIML | `%excerpt%` / `%wc_shortdesc%` inherit content overlays | Existing / A.7* |
| Language locale / `inLanguage` / `og:locale` | WP locale + AIML Router | Already language-aware | Existing |

---

## 2. Critical live finding

With Rank Math active, core `document_title_parts` (AIML `Renderer::filter_document_title`) is **bypassed**. Rank Math hooks `pre_get_document_title` at priority 15 (`includes/frontend/class-head.php`). SV pages currently emit **English** SEO title/description while `og:locale` / schema `inLanguage` already follow AIML.

---

## 3. Forbidden ownership moves

- Mutating Rank Math postmeta/termmeta/options as the AIML translation store
- HTML scraping of Rank Math head output
- Emitting a second competing `<title>` / meta description
- Rewriting Rank Math into an AIML-owned SEO CMS
- Changing A.SEOb canonical/hreflang or SB11
