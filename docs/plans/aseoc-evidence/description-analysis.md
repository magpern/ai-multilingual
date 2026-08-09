# A.SEOc Evidence — Description Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `488e62f93`

---

## Emission path

Parallel to titles: paper description → `replace_vars` → `rank_math/frontend/description`.

Default PT templates: `pt_*_description` = `%excerpt%`.

## Live evidence

| URL | Meta description |
|---|---|
| EN/SV fixture page | `A4 Group Paragraph Source` (same English both languages) |
| EN/SV product `bpc-157` | Filled Rank Math research-peptide prose (same English both languages) |

## Source classes

| Class | Example | Policy |
|---|---|---|
| Explicit `rank_math_description` | Product filled meta | Translate stable source; overlay via filter |
| Template `%excerpt%` | Page default | Inherit excerpt/content overlays; no duplicate SEO description identity when meta empty |
| Auto-generated description | Rank Math paper auto path | Prefer defer if unstable/opaque; do not invent identity from scraped head |

## Frozen description policy

Same as titles: **stable explicit Rank Math fields** are Supported sources; **token-composed** descriptions rely on already-translated content tokens; never annex Rank Math meta as Store; never scrape HTML.
