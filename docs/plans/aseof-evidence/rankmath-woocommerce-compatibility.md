# A.SEOf Evidence — Rank Math / WooCommerce (SF11–SF12)

## Rank Math (SF12)

- Active **1.0.275** on live site
- Compatibility already modeled in `RankMathIntegration::get_compatibility()`
- Diagnostics should surface installed/active/version/hooks/disabled states without reimplementing SEO

## WooCommerce (SF11)

Upstream Supported surfaces already cover product + product_cat (A.SEOc–e). Diagnostics validate those objects using the same SB11/RM contracts — no Woo permalink mutation, no second product SEO pipeline.

Live product EN/SV: hreflang + OG language-correct; dual title attributed foreign.

**Disposition:** SF11/SF12 **Supported**.
