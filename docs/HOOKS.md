# Hooks

Every hook this plugin registers, why, and how it gates itself. Filters attach
unconditionally and short-circuit at call time, so the registered set does not
vary by request type.

## Routing — `src/Routing/Router.php`

| Hook | Priority | Purpose |
|---|---|---|
| `plugins_loaded` | 999 | Resolve the language, strip the prefix from `REQUEST_URI`, attach the filters below. Late enough that every plugin has loaded; early enough that `locale` is in place before `load_default_textdomain()` and before `WP::parse_request()` reads the URI. |
| `locale` | 10 | Serve the active language's locale. |
| `language_attributes` | 10 | Emit `lang` and `dir`. |
| `redirect_canonical` | 10 | Return `false` for prefixed requests so core cannot "correct" the URL back and loop. |
| `parse_request` | 0 | Attach the `home_url` filter — **after** routing. See below. |
| `home_url` | 10 | Prefix generated URLs. Skips `/wp-admin`, `/wp-login.php` and the REST namespace. |

Routing is skipped entirely for admin, REST, AJAX, cron, WP-CLI, XML-RPC and the
login screen.

**Why `home_url` attaches late.** `WP::parse_request()` calls `home_url()` and
strips that path from the request URI using an unanchored `|^path|i` pattern. If
the filter were live at that moment, a Swedish request for a page whose slug
merely starts with the language code would be truncated — `/svenska-sidan/`
becomes `enska-sidan/` and 404s. `parse_request` fires at the very end of
`WP::parse_request()`, after the home path has been read.

## Overlays — `src/Translation/Renderer.php`

| Hook | Priority | Notes |
|---|---|---|
| `the_title` | 10 (2 args) | Skips `nav_menu_item`; menus are a later milestone. |
| `the_content` | **1** | Must run before core's `apply_block_hooks_to_content_from_post_object` (8) and `do_blocks` (9). Only substitutes when the incoming string is byte-identical to the queried post's raw `post_content`, because plugins apply this filter to arbitrary strings. |
| `get_the_excerpt` | 10 (2 args) | Manual excerpts only; a generated excerpt derives from the already-translated body. |
| `document_title_parts` | 20 | Singular views only. |

All four are inert unless a non-default language is resolved, and always inert
in the admin. A re-entrancy flag prevents a filter from re-entering itself.

## Lifecycle and content — `src/Plugin.php`

| Hook | Priority | Purpose |
|---|---|---|
| `save_post` | 20 (2 args) | Stale detection. Re-extracts the source, recomputes hashes per format and flags changed segments. Never touches translated text or status. Skips revisions and autosaves. |
| `admin_init` | 10 | Schema drift check. Bind-mount deployments update files in place and never fire the activation hook, so this is the only thing that migrates them. |
| `before_woocommerce_init` | 10 | Declare HPOS compatibility when WooCommerce is present. |

## Front end — `src/Frontend/Switcher.php`

| Hook | Purpose |
|---|---|
| `aiml_switcher` (shortcode) | Renders the switcher. |
| `wp_nav_menu_items` | Appends the switcher to a menu, opt-in only. |

## Admin — `src/Admin/`

| Hook | Capability |
|---|---|
| `admin_menu`, `admin_init` | — |
| `admin_post_aiml_save_language` | `manage_options` + nonce |
| `admin_post_aiml_delete_language` | `manage_options` + nonce |
| `admin_post_aiml_save_translation` | `aiml_translate` + nonce |

No `admin_post_nopriv_*` counterpart exists for any handler, so none is
reachable while logged out.

## Filters this plugin provides

| Filter | Default | Purpose |
|---|---|---|
| `aiml_switcher_in_menu` | `false` | Whether to append the language switcher to a given nav menu. Receives the `wp_nav_menu()` args. |

## Deliberately not hooked

- **No rewrite rules.** `add_rewrite_rule`, `add_rewrite_tag` and
  `flush_rewrite_rules` are absent by design (ADR-0002); prefix stripping means
  there is no rewrite state to manage.
- **No cookie.** The URL is the only language authority in this milestone, so
  front-end responses carry no `Set-Cookie` and stay cacheable at the edge.
- **No REST routes.** Milestone 1 uses conventional admin forms; REST arrives
  with the segment editor that needs it.
- **No deactivation hook.** Deactivation must remove nothing, so there is
  nothing for it to do.
- **No WooCommerce hooks yet** beyond the compatibility declaration.
