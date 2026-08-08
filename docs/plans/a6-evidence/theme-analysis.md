# A.6 — Theme analysis (Blocksy)

**Parent theme:** Blocksy
**Child:** `blocksy-child` (BioPentra)
**Companion:** Blocksy Companion active
**Options host:** `theme_mods_blocksy` (≈77 keys; ~34 KB)

---

## Builder chrome

### Header (`header_placements`)

Present elements on live site: `logo`, `menu`, `search`, `account`, `cart`, `text`, `socials`, `trigger`, `offcanvas`, rows.

Visitor-facing custom content found:

| Key / path | Sample | Owner |
|---|---|---|
| Header text element `header_text` | `Free shipping over €200` | Blocksy theme_mod |

No AIML post identity. Overlay would require either:

1. site-scoped Store resolution (framework change → ADR), or
2. HTML scrape of rendered header (Unsupported).

**Disposition:** Deferred (D1).

### Footer (`footer_placements`)

Widget areas `widget-area-1..5` + `copyright`.

| Key | Sample | Owner |
|---|---|---|
| `copyright_text` | `Copyright © {current_year} - Biopentra` | Blocksy theme_mod |

`{current_year}` is runtime interpolation → even with a host, treat as dynamic-text caution (prefer label/static HTML without inventing gettext hijack).

**Disposition:** Deferred (D2).

---

## Theme templates / gettext

| Surface | Evidence | Disposition |
|---|---|---|
| 404 | `themes/blocksy/404.php` | Deferred (D6) |
| Pagination | A.7b: Blocksy replaces Woo pagination | Deferred (D5) |
| Breadcrumbs | `blocksy_breadcrumbs` shortcode registered | Deferred (D4) |
| Search UI | Header `search` element + theme strings | Deferred (D3) |
| Mobile / offcanvas | `trigger` + `offcanvas` placements | Deferred (D3) |
| Account element | Header `account` | Deferred (D19) |

Theme gettext remains theme-owned. A.6 does not invent a Blocksy integration without owner-declared deterministic filters.

---

## Child theme

`blocksy-child` theme_mods are minimal (`nav_menu_locations` dominant). No additional child-owned visitor string bag discovered beyond parent builder content.

---

## Compatibility rule

A.6 must not break Blocksy header/footer builder rendering. N1 overlays menu item titles only; builder HTML and theme gettext stay untouched.
