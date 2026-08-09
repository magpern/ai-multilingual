# A.SEOf Evidence — Sitemap / Robots / Indexability (SF6–SF7)

**Owner:** Rank Math sitemap + WP/Woo/RM robots stack; AIML `RankMathSitemapOverlay` honesty overlays.

## Contract validation

- Singular Rank Math index ownership
- Overlay hooks registered; no AIML provider
- `include_noindex` never forced true by AIML
- Public relationships for sitemap discovery use SB11 `include_preview=false`
- `blog_public=0` ⇒ AIML must not enrich discovery (honesty)

## Live emission

| Check | Result |
|---|---|
| Stored `blog_public` | **0** |
| HTTP page-sitemap xhtml / `/sv/` | **0** (honesty PASS) |
| robots `Disallow: /` present | yes (discourage indexing) |
| Rank Math `Sitemap:` line | present (owner) |
| SE11 SitemapDiscovery class | absent |

**Disposition:** SF6/SF7 **Supported** as bounded honesty/validation; never override `blog_public` for green checks.
