# A.SEOb Evidence — WooCommerce Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `a1e91f442`

---

## 1. URL surfaces

| Surface | Live check | SEO relationship need |
|---|---|---|
| Product singular | EN/SV 200; source slug retained | Canonical + hreflang via SA7 product permalink |
| Product category / tag | Prefixed via Router | Same graph rules; source term slugs |
| Shop page | Prefixed via Router | Page-like singular / shop query |
| Cart / checkout / account | Prefixed; typically noindex | Exclude from public hreflang graph when noindex / non-public |

## 2. Ownership

Woo owns product/taxonomy permalink structures. Rank Math Woo module may emit product SEO head. AIML must not invent `/sv/produkt/` rewrite bases (ADR-0002 Deferred).

## 3. Admission implication

Woo singular/archive public URLs are in scope for SB1/SB3 **using SA7 URL identity**. No Woo schema/Store changes. Translated product leaf slugs remain A.SEOa Deferred.
