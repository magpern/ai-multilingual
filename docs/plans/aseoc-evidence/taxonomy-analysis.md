# A.SEOc Evidence — Taxonomy Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `488e62f93`

---

## Templates

- Category / product_cat / product_brand: `%term% %sep% %sitename%` + `%term_description%`  
- Post tag / product_tag: descriptions present; robots often **noindex**

## Term meta

Keys in use: `rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`.

Example `product_cat` term 36 (`research-peptides`): filled title + description + focus.

Many child terms: description only → title falls back to tax template.

## Ownership

| Value | Owner |
|---|---|
| Term name | WP taxonomy + AIML content overlays (existing) |
| Term description | WP + AIML (existing where admitted) |
| Explicit Rank Math term SEO title/description | Rank Math |

## Admission implication

SC5/SC6 Supported for **explicit** Rank Math term SEO fields via the same Integration + filter pattern as posts. Template-only tax titles inherit `%term%` / `%term_description%` without new Rank Math identity when meta empty.
