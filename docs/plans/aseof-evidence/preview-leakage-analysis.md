# A.SEOf Evidence — Preview Leakage (SF8)

**Authority:** ADR-0008 + SB11 public set (`include_preview=false`).

## Contract validation

- Public SEO relationships exclude preview languages
- Sitemap/social/hreflang expected sets must match SB11 public set
- Diagnostics must not publish preview content or change preview routing

## Live

Document head and OG alternates: `/de/` leakage = **0** on sampled EN/SV page+product.

**Disposition:** SF8 **Supported** (detect/report only).
