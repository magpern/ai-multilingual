# ADR-0002 — Prefix-strip routing, not rewrite-rule duplication

## Status
Accepted (Milestone 0).

## Context
Serving `/sv/about/` can be done by registering a parallel set of rewrite rules
per language, or by removing the prefix before WordPress parses the request.

Duplicating rules multiplies the rule set by the number of languages, has to be
regenerated whenever any plugin registers a rule, and still misses code that
matches paths itself.

## Decision
Strip the language prefix from `REQUEST_URI` on `plugins_loaded` priority 999,
before `WP::parse_request()` reads it. Re-add it to generated URLs through the
`home_url` filter.

Priority 999 is the latest point at which every plugin has loaded and the
`locale` filter is still in place before `load_default_textdomain()` runs
(`wp-settings.php:704`).

The `home_url` filter attaches on `parse_request`, not earlier.
`WP::parse_request()` calls `home_url()` and strips that path from the request
URI with an unanchored `|^path|i` pattern; a prefixed value at that moment would
truncate any slug beginning with the language code, turning `/svenska-sidan/`
into `enska-sidan/`.

No rewrite rules are registered and none are flushed.

## Consequences
- Every existing rule works in every language with no duplication.
- There is no rewrite state to corrupt or regenerate.
- Requests that bypass `WP::parse_request()` do not get URL prefixing; the
  language context is still set.
- Translated rewrite bases (`/sv/produkt/`) are not possible under this model
  and would reopen the decision.
