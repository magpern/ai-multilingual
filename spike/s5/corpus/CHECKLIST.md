# Spike S5 — manual corpus authoring checklist

**This is the Phase 1a hold point.** Everything below requires a human at the
real Gutenberg editor. I cannot drive a browser, and hand-written block markup
is explicitly disallowed as a substitute — the whole point of this corpus is
that it is genuine editor output, quirks included (see
`docs/spikes/S5-gutenberg-segment-identity.md` once drafted, and the corpus
rule in the accepted plan: "every block in the corpus originates from a real
editor save").

## Why this exists

The dev site has essentially no Gutenberg content to harvest — 4 published
posts contain any `<!-- wp:` markup at all, the largest 203 bytes (this is an
Elementor site). Phase 0's assembly-safety tests used hand-built adversarial
fixtures instead, and that evidence is explicitly labeled separately from this
authentic corpus in the report. Once these exports land, Phase 1b reruns the
Phase 0 assembly tests against them as a **second, distinct evidence set**
before the assembly recommendation is finalized.

## What to do, per page

1. Create a new **draft** page on the dev site (wp-admin → Pages → Add New).
2. Give it the title shown below and build exactly the block structure
   described, using the block editor normally — type it in, don't paste
   pre-made HTML.
3. Save as draft (or publish; either is fine — the export script accepts
   both). Note the numeric post ID (visible in the edit-page URL, or via
   `wp post list`).
4. Export it:
   ```bash
   cd /opt/biopentra/dev/ai-multilingual
   spike/s5/tools/export-corpus-page.sh <post_id> <slug>
   ```
   using the exact `<slug>` given for that page below. The script refuses to
   overwrite an existing export and warns if the exported content has no
   `<!-- wp:` markers at all (a sign something didn't save as blocks).
5. Leave the draft page on the dev site for now — it may be referenced again
   if a re-export is needed. All of these are listed as dev-site artifacts to
   clean up after M2 acceptance, the same way `aiml-acceptance-page` was
   tracked for M1.

## Pages needed

| Slug | Title | Build |
|---|---|---|
| `headings-and-paragraphs` | Spike S5 — Headings and paragraphs | An H2, a paragraph, an H3, another paragraph. Plain, no nesting — this is the baseline flat case. |
| `nested-group-columns` | Spike S5 — Nested group and columns | A Group block containing a Columns block with 2 Columns, each column holding one paragraph with distinct text. This is the deepest nesting shape (group → columns → column → paragraph). |
| `list-nested` | Spike S5 — Nested list | A List block with 3 items, where the second item has its own nested sub-list of 2 items. |
| `quote-with-citation` | Spike S5 — Quote with citation | A Quote block with quote text AND a citation (the citation field, not just the quote body — both are separately translatable). |
| `buttons` | Spike S5 — Buttons | A Buttons block containing 2 individual Button blocks with different labels and different URLs (the URL matters — it's what the attrs-fingerprint bucketing in Phase 1c will need to distinguish two buttons with the same label). |
| `image-caption-alt` | Spike S5 — Image with caption and alt text | An Image block with BOTH a caption AND alt text filled in, and both must be different strings from each other. Any image is fine. |
| `table` | Spike S5 — Table | A Table block, 3 columns × 3 rows, with distinct text in each cell (not placeholder "Cell 1/2/3" — real varied sentence fragments help later duplicate-detection tests). |
| `separator-between-paragraphs` | Spike S5 — Separator | Paragraph, then a Separator block, then another paragraph. |
| `html-block` | Spike S5 — Custom HTML | A Custom HTML block with a short raw HTML snippet, e.g. a `<div>` containing a sentence. |
| `reusable-block` | Spike S5 — Reusable block | Create a Reusable block (right-click any block → "Create pattern" / "Add to Reusable blocks" depending on your WP version) containing one paragraph, then insert that SAME reusable block twice on this page. |
| `synced-pattern` | Spike S5 — Synced pattern | Create a synced pattern (Pages → Patterns → Add New, "Synced") containing a paragraph and a heading, then insert it on this page. |
| `dynamic-block` | Spike S5 — Dynamic block | Insert a **Latest Posts** block (`core/latest-posts`) — this is a dynamic block; its saved markup carries attrs only, no useful innerHTML, which is exactly the case the extractor must skip and record why. |
| `no-op-save-source` | Spike S5 — No-op save source | Any 3–4 varied blocks (reuse the `headings-and-paragraphs` structure is fine, or something new). This page is for the manual no-op-save step below, not for its structure specifically. |

That's 13 pages. Take your time — quality and genuineness of the markup
matters more than speed; a page you rushed through in a way that doesn't
reflect normal editing isn't better evidence than not having it yet.

## The no-op save/diff step (also manual)

After exporting `no-op-save-source`:

1. Open that page in the block editor again.
2. Without changing anything, click Save/Update.
3. Export it again to a **second** file so we can diff:
   ```bash
   spike/s5/tools/export-corpus-page.sh <same_post_id> no-op-save-after
   ```
4. Send me both exports (or just tell me they're in place — I'll diff
   `no-op-save-source.html` against `no-op-save-after.html` myself).

If the editor's own normalization changed anything on a save where you changed
nothing, every reconciliation strategy in Phase 1c inherits spurious staleness
from that alone, and the report has to say so.

## What happens after you hand these back

I resume at Phase 1b: import these into `tests/fixtures/blocks/`, build the
100/500/1000-block scale documents by deterministically repeating and
permuting this real content, run the no-op-save diff analysis, and re-run the
Phase 0 assembly tests against this corpus as the second, authentic-corpus
evidence set — all before Phase 1c (strategy evaluation) begins.
