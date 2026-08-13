# TSC.5 Implementation Baseline

**Status:** Implementation baseline recorded — TSC.5 **COMPLETE** on `main`  
**Branch:** `feature/tsc5-elementor-coverage-expansion`  
**Authoritative plan:** [TSC5_ELEMENTOR_COVERAGE_EXPANSION_IMPLEMENTATION_PLAN.md](TSC5_ELEMENTOR_COVERAGE_EXPANSION_IMPLEMENTATION_PLAN.md)  
**Planning validation:** [TSC5_ELEMENTOR_COVERAGE_EXPANSION_PLANNING_VALIDATION_LOG.md](TSC5_ELEMENTOR_COVERAGE_EXPANSION_PLANNING_VALIDATION_LOG.md)

## Baseline

| Field | Value |
|---|---|
| Starting `main` SHA | `499750bd06f5a958087af3ce1a72c0e6974a8a77` |
| Frozen plan SHA | `499750bd06f5a958087af3ce1a72c0e6974a8a77` |
| Plugin version | **1.3.0** (unchanged) |
| `Migrator::TARGET` | **7** (unchanged) |
| Schema | **STATE A** — no migration |
| ADR | **ADR-0016** — no new ADR |
| EL matrix | EL1–EL31 |
| AC matrix | AC1–AC30 |
| WP ladder | TSC5.0–TSC5.6 |
| TSC.0–TSC.4 | COMPLETE |
| TSC.5 implementation at baseline | NOT STARTED |
| TSC.6 | NOT STARTED |

## Committed eight-widget scope

| Widget | Controls |
|---|---|
| `heading` | `title` |
| `text-editor` | `editor` |
| `button` | `text` |
| `accordion` | `tab_title`, `tab_content` |
| `toggle` | `tab_title`, `tab_content` |
| `image` | `caption` (custom source only) |
| `icon-list` | `text` (repeater `_id`) |
| `call-to-action` | `title`, `description`, `button` |

## Deferred / Unsupported (frozen)

**Deferred:** testimonial, icon-box, image-box, alert, progress, counter, tabs, Elementor Forms, nav-menu, posts, portfolio, loop-grid, third-party widgets, elementor_library, Theme Builder, popups, globals, loop templates.

**Unsupported:** HTML/shortcode widgets, dynamic tags as literal content, Woo Elementor widgets, URL/config objects, CSS/code, direct meta-only `_elementor_data` writes, public Elementor registration API (TSC.6).

## Frozen architecture reminders

- Identity: `e:d:<owner_post_id>:<element_id>:<control_key>[:<nested_item_id>]`
- `source_type=post`; `field_key=_elementor`; no SOURCE_ELEMENTOR
- Invalidation: `save_post` @ 20 fallback + **`elementor/document/after_save` @ 20 authoritative**; shutdown @ 20 sole sync
- No `_elementor_data` meta hook; no `before_save`
- Structural guard: `Translation/Safety/StructuralAttributeGuard` (surface-neutral)
- Render seam: `elementor/frontend/builder_content_data` @ 20; frozen context matrix
- Both Elementor flags default **OFF**
- No canonical `_elementor_data` translated writes

## Explicit STOP boundaries

STOP rather than improvise if implementation requires:

- schema migration / TARGET bump / SOURCE_ELEMENTOR
- widget expansion beyond eight families
- generic HTML translation engine / URL translation
- public Elementor registration API (TSC.6)
- activation default changes / version bump / tag / release / deploy
- weakening frozen invalidation or render-context contracts

## Exact next steps on this branch

Implement TSC5.0–TSC5.6 per the authoritative plan, then validation, independent review, PR, merge, and closure.
