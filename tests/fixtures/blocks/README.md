# Block fixtures — read the provenance notice first

**`authored/` is a PROVISIONAL, WP-CLI-GENERATED corpus. It is NOT genuine
Gutenberg browser-editor output.** See
[`authored/PROVENANCE_NOTICE.md`](authored/PROVENANCE_NOTICE.md) before using
anything in this directory as evidence about editor serialization behaviour,
save-time normalization, or the authentic-corpus architecture gate — none of
those are satisfied by this batch.

In short: `authored/*.html` was created with `wp post create`/`wp post
update`, using block markup composed by hand to approximate known Gutenberg
conventions. No browser, no block editor, and no editor JavaScript were
involved. `authored/MANIFEST.md` carries a static header (from
`export-corpus-page.sh`) claiming real-editor provenance for every row — that
claim is false for this batch. It is left unedited, as a record of exactly
what the tool produced; **use [`authored/MANIFEST-AUTOMATED.md`](authored/MANIFEST-AUTOMATED.md)
instead**, which has the same data with an accurate header.
`PROVENANCE_NOTICE.md` is the fuller correction, and both files must travel
together wherever these fixtures are copied or referenced.
