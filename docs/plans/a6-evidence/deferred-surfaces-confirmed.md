# A.6 — Deferred surfaces confirmed (implementation)

**Status:** Confirmed — no production code in A.6  
**Date:** 2026-08-08

Reconfirmed against [admission-matrix.md](admission-matrix.md) after N1 implementation:

| IDs | Surfaces | Code in A.6? |
|---|---|---|
| D1–D2 | Blocksy header_text / copyright theme_mods | **No** |
| D3–D6 | Blocksy search/mobile/offcanvas/breadcrumbs/pagination/404 | **No** |
| D7–D10 | WP core search/comments/password/archives gettext | **No** |
| D11–D12 | widget_block / Woo products widget | **No** |
| D13–D15 | storefront / loop-card / header-auth Elementor gaps | **No** |
| D16–D17 | Age Gate / Cookie | **No** |
| D18 | Woo A.7* Deferred gettext | **No** (untouched) |
| D19–D20 | Blocksy account chrome / unused menu attrs | **No** |
| U1–U7 | Scrape / msgid / ownership theft / Store redesign / … | **Forbidden** |

Focused ADR still required before any site-scoped theme_mod or widget_block host admission.
