# A.6 — Admission matrix

**Status:** Frozen for planning  
**Rule:** Evidence decides. Do not assume candidates survive.

| Disposition | Meaning |
|---|---|
| **Supported** | Clear owner + deterministic identity + Store reuse without redesign + official/overlay-safe seam |
| **Deferred** | Real surface, wrong seam, site-global Store host gap, gettext-only, inactive, or wrong owner |
| **Unsupported** | Violates architecture (scrape, steal ownership, fuzzy identity, second pipeline) |
| **Already covered** | Owned by prior AIML milestone — not re-admitted |

---

## Supported

| ID | Surface | Owner | Identity | Overlay | Store host |
|---|---|---|---|---|---|
| **N1** | Custom navigation menu item titles | WordPress `nav_menu_item` | Existing post field `post_title` on menu-item post ID (no new identity family) | Enable `the_title` for `nav_menu_item` (remove skip); optional `nav_menu_item_title` consistency | `source_id` = menu item post ID |

Live fixture: menu item **3474** (“Home”) is the proven custom-title case on Main Menu 34.

---

## Already covered (not A.6)

| ID | Surface | Owner / prior milestone |
|---|---|---|
| AC1 | Menu labels that resolve to linked object titles (empty custom title) | Page/product `post_title` via existing Renderer |
| AC2 | Gutenberg document content | A.0 / A.4 / F14 |
| AC3 | Elementor document controls | A.2 / A.3 / ADR-0016 |
| AC4 | Woo catalog / archive chrome / journey / email subject+heading | A.7a–A.7d |
| AC5 | Fluent Forms Contact #5 field labels + submit | A.8 |

---

## Deferred

| ID | Surface | Reason |
|---|---|---|
| D1 | Blocksy header builder promotional HTML (`header_text`) | Theme_mod site-global; Store resolution is post-scoped (Age Gate lesson) |
| D2 | Blocksy copyright (`copyright_text`) | Same site-global host gap |
| D3 | Blocksy search overlay / mobile menu / offcanvas chrome | Theme gettext + builder; no declared deterministic AIML identity |
| D4 | Blocksy breadcrumbs shortcode | Theme-owned; no owner-declared overlay contract |
| D5 | Blocksy pagination strings | Theme-owned (A.7b B5) |
| D6 | Blocksy 404 template chrome | Theme gettext |
| D7 | WP core search form labels | Gettext-only |
| D8 | WP comments UI | Inactive on live site |
| D9 | WP password-protected post UI | No live password posts |
| D10 | WP archives / calendar / RSS chrome | Inactive or demo widget copy only |
| D11 | Block widgets (`widget_block` option markup) | Gutenberg-owned content without post-scoped Store host |
| D12 | Classic `woocommerce_products` widget title | Residual; not A.7; low value |
| D13 | biopentra-storefront shortcode gettext (home/shop search, refine, footer email) | Gettext-only; no official overlay filter; wrong to invent scrape |
| D14 | biopentra-loop-card visitor i18n | First-party card chrome; excluded by A.7b ownership rules |
| D15 | biopentra header-auth Elementor control gaps | Elementor coverage lane (not WP chrome) |
| D16 | Age Gate messages | Shared-definition options; production-integration track (A.8 runner-up) |
| D17 | Cookie Law / CookieYes banner | No official content overlay filters |
| D18 | A.7* Deferred Woo gettext (cart, notices, email body, …) | Remain Woo Deferred — do not steal into A.6 |
| D19 | Login / account chrome labels (Blocksy account element) | Theme gettext |
| D20 | Menu item description / attr_title / xfn | Not used meaningfully on live menu |

---

## Unsupported

| ID | Pattern | Why |
|---|---|---|
| U1 | HTML scraping of header/footer/DOM | Forbidden by platform contract |
| U2 | Gettext capture keyed by msgid / source string | Fuzzy / non-deterministic identity |
| U3 | Path identity / URL-keyed units | Forbidden |
| U4 | Stealing Gutenberg / Elementor / Woo / Fluent / Rank Math ownership | Ownership theft |
| U5 | Second translation pipeline for theme chrome | Forbidden |
| U6 | Store / schema redesign inside A.6 to force site-global theme_mods | Stop → focused ADR instead |
| U7 | New identity family beyond `b:` / `e:` / `p:` / post fields | Forbidden for this milestone |

---

## Freeze statement

A.6 implementation may ship **N1 only** unless new live evidence upgrades a Deferred row without violating Unsupported rules.

Site-global Blocksy builder strings (D1/D2) and widget_block hosts (D11) require a **focused ADR** before Supported admission — do not silently invent a site-scoped Store in A.6.
