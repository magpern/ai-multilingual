# A.SEOc Evidence — Schema Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `488e62f93`

---

## Pipeline

1. `JsonLD` on `rank_math/head`  
2. Collect via `rank_math/json_ld`  
3. Variable replace on schema strings  
4. `rank_math/snippet/rich_snippet_{type}_entity` + `rank_math/snippet/rich_snippet_entity`  
5. `rank_math/schema/validated_data` → emit JSON-LD  

## Live observations

- `inLanguage` already follows AIML locale (`en-US` / `sv-SE`) via `rank_math/schema/language` + WP locale.  
- Product/Article `name` and `description` remain **English** on SV URLs.  
- Product breadcrumb label can show translated Woo title (`BPC-157 SV`) while Product `name` stays English SEO title — proves content vs SEO ownership split.

## Conservative admission rule

Admit only **visitor-language-dependent textual properties** that:

- are Rank Math-owned or Rank Math-emitted from admitted SEO title/description sources  
- are stable  
- are filterable before serialization  
- are not machine values (IDs, URLs, prices, ratings, dates, SKUs)  
- do not duplicate content already owned elsewhere unless Rank Math emits a distinct SEO string  

## Deferred / out of scope

| Topic | Wave / disposition |
|---|---|
| Complex dynamic graph nodes | Deferred |
| URLs / canonical / `@id` | A.SEOa / A.SEOb |
| Prices, ratings, SKUs, identifiers, dates | Unsupported for translation |
| OG/Twitter | A.SEOd |
| Sitemap entities | A.SEOe |
| Broad claim “schema translated” | Unsupported |

## Preferred seam

Mutate entity arrays via `rank_math/snippet/rich_snippet_entity` (and typed variants) and/or align schema name/description with the same Store hits used for SC1–SC4 after Rank Math builds the entity — **not** a second SEO pipeline.
