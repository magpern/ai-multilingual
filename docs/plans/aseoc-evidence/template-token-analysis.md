# A.SEOc Evidence — Template / Token Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `488e62f93`

---

## Live templates (`rank-math-options-titles`)

| Surface | Title template | Description template |
|---|---|---|
| Post | `%title% %sep% %sitename%` | `%excerpt%` |
| Page | `%title% %sep% %sitename%` | `%excerpt%` |
| Product | `%title% %sep% %sitename%` | `%excerpt%` |
| Category | `%term% %sep% %sitename%` | `%term_description%` |
| Product cat | `%term% %sep% %sitename%` | `%term_description%` |
| Homepage | `%sitename% %page% %sep% %sitedesc%` | (empty) |

Separator: `-`.  
Note: `%sitename%` expands from WP `blogname` (**BiopentraDev** on dev), not Rank Math `website_name` display string alone.

## Token classification

| Token class | Examples | A.SEOc action |
|---|---|---|
| Already-translated content ownership | `%title%`, `%excerpt%`, `%term%`, `%term_description%`, `%wc_shortdesc%` | **Do not** create second identity; ensure expansion sees translated values |
| Stable Rank Math/WP literals / separators | `%sep%` | Leave |
| Site branding / dynamic runtime | `%sitename%`, `%sitedesc%`, `%page%`, `%wc_price%`, `%wc_sku%` | Do not translate via SEO title identity; branding Deferred unless separate admission |
| SEO self-reference | `%seo_title%`, `%seo_description%` | Resolve via Rank Math helper; avoid recursion / duplicate overlay |

## Expansion order (frozen understanding)

Custom meta or options template → `Helper::replace_vars()` / `Replacer` → **then** `rank_math/frontend/title|description`.

A.SEOc overlays **after** expansion for explicit-field paths. Template-only paths rely on token values already being language-correct.

## Duplicate-translation guard

If `%title%` already resolves AIML-translated `post_title`, A.SEOc must not also invent `p:rankmath:…:title` for that document **unless** an explicit `rank_math_title` differs from the post title source.
