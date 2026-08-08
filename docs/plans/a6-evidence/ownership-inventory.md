# A.6 — Live ownership inventory

**Environment:** https://dev.biopentra.eu  
**Captured:** 2026-08-08  
**Baseline:** `main` @ `063a5d1bc40c7a6b46c0856c173199b77b2e37c2`  
**Theme:** Blocksy Child — BioPentra (`blocksy-child`); parent Blocksy + Blocksy Companion  
**Menus:** Main Menu `term_id=34` on `menu_1` + `menu_mobile` (4 items)  
**Home / blog:** home=`4444`, blog=`557`

Hard rule: visible placement on a URL does **not** imply AIML ownership. Owner = who owns the string persistence / official render seam.

---

## 1. WordPress Core

| Surface | Live finding | Owner | Overlay seam | Disposition |
|---|---|---|---|---|
| Navigation menus | Menu 34: Home, Shop, News, Contact | WP `nav_menu_item` posts | `the_title` / `nav_menu_item_title` | **Supported** when custom title (see §2) |
| Linked object titles in menus | Shop/News/Contact use empty custom `post_title` → object title | Page/product title pipeline (already AIML) | `the_title` on object ID | **Already covered** — not re-admitted |
| Widgets (block) | Active sidebars use `widget_block-*` | Gutenberg block markup in `widget_block` option | No post-scoped extract path | **Deferred** |
| Classic Woo products widget | `woocommerce_products-2` title “Products” | WooCommerce widget | Widget title filters (not inventoried as Woo A.7) | **Deferred** (low value; residual) |
| Search form | No `get_search_form` theme override filter count; storefront owns product search shortcodes | WP gettext / theme / storefront | Gettext-only or wrong owner | **Deferred** / wrong owner |
| Comments | `default_comment_status` empty; not a live visitor path | WP | — | **Deferred** (inactive) |
| Archives / calendar | Present only as block-widget headings/demo copy | Block widgets | — | **Deferred** |
| Login links | Blocksy account element + storefront header-auth | Theme / Elementor / storefront | — | **Deferred** / wrong owner |
| Password-protected UI | `password_posts=0` | WP | — | **Deferred** (no live content) |
| Pagination | Blocksy replaces Woo loop pagination (A.7b evidence) | Blocksy | Theme gettext / markup | **Deferred** |
| RSS | Not a Biopentra visitor chrome priority | WP | — | **Deferred** |
| Search results chrome | Product search dominated by storefront/loop-card | storefront / loop-card / Woo | — | Wrong owner (see A.7b) |
| 404 page chrome | `blocksy/404.php` | Blocksy | Template gettext | **Deferred** |

---

## 2. Navigation detail (Supported candidate)

| Item ID | Display title | `post_title` | Object title | Custom? |
|---|---|---|---|---|
| 3474 | Home | Home | Private: SiteHome | **Yes** |
| 3756 | Shop | *(empty)* | Shop | No — object title |
| 3478 | News | *(empty)* | Private: News | No — object title |
| 3477 | Contact | *(empty)* | Contact | No — object title |

AIML `Renderer::filter_title` **explicitly skips** `nav_menu_item` today ([HOOKS.md](../../HOOKS.md)). That skip is the A.6 nav gap for custom titles.

WordPress setup behavior (core):

- Custom `post_title` → `the_title( $post_title, $menu_item_id )`
- Empty custom title → `the_title( object_title, $object_id )` (already overlayable)

---

## 3. Theme — Blocksy

| Surface | Live finding | Owner | Disposition |
|---|---|---|---|
| Header builder | `theme_mods_blocksy.header_placements` — logo, menu, search, account, cart, text, socials, offcanvas trigger | Blocksy | Theme-owned |
| Header promotional text | `header_text` = `Free shipping over €200` (builder HTML) | Blocksy theme_mod | **Deferred** (site-global Store host gap) |
| Footer builder | `footer_placements` — widget areas + copyright | Blocksy | Theme-owned |
| Copyright | `copyright_text` = `Copyright © {current_year} - Biopentra` | Blocksy theme_mod | **Deferred** (site-global) |
| Breadcrumbs | Shortcode `blocksy_breadcrumbs` registered | Blocksy | **Deferred** (gettext / shortcode without declared AIML identity) |
| Search overlay / mobile / offcanvas | Header placements include `search`, `trigger`, `offcanvas` | Blocksy gettext + builder | **Deferred** |
| Pagination | Theme templates (A.7b B5) | Blocksy | **Deferred** |
| Theme widgets / sidebars | `sidebar-1`, `sidebar-woocommerce`, `ct-footer-sidebar-*` | Block widgets + Woo widget | **Deferred** |

Theme-owned strings remain theme-owned. AIML overlays only where a frozen admission exists.

---

## 4. Elementor (verify only — do not re-admit documents)

| Finding | Notes |
|---|---|
| Document content | Already A.2–A.3 / Elementor identity (ADR-0016) |
| Header auth | Elementor library **Header #3782** embeds biopentra header-auth controls | Elementor-owned widget settings — **not** A.6; allowlist gaps are Elementor coverage, not visitor-chrome ownership theft |
| Shop / landing documents | Remain Elementor-owned | Out of A.6 |

---

## 5. WooCommerce (verify residual vs A.7*)

| Surface | Status |
|---|---|
| A.7a catalog content | Complete — out of A.6 |
| A.7b archive orderby labels | Complete — out of A.6 |
| A.7c journey labels | Complete — out of A.6 |
| A.7d email subject/heading | Complete — out of A.6 |
| Deferred A.7* gettext (cart, notices, email body, …) | Remain Woo Deferred — **not** stolen into A.6 |
| Products widget title | Residual classic widget — **Deferred** in A.6 |

---

## 6. Fluent Forms (verify)

| Surface | Status |
|---|---|
| Contact Form #5 labels/submit | A.8 Complete — out of A.6 |
| Other forms / placeholders / validation | A.8 Deferred — out of A.6 |
| Remaining visitor chrome | **None** for A.6 to admit |

---

## 7. First-party plugins

### biopentra-storefront

| Surface | Owner | Seam | Disposition |
|---|---|---|---|
| `biopentra_home_search` / `biopentra_shop_search` / `biopentra_search_refine` | storefront | `esc_attr__` / `esc_html__` inside HTML builders | **Deferred** (gettext-only; no owner-declared overlay filter) |
| `biopentra_header_auth` shortcode + Elementor widget | storefront / Elementor | Elementor controls or shortcode render | Elementor path or **Deferred** |
| `biopentra_footer_email` | storefront | Shortcode gettext | **Deferred** |
| Technical SEO module strings | storefront | SEO lane | **Out of scope** (A.SEO) |
| Admin stock-display settings | storefront | Admin | **Out of scope** |

### biopentra-loop-card

| Surface | Owner | Disposition |
|---|---|---|
| Card CTAs / live-search i18n (`Searching…`, `Add to cart`, …) | loop-card | **Deferred** — wrong owner for WP chrome; commerce card UI (A.7b already excluded) |

---

## 8. Third-party visitor chrome (not A.6)

| Plugin | Notes |
|---|---|
| Age Gate | Active; shared-definition `age_gate_messages` options — A.8 selection already Deferred (Store host). Track under production integrations, not A.6 |
| Cookie Law / CookieYes | Active; A.8 rejected (no official overlay filters) |
| Rank Math | A.SEO only |

---

## 9. Ownership summary

| Owner | May A.6 admit? |
|---|---|
| WordPress (`nav_menu_item` custom titles) | **Yes** — Supported |
| WordPress gettext core UI | Only with deterministic identity + filter — none proven → Deferred |
| Blocksy | Theme-owned; site-global mods Deferred |
| Gutenberg (post documents) | Already covered — do not steal |
| Gutenberg (widget_block option) | Deferred (no post host) |
| Elementor | Verify only |
| WooCommerce | Verify residual only; do not re-open A.7 Deferred |
| Fluent Forms | None remaining |
| biopentra-storefront / loop-card | Deferred (gettext / wrong lane) |
| AIML | Overlay for admitted surfaces only |
