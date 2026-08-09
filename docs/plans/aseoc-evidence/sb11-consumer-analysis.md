# A.SEOc Evidence — SB11 Consumer Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `488e62f93`  
**Contract:** `AIMultilingual\Seo\LanguageRelationshipService` (A.SEOb SB11)

---

## Binding rule

A.SEOc consumes SB11 **unchanged**.

## Legitimate A.SEOc uses

- Know current vs default language for overlay gating  
- Obtain absolute language URLs if Rank Math cooperation needs relationship context (rare for title/meta text)  
- Ensure preview languages excluded from any public metadata discovery claims  

## Forbidden

- Reimplement alternate discovery  
- Duplicate canonical / hreflang emission  
- Modify `LanguageRelationship` shape or service semantics  
- Add A.SEOc→SB11 circular dependency  

## If a candidate seems to require SB11 changes

**STOP that candidate** and document the architecture issue. Do not silently edit SB11.

## Disposition

**SC11 = Supported** as a consumption/compatibility admission (no new SB11 features).
