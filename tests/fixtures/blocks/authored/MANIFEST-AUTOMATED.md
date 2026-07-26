# Corpus manifest — CORRECTED (automated/provisional provenance)

**This corrects the header of `MANIFEST.md` in this same directory, which is
FALSE for every row it lists.** `MANIFEST.md`'s header claims each page "was
exported from a page created through the real Gutenberg editor... None of
this markup was hand-written." That claim is wrong. See
[`PROVENANCE_NOTICE.md`](PROVENANCE_NOTICE.md) for the full explanation.
`MANIFEST.md` itself is left unedited — it is preserved as a record of
exactly what `export-corpus-page.sh` produced, false header and all — this
file is the accurate reference to use instead.

## What actually happened

Every page below was created with `wp post create <file> --post_type=page`,
where `<file>` contained block markup composed by hand to approximate known
Gutenberg serialization conventions. No browser, no block editor, and no
editor JavaScript were involved. This is a **provisional, automated** corpus,
not genuine editor output.

The data (post ID, byte count, SHA1) is identical to `MANIFEST.md` — only the
provenance claim differs.

| Slug | Post ID | Bytes | SHA1 |
|---|---|---|---|
| headings-and-paragraphs | 4623 | 667 | e3cdc21fefab4e0ab3fbd9778a4c7735fc22887a |
| nested-group-columns | 4624 | 632 | e16a3e6be493054a0490362f86e9635c9d795c91 |
| list-nested | 4625 | 711 | e54360035887c1ba32b0e2726b4c631f56b289a6 |
| quote-with-citation | 4626 | 258 | ad8b8ea6049a16a5fc280895059853d711d2a596 |
| buttons | 4627 | 489 | 292d6f6881a898c05a65219ab584a6941301c6d6 |
| image-caption-alt | 4628 | 450 | f27b5d69069012c803a04f7b45d2fc563e39ce25 |
| table | 4629 | 454 | edb235277f617ebb41bf84758ff42d97f54ad9ba |
| separator-between-paragraphs | 4630 | 398 | b5129fd94e5abdeccd66b0f40f2b12d506a532f8 |
| html-block | 4631 | 214 | 79541c28bad52d92de19530768e126a42dfb1a44 |
| reusable-block | 4632 | 66 | 095cbc3be4cbe50427201ee378cb74b1ee7d79e2 |
| synced-pattern | 4633 | 33 | fe5e8ae993a454ad71553aa0bf87fd06cc258a66 |
| dynamic-block | 4634 | 68 | b8a60362c6d6338113f38e3b36500932e25f3172 |
| no-op-save-source | 4636 | 529 | 0787c8c517f3f8ee1ad7f5d893ed9162df170b2a |
| no-op-save-after | 4636 | 529 | 0787c8c517f3f8ee1ad7f5d893ed9162df170b2a |

## Status

The authentic-corpus architecture gate (real browser-editor output) remains
**open**. This batch does not satisfy it. See `spike/s5/corpus/CHECKLIST.md`
in the repository for what real editor authoring still needs to happen.
