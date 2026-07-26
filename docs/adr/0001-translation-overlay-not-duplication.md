# ADR-0001 — Translation overlay, not object duplication

## Status
Accepted (Milestone 0).

## Context
Multilingual plugins broadly split into two camps: duplicate the post per
language, or store translations separately and apply them at render time.
Duplication is simpler to build — every language is just another post — but it
multiplies the object graph. For a WooCommerce catalogue that means duplicate
products, variations, inventory, SKUs and prices, and a permanent
synchronisation problem between them.

## Decision
There is exactly one canonical WordPress object per piece of content.
Translations live in custom tables and are applied through filters while the
page renders.

`src/` never calls `wp_insert_post`, `wp_update_post`, `wp_insert_term` or a
WooCommerce setter, and never writes to `wp_posts`, `wp_postmeta`, `wp_terms` or
`wp_termmeta`. Products, variations, inventory, stock, prices, SKUs, orders,
reviews, media, tax and shipping data are never duplicated.

## Consequences
- IDs, permalinks, relationships and inventory keep working unchanged.
- Deactivating the plugin restores the previous behaviour with no cleanup.
- Overlays must be applied everywhere content is read, which means finding the
  right filter for each surface — more integration points than duplication
  needs.
- WooCommerce is safe by construction: `WC_Data::get_prop()` applies
  `woocommerce_{type}_get_{prop}` only in `view` context, so admin screens and
  internal calculations never see a translated value.
