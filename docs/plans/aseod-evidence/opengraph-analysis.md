# A.SEOd Evidence — OpenGraph Analysis

**Source:** Rank Math 1.0.275 `includes/opengraph/`  
**Observational HTML:** EN/SV page + product (not `/sv/` home — redirect loop env note)

---

## Emission pipeline

1. `rank_math/head` → `OpenGraph::output_tags` (prio 30)
2. Fires `rank_math/opengraph/facebook`
3. `Facebook` class methods emit tags via `OpenGraph::tag()` which applies `rank_math/opengraph/facebook/{prop}`

| Priority | Method | Tag |
|---|---|---|
| 1 | `locale` | `og:locale` |
| 5 | `type` | `og:type` |
| 10 | `title` | `og:title` |
| 11 | `description` | `og:description` |
| 12 | `url` | `og:url` |
| 30 | `image` | `og:image*` |

---

## Title / description resolution

`OpenGraph::get_title()` / `get_description()`:

1. Network-specific meta (`facebook_title` / `facebook_description` → DB `rank_math_facebook_*`)
2. Homepage Facebook settings when front page
3. Else Paper SEO title/description (`rank_math/frontend/title|description`)

**Implication:** When Facebook-specific meta is empty, A.SEOc overlays already cascade into OG via Paper. Live SV samples still show English titles where Store SEO translations are absent — cascade is real; content gap is translation coverage, not missing seam.

---

## URL

Default: `Paper::get()->get_canonical()` filtered by `rank_math/opengraph/url`.  
A.SEOb already filters `rank_math/frontend/canonical` → live `og:url` is language-prefixed for SV.

---

## Locale

`Facebook::locale()` uses `get_locale()` + `Facebook_Locale` allowlist; fallback `en_US`.  
AIML `Router::filter_locale` already makes live `og:locale` = `en_US` / `sv_SE`.

**`og:locale:alternate`:** not present in Rank Math 1.0.275 source or live HTML.

---

## Live samples (observational)

| Page | `og:locale` | `og:url` | `og:title` language | `og:locale:alternate` |
|---|---|---|---|---|
| EN `/aiml-v1-rc-long-doc/` | `en_US` | unprefixed | EN | absent |
| SV `/sv/aiml-v1-rc-long-doc/` | `sv_SE` | `/sv/…` | EN (gap) | absent |
| EN/SV product | correct locale/url | correct | EN title; desc often missing | absent |

Env note: `https://dev.biopentra.eu/sv/` currently 301-loops; use non-home SV URLs for acceptance.
