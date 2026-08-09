# A.SEOc Evidence — Language Context Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `488e62f93`

---

## Existing behavior

| Mechanism | Effect |
|---|---|
| `LanguageContext` | Current request language; `is_translated()` gates overlays |
| `Router::filter_locale` | Rank Math `og:locale` / schema `inLanguage` follow AIML |
| Published vs preview | ADR-0008 / SA10 / SB9 |

## A.SEOc requirement

Title/description overlays must select Store translations using the **current LanguageContext language**, same as other Integration API consumers.

## Must not

- Infer language from Rank Math options  
- Publish preview-language metadata to anonymous visitors  
- Fall back across languages (ADR-0008)  

## Sitewide noindex honesty

`blog_public=0` forces Rank Math `noindex,nofollow`. Validate overlays via filters/API and head inspection of title/description strings; do not treat missing canonical HTML as A.SEOc failure (A.SEOb precedent).
