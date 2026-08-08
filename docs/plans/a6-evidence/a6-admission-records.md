# A.6 — Admission records (implementation freeze)

**Status:** Frozen for implementation  
**Date:** 2026-08-08  
**Branch:** `feature/a6-wordpress-visitor-chrome`  
**Canonical matrix:** [admission-matrix.md](admission-matrix.md)

---

## Supported — N1

| Field | Value |
|---|---|
| ID | **N1** |
| Surface | Custom navigation menu item titles |
| Owner | WordPress `nav_menu_item` |
| Identity | Existing Store field `post_title` (Extractor `FIELD_TITLE`) |
| `source_type` | `post` (`Store::SOURCE_POST`) |
| `source_id` | `nav_menu_item` post ID |
| `source_subtype` | `nav_menu_item` |
| PluginIdentity / `p:` | **Not used** |
| Extract when | `trim(post_title) !== ''` |
| Extract skip | Empty custom titles (object-title path = AC1) |
| Overlay | `the_title` for `nav_menu_item` (remove Renderer skip) |
| Live fixture | Menu item **3474** (“Home”) on Main Menu **34** |

---

## Explicit non-admissions (reconfirmed)

| ID | Disposition |
|---|---|
| AC1–AC5 | Already covered — not re-admitted |
| D1–D20 | Deferred — no production code in A.6 |
| U1–U7 | Unsupported — forbidden |

---

## Freeze statement

A.6 ships **N1 only**. No Blocksy theme_mods, widget_block hosts, gettext msgid capture, storefront/loop-card, Age Gate, Cookie, Woo Deferred, Elementor, Gutenberg, or SEO work.
