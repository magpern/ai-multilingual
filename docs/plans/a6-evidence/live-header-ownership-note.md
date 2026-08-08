# A.6 — Live header ownership note (validation)

During A6.7 live checks on https://dev.biopentra.eu/:

- Locations `menu_1` / `menu_mobile` still point at Main Menu **34** (items 3474/3756/3478/3477).
- `wp_nav_menu( menu=34 )` correctly overlays N1 (**Home → Hem**) and AC1 object titles (**Shop → Butik**).
- The **homepage header** currently renders Elementor mega-menu widget `n-menu` with document-embedded `item_title` values (`Home`, `Shop`, …), not `wp_nav_menu` markup.

Disposition: Elementor document chrome remains **out of A.6** (already covered / Elementor lane). A.6 does not translate Elementor mega-menu titles.
