# Provenance notice — read before using these fixtures

`MANIFEST.md` in this directory carries a static header (written into
`export-corpus-page.sh`) stating that every row "was exported from a page
created through the real Gutenberg editor... None of this markup was
hand-written." **That claim is false for every row currently in this
manifest.**

[CHECKLIST.md](../CHECKLIST.md) required these 13 pages to be authored by a human at the
real wp-admin block editor, specifically because hand-written or
API-synthesized block markup cannot reproduce the editor's own
serialization/normalization behavior, and because the whole point of this
corpus is to be a second, *authentic* evidence set distinct from Phase 0's
hand-built adversarial fixtures.

That requirement was explicitly waived for this branch by direct
instruction: create the corpus automatically via WP-CLI, using generated
Gutenberg block markup, and document the gap instead of refusing. This
notice is that documentation.

## What actually happened

Every page in this manifest was created with `wp post create <file>
--post_type=page`, where `<file>` contained block markup composed by hand
to match known Gutenberg serialization conventions as closely as possible.
No browser, no block editor, and no editor JavaScript were involved at any
point. See the chat report for the per-page exact/approximated breakdown
and specific known risk areas (default block attrs, list/quote nesting
conventions, separator/table default classes, image lightbox attrs).

## Consequence for the no-op-save/diff step

The checklist's no-op-save/diff step exists to catch editor-JS
normalization that fires on every save even with no content change. Since
`no-op-save-after.html` was produced by `wp post update` with byte-identical
content (not a re-save through the editor), it is **trivially identical**
to `no-op-save-source.html` by construction. That identity is not evidence
that the real editor has no save-time normalization — it only shows that
`wp post update` doesn't rewrite content, which was never in question. This
step still needs to be run for real, through the browser editor, before its
conclusion can be trusted.

## Recommendation for Phase 1b

Treat this batch as "best-effort synthetic, structurally valid Gutenberg
documents" rather than "genuine editor output." If Phase 1c conclusions
depend on editor-specific quirks (exact attribute defaults, whitespace,
class lists, or actual save-time normalization behavior), re-run the
original [CHECKLIST.md](../CHECKLIST.md) process by hand at least for a representative subset
and compare against this batch before relying on it.
