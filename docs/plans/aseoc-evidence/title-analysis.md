# A.SEOc Evidence — Title Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `488e62f93`

---

## Emission path

1. Rank Math `Head` filters `pre_get_document_title` → paper title  
2. Paper resolves custom `rank_math_title` **or** PT template (e.g. `pt_page_title` = `%title% %sep% %sitename%`)  
3. `Helper::replace_vars()` expands tokens  
4. Filter `rank_math/frontend/title` receives the **already expanded** string  

AIML `Renderer::filter_document_title` on `document_title_parts` does **not** run for Rank Math titles.

## Live evidence

| URL | Title |
|---|---|
| EN `/a4-nested-gutenberg-fixture/` | `A4 Nested Gutenberg Fixture - BiopentraDev` (template; no meta) |
| SV same path | **Identical English string** |
| EN `/product/bpc-157/` | `BPC-157 Research Peptide \| Biopentra` (filled meta) |
| SV product | **Identical English string** |

## Two source classes

| Class | Example | Identity implication |
|---|---|---|
| **Explicit Rank Math field** | Filled `rank_math_title` literal | Stable Rank Math-owned source → AIML Store overlay candidate |
| **Generated template** | `%title% %sep% %sitename%` | Compose from tokens; do **not** invent identity for full interpolated string |

## Frozen title policy

1. Translate **stable Rank Math-owned source components** (explicit title meta without variable tokens), not arbitrary final SERP strings that mix unstable runtime values.  
2. When title is template-generated from `%title%` (and similar content tokens already owned by WP/Woo/AIML content overlays), Rank Math expansion should inherit translated token values — **no second title identity**.  
3. Overlay applies through `rank_math/frontend/title` only.  
4. Site branding (`%sitename%`) remains Rank Math/WP-owned unless separately admitted (not required for SC1/SC3 primary path).

## WP title vs Rank Math SEO title

Post `post_title` translation already exists. Explicit SEO titles that differ from `post_title` (product BPC-157) are the primary A.SEOc title gap.
