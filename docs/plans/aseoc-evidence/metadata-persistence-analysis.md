# A.SEOc Evidence — Metadata Persistence Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `488e62f93`

---

## Persistence owners

| Store | Owner | Contents |
|---|---|---|
| `rank-math-options-titles` | Rank Math | Global PT/tax title & description templates |
| Post meta `rank_math_*` | Rank Math | Per-post SEO title/description/robots/focus/score/… |
| Term meta `rank_math_*` | Rank Math | Per-term SEO title/description/focus |
| AIML Store | AIML | Translations of admitted overlay sources only |

## Postmeta keys observed on site

`rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`, `rank_math_robots`, `rank_math_seo_score`, `rank_math_analytic_object_id`, `rank_math_internal_links_processed`, `rank_math_og_content_image`, `rank_math_rich_snippet`.

Filled counts (approx.): title **26**, description **34**, focus **25**, robots **3** (all noindex).

## Resolution order (Rank Math Singular paper)

1. Custom post/term meta when present  
2. Else options template via `Paper::get_from_options()` + `Helper::replace_vars()`

## AIML persistence rule (frozen)

- AIML **must not** write Rank Math meta/options as its translation store.
- Admitted explicit field **source strings** are extracted into AIML Store under Integration API v1 / `PluginIdentity` keys.
- Rank Math remains source of truth for the English/default SEO field value.
- Copy/delete lifecycle: Store segments follow existing Integration + post/term lifecycle conventions; never orphan-write into Rank Math tables.

## Unsafe / out of scope persistence

- Focus keyword as primary SERP string (not title/desc) — inventory only; admission not required for SC1–SC4
- SEO score / analytics object IDs — machine values; never translate
- OG image JSON — A.SEOd
