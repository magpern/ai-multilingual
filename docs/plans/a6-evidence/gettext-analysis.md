# A.6 — Gettext analysis

## Policy (frozen)

From [POST_V1_PLATFORM_ROADMAP.md](../POST_V1_PLATFORM_ROADMAP.md) §6.1 and A.7* lessons:

- Prefer official pre-render data filters that mutate **labels**.  
- Gettext-only templates without such filters → **Deferred**.  
- Do not hijack `gettext` / `ngettext` by msgid.  
- Do not key Store rows by source English string (fuzzy identity).

---

## Classes observed

| Class | Examples | A.6 disposition |
|---|---|---|
| WP core visitor gettext | Search submit, password form, comment form | Deferred (D7–D10) |
| Blocksy theme gettext | Search, offcanvas, 404, pagination, breadcrumbs | Deferred (D3–D6) |
| Woo template gettext | Cart/notice/email body (A.7 Deferred) | Not stolen into A.6 (D18) |
| storefront / loop-card gettext | Search placeholders, card CTAs, footer email | Deferred (D13–D14) |
| Age Gate option-backed strings | `age_gate_messages` | Deferred to integration track (D16) — not gettext-primary |
| AIML plugin gettext | Admin / workspace UI | Out of scope (merchant UI) |

---

## Contrast with N1

N1 (custom menu titles) is **not** gettext. It is post field data (`nav_menu_item.post_title`) with a first-class WP filter seam (`the_title`). That is why it alone survives Supported admission.

---

## Future unlock (out of A.6 freeze)

If Blocksy or storefront adds owner-declared filters or records for chrome strings, re-inventory and consider a narrow admission — still without scrape or msgid identity.
