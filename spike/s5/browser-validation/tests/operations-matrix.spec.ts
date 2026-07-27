/**
 * Tier 1 mandatory identity operations, browser-authored. THROWAWAY — spike/s5 only.
 *
 * Methodology fix (Phase 3): every case creates a FRESH post, snapshots
 * post_content to a FILE before any browser interaction (`snapshotBaseline`),
 * performs the operation, saves, then re-exports and diffs the CURRENT DB
 * row against that frozen snapshot file — never against itself. See
 * wp-cli.ts `exportPost` doc comment for why the Phase 2 self-comparison
 * pattern (`baselineId === postId`) was invalid.
 */
import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { CORPUS_DIR } from '../helpers/env';
import { createTaggedPost, exportPost, snapshotBaseline, deletePost } from '../helpers/wp-cli';
import {
  login,
  openBlockEditor,
  savePost,
  editBlockText,
  duplicateBlock,
  copyBlock,
  pasteAtEnd,
  moveBlockDown,
  wrapInGroup,
  unwrapFromGroup,
  transformBlock,
  splitParagraph,
  mergeParagraphBackward,
  undo,
  redo,
  deleteBlock,
  canvas,
  assertNoBlockRecovery,
  blockCount,
} from '../helpers/editor';

type BlockAnalysis = { uuid: string; valid_uuid: boolean; block_name: string; text_sha1: string };
type Analysis = {
  content_sha1: string;
  content_identical?: boolean;
  eligible_blocks: number;
  blocks: BlockAnalysis[];
  duplicate_uuids: Record<string, number>;
  has_aimlBlockId: boolean;
  uuid_preservation?: string[];
};

const results: Record<string, unknown> = {};

test.describe('Strategy F Tier-1 operations matrix (browser-authored)', () => {
  // Cookie-based auth (see helpers/editor.ts login()) — no user/pass needed
  // inside the Playwright container, which has no access to
  // .admin-credentials (that file is only used by host-side WP-CLI calls).
  const creds = { baseUrl: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu', user: '', password: '' };

  test.afterAll(() => {
    fs.mkdirSync(CORPUS_DIR, { recursive: true });
    fs.writeFileSync(path.join(CORPUS_DIR, 'operations-matrix-results.json'), JSON.stringify(results, null, 2));
  });

  test('op-text-edit: full text rewrite changes text_sha1, preserves UUID', async ({ page }) => {
    const slug = 'op-text-edit';
    const postId = createTaggedPost(slug, 'S5 Ops — Text edit', 'core-paragraph.html');
    const baseline = snapshotBaseline(postId, slug);
    const beforeAnalysis = exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis;

    await login(page, creds);
    await openBlockEditor(page, creds, postId);
    await editBlockText(page, 0, 'Fully rewritten paragraph text.');
    await savePost(page);
    await assertNoBlockRecovery(page);

    const after = exportPost(postId, slug, baseline.containerPath).analysis as unknown as Analysis;

    const textChanged = after.blocks[0]?.text_sha1 !== beforeAnalysis.blocks[0]?.text_sha1;
    const uuidPreserved = after.blocks[0]?.uuid === beforeAnalysis.blocks[0]?.uuid;

    results[slug] = {
      post_id: postId,
      before_uuid: beforeAnalysis.blocks[0]?.uuid,
      after_uuid: after.blocks[0]?.uuid,
      before_text_sha1: beforeAnalysis.blocks[0]?.text_sha1,
      after_text_sha1: after.blocks[0]?.text_sha1,
      text_actually_changed: textChanged,
      uuid_preserved: uuidPreserved,
      content_identical: after.content_identical,
      classification: uuidPreserved && textChanged ? 'same_logical_block, uuid_preserved' : 'ambiguous',
    };

    expect(textChanged, 'text must actually change for this to be a valid text-edit test').toBe(true);
    expect(uuidPreserved).toBe(true);
    expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);

    deletePost(postId);
  });

  test('op-duplicate: duplicate block copies UUID (pre-repair evidence)', async ({ page }) => {
    const slug = 'op-duplicate';
    const postId = createTaggedPost(slug, 'S5 Ops — Duplicate', 'duplicate-single.html');
    const baseline = snapshotBaseline(postId, slug);
    const beforeAnalysis = exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis;

    await login(page, creds);
    await openBlockEditor(page, creds, postId);
    const countBefore = await blockCount(page, 'core/paragraph');
    await duplicateBlock(page, 0, 'core/paragraph');
    const countAfterDom = await blockCount(page, 'core/paragraph');
    await savePost(page);
    await assertNoBlockRecovery(page);

    const after = exportPost(postId, slug, baseline.containerPath).analysis as unknown as Analysis;
    const dupeUuids = Object.keys(after.duplicate_uuids);

    results[slug] = {
      post_id: postId,
      before_block_count: beforeAnalysis.eligible_blocks,
      dom_count_before: countBefore,
      dom_count_after_duplicate: countAfterDom,
      after_block_count: after.eligible_blocks,
      before_uuid: beforeAnalysis.blocks[0]?.uuid,
      after_uuids: after.blocks.map((b) => b.uuid),
      duplicate_uuids_detected: after.duplicate_uuids,
      block_grew: after.eligible_blocks > beforeAnalysis.eligible_blocks,
      gutenberg_copied_uuid: dupeUuids.length > 0,
      classification: dupeUuids.length > 0 ? 'new_logical_block, uuid_copied' : 'new_logical_block, uuid_absent_or_regenerated',
    };

    expect(countAfterDom, 'duplicate must actually add a DOM block').toBeGreaterThan(countBefore);
    expect(after.eligible_blocks, 'duplicate must actually persist to post_content').toBeGreaterThan(beforeAnalysis.eligible_blocks);

    deletePost(postId);
  });

  test('op-duplicate-nested-subtree: duplicating a Group copies all descendant UUIDs', async ({ page }) => {
    const slug = 'op-duplicate-nested';
    const postId = createTaggedPost(slug, 'S5 Ops — Duplicate nested', 'nested-subtree.html');
    const baseline = snapshotBaseline(postId, slug);
    const beforeAnalysis = exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis;

    await login(page, creds);
    await openBlockEditor(page, creds, postId);
    await duplicateBlock(page, 0, 'core/group');
    await savePost(page);
    await assertNoBlockRecovery(page);

    const after = exportPost(postId, slug, baseline.containerPath).analysis as unknown as Analysis;
    const dupeUuids = Object.keys(after.duplicate_uuids);

    results[slug] = {
      post_id: postId,
      before_block_count: beforeAnalysis.eligible_blocks,
      after_block_count: after.eligible_blocks,
      duplicate_uuids_detected: after.duplicate_uuids,
      all_descendants_duplicated: after.eligible_blocks === beforeAnalysis.eligible_blocks * 2,
      classification: dupeUuids.length > 0 ? 'subtree_duplicated, uuids_copied' : 'subtree_duplicated, uuids_regenerated_by_gutenberg',
    };

    expect(after.eligible_blocks).toBeGreaterThan(beforeAnalysis.eligible_blocks);

    deletePost(postId);
  });

  test('op-copy-paste-same-post: copy block then paste at end within same post', async ({ page }) => {
    const slug = 'op-copy-paste-same';
    const postId = createTaggedPost(slug, 'S5 Ops — Copy paste same post', 'core-paragraph.html');
    const baseline = snapshotBaseline(postId, slug);
    const beforeAnalysis = exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis;

    await login(page, creds);
    await openBlockEditor(page, creds, postId);
    await copyBlock(page, 0, 'core/paragraph');
    await pasteAtEnd(page);
    await savePost(page);
    await assertNoBlockRecovery(page);

    const after = exportPost(postId, slug, baseline.containerPath).analysis as unknown as Analysis;
    const dupeUuids = Object.keys(after.duplicate_uuids);

    results[slug] = {
      post_id: postId,
      before_block_count: beforeAnalysis.eligible_blocks,
      after_block_count: after.eligible_blocks,
      after_uuids: after.blocks.map((b) => b.uuid),
      duplicate_uuids_detected: after.duplicate_uuids,
      paste_copied_uuid: dupeUuids.length > 0,
    };

    expect(after.eligible_blocks, 'paste must actually add a block').toBeGreaterThan(beforeAnalysis.eligible_blocks);

    deletePost(postId);
  });

  test('op-reorder-adjacent: move block down swaps document order, UUIDs travel with content', async ({ page }) => {
    const slug = 'op-reorder-adjacent';
    const postId = createTaggedPost(slug, 'S5 Ops — Reorder adjacent', 'operations-multi-paragraph.html');
    const baseline = snapshotBaseline(postId, slug);
    const beforeAnalysis = exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis;

    await login(page, creds);
    await openBlockEditor(page, creds, postId);
    await moveBlockDown(page, 0, 'core/paragraph');
    await savePost(page);
    await assertNoBlockRecovery(page);

    const after = exportPost(postId, slug, baseline.containerPath).analysis as unknown as Analysis;

    const firstBlockUuidBefore = beforeAnalysis.blocks[0]?.uuid;
    const firstBlockUuidAfter = after.blocks[0]?.uuid;
    const secondBlockUuidAfter = after.blocks[1]?.uuid;

    results[slug] = {
      post_id: postId,
      before_order: beforeAnalysis.blocks.map((b) => ({ uuid: b.uuid, text_sha1: b.text_sha1 })),
      after_order: after.blocks.map((b) => ({ uuid: b.uuid, text_sha1: b.text_sha1 })),
      order_changed: firstBlockUuidBefore !== firstBlockUuidAfter,
      uuid_travels_with_content: secondBlockUuidAfter === firstBlockUuidBefore,
      duplicate_uuids: after.duplicate_uuids,
    };

    expect(firstBlockUuidBefore).not.toBe(firstBlockUuidAfter);
    expect(secondBlockUuidAfter).toBe(firstBlockUuidBefore);
    expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);

    deletePost(postId);
  });

  test('op-reorder-nonadjacent: move first block down twice (non-adjacent reorder)', async ({ page }) => {
    const slug = 'op-reorder-nonadjacent';
    const postId = createTaggedPost(slug, 'S5 Ops — Reorder non-adjacent', 'operations-multi-paragraph.html');
    const baseline = snapshotBaseline(postId, slug);
    const beforeAnalysis = exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis;

    await login(page, creds);
    await openBlockEditor(page, creds, postId);
    await moveBlockDown(page, 0, 'core/paragraph');
    await moveBlockDown(page, 0, 'core/paragraph');
    await savePost(page);
    await assertNoBlockRecovery(page);

    const after = exportPost(postId, slug, baseline.containerPath).analysis as unknown as Analysis;
    const firstBlockUuidBefore = beforeAnalysis.blocks[0]?.uuid;
    const thirdBlockUuidAfter = after.blocks[2]?.uuid;

    results[slug] = {
      post_id: postId,
      before_order: beforeAnalysis.blocks.map((b) => b.uuid),
      after_order: after.blocks.map((b) => b.uuid),
      moved_block_now_last: thirdBlockUuidAfter === firstBlockUuidBefore,
      duplicate_uuids: after.duplicate_uuids,
    };

    expect(thirdBlockUuidAfter).toBe(firstBlockUuidBefore);
    expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);

    deletePost(postId);
  });

  test('op-wrap-in-group: wrapping a paragraph in a Group preserves its UUID', async ({ page }) => {
    const slug = 'op-wrap-group';
    const postId = createTaggedPost(slug, 'S5 Ops — Wrap in group', 'core-paragraph.html');
    const baseline = snapshotBaseline(postId, slug);
    const beforeAnalysis = exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis;

    await login(page, creds);
    await openBlockEditor(page, creds, postId);
    await wrapInGroup(page, 0, 'core/paragraph');
    await savePost(page);
    await assertNoBlockRecovery(page);

    const after = exportPost(postId, slug, baseline.containerPath).analysis as unknown as Analysis;
    const paragraphUuidAfter = after.blocks.find((b) => b.block_name === 'core/paragraph')?.uuid;

    results[slug] = {
      post_id: postId,
      before_uuid: beforeAnalysis.blocks[0]?.uuid,
      after_blocks: after.blocks.map((b) => ({ block_name: b.block_name, uuid: b.uuid })),
      paragraph_uuid_preserved: paragraphUuidAfter === beforeAnalysis.blocks[0]?.uuid,
    };

    expect(paragraphUuidAfter, 'nesting change must preserve child UUID').toBe(beforeAnalysis.blocks[0]?.uuid);

    deletePost(postId);
  });

  test('op-unwrap-from-group: ungrouping preserves child paragraph UUID', async ({ page }) => {
    const slug = 'op-unwrap-group';
    const postId = createTaggedPost(slug, 'S5 Ops — Unwrap from group', 'core-group.html');
    const baseline = snapshotBaseline(postId, slug);
    const beforeAnalysis = exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis;
    const paragraphUuidBefore = beforeAnalysis.blocks.find((b) => b.block_name === 'core/paragraph')?.uuid;

    await login(page, creds);
    await openBlockEditor(page, creds, postId);
    await unwrapFromGroup(page, 0);
    await savePost(page);
    await assertNoBlockRecovery(page);

    const after = exportPost(postId, slug, baseline.containerPath).analysis as unknown as Analysis;
    const paragraphUuidAfter = after.blocks.find((b) => b.block_name === 'core/paragraph')?.uuid;

    results[slug] = {
      post_id: postId,
      paragraph_uuid_before: paragraphUuidBefore,
      after_blocks: after.blocks.map((b) => ({ block_name: b.block_name, uuid: b.uuid })),
      paragraph_uuid_preserved: paragraphUuidAfter === paragraphUuidBefore,
      group_removed: !after.blocks.some((b) => b.block_name === 'core/group'),
    };

    expect(paragraphUuidAfter).toBe(paragraphUuidBefore);

    deletePost(postId);
  });

  test('op-transform-paragraph-to-heading: block-type conversion', async ({ page }) => {
    const slug = 'op-transform-p2h';
    const postId = createTaggedPost(slug, 'S5 Ops — Transform p2h', 'core-paragraph.html');
    const baseline = snapshotBaseline(postId, slug);
    const beforeAnalysis = exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis;

    await login(page, creds);
    await openBlockEditor(page, creds, postId);
    await transformBlock(page, 0, 'core/paragraph', /heading/i);
    await savePost(page);
    await assertNoBlockRecovery(page);

    const after = exportPost(postId, slug, baseline.containerPath).analysis as unknown as Analysis;
    const headingBlock = after.blocks.find((b) => b.block_name === 'core/heading');

    results[slug] = {
      post_id: postId,
      before_block_name: beforeAnalysis.blocks[0]?.block_name,
      before_uuid: beforeAnalysis.blocks[0]?.uuid,
      after_block_name: headingBlock?.block_name ?? after.blocks[0]?.block_name,
      after_uuid: headingBlock?.uuid ?? after.blocks[0]?.uuid,
      uuid_transferred: (headingBlock?.uuid ?? after.blocks[0]?.uuid) === beforeAnalysis.blocks[0]?.uuid,
      classification: (headingBlock?.uuid === beforeAnalysis.blocks[0]?.uuid) ? 'block_transformed, uuid_preserved' : 'block_transformed, uuid_dropped',
    };

    deletePost(postId);
  });

  test('op-transform-paragraph-to-quote: block-type conversion', async ({ page }) => {
    const slug = 'op-transform-p2q';
    const postId = createTaggedPost(slug, 'S5 Ops — Transform p2quote', 'core-paragraph.html');
    const baseline = snapshotBaseline(postId, slug);
    const beforeAnalysis = exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis;

    await login(page, creds);
    await openBlockEditor(page, creds, postId);
    await transformBlock(page, 0, 'core/paragraph', /quote/i);
    await savePost(page);
    await assertNoBlockRecovery(page);

    const after = exportPost(postId, slug, baseline.containerPath).analysis as unknown as Analysis;
    const quoteBlock = after.blocks.find((b) => b.block_name === 'core/quote');

    results[slug] = {
      post_id: postId,
      before_block_name: beforeAnalysis.blocks[0]?.block_name,
      before_uuid: beforeAnalysis.blocks[0]?.uuid,
      after_block_name: quoteBlock?.block_name ?? after.blocks[0]?.block_name,
      after_uuid: quoteBlock?.uuid ?? after.blocks[0]?.uuid,
      uuid_transferred: (quoteBlock?.uuid ?? after.blocks[0]?.uuid) === beforeAnalysis.blocks[0]?.uuid,
    };

    deletePost(postId);
  });

  test('op-split-paragraph: splitting produces two blocks, verify UUID distribution', async ({ page }) => {
    const slug = 'op-split';
    const postId = createTaggedPost(slug, 'S5 Ops — Split paragraph', 'core-paragraph.html');
    const baseline = snapshotBaseline(postId, slug);
    const beforeAnalysis = exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis;

    await login(page, creds);
    await openBlockEditor(page, creds, postId);
    await splitParagraph(page, 0);
    await savePost(page);
    await assertNoBlockRecovery(page);

    const after = exportPost(postId, slug, baseline.containerPath).analysis as unknown as Analysis;
    const originalUuid = beforeAnalysis.blocks[0]?.uuid;
    const afterUuids = after.blocks.map((b) => b.uuid);

    results[slug] = {
      post_id: postId,
      before_block_count: beforeAnalysis.eligible_blocks,
      after_block_count: after.eligible_blocks,
      original_uuid: originalUuid,
      after_uuids: afterUuids,
      original_uuid_retained_by_first: after.blocks[0]?.uuid === originalUuid,
      second_block_has_new_or_no_uuid: afterUuids[1] !== originalUuid,
      duplicate_uuids: after.duplicate_uuids,
    };

    expect(after.eligible_blocks).toBeGreaterThanOrEqual(beforeAnalysis.eligible_blocks);
    expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);

    deletePost(postId);
  });

  test('op-merge-paragraphs: merging two blocks into one, verify surviving UUID', async ({ page }) => {
    const slug = 'op-merge';
    const postId = createTaggedPost(slug, 'S5 Ops — Merge paragraphs', 'operations-multi-paragraph.html');
    const baseline = snapshotBaseline(postId, slug);
    const beforeAnalysis = exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis;

    await login(page, creds);
    await openBlockEditor(page, creds, postId);
    await mergeParagraphBackward(page, 1);
    await savePost(page);
    await assertNoBlockRecovery(page);

    const after = exportPost(postId, slug, baseline.containerPath).analysis as unknown as Analysis;
    const firstUuidBefore = beforeAnalysis.blocks[0]?.uuid;
    const secondUuidBefore = beforeAnalysis.blocks[1]?.uuid;
    const survivingUuid = after.blocks[0]?.uuid;

    results[slug] = {
      post_id: postId,
      before_block_count: beforeAnalysis.eligible_blocks,
      after_block_count: after.eligible_blocks,
      first_uuid_before: firstUuidBefore,
      second_uuid_before: secondUuidBefore,
      surviving_uuid: survivingUuid,
      merge_kept_first: survivingUuid === firstUuidBefore,
      merge_kept_second: survivingUuid === secondUuidBefore,
      merge_dropped_both: survivingUuid !== firstUuidBefore && survivingUuid !== secondUuidBefore,
      duplicate_uuids: after.duplicate_uuids,
    };

    expect(after.eligible_blocks).toBeLessThan(beforeAnalysis.eligible_blocks);
    expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);

    deletePost(postId);
  });

  test('op-undo-redo: undo restores prior state, redo reapplies edit', async ({ page }) => {
    const slug = 'op-undo-redo';
    const postId = createTaggedPost(slug, 'S5 Ops — Undo redo', 'core-paragraph.html');
    const baseline = snapshotBaseline(postId, slug);
    const beforeAnalysis = exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis;

    await login(page, creds);
    await openBlockEditor(page, creds, postId);
    await editBlockText(page, 0, 'Text edited before undo test.');
    const afterEditText = await canvas(page).locator('[data-type="core/paragraph"]').first().innerText();
    await undo(page);
    const afterUndoText = await canvas(page).locator('[data-type="core/paragraph"]').first().innerText();
    await redo(page);
    const afterRedoText = await canvas(page).locator('[data-type="core/paragraph"]').first().innerText();
    await savePost(page);
    await assertNoBlockRecovery(page);

    const after = exportPost(postId, slug, baseline.containerPath).analysis as unknown as Analysis;

    results[slug] = {
      post_id: postId,
      before_uuid: beforeAnalysis.blocks[0]?.uuid,
      after_uuid: after.blocks[0]?.uuid,
      after_edit_text: afterEditText,
      after_undo_text: afterUndoText,
      after_redo_text: afterRedoText,
      undo_restored_original: afterUndoText === beforeAnalysis.blocks[0] ? true : afterUndoText !== afterEditText,
      redo_reapplied_edit: afterRedoText === afterEditText,
      uuid_preserved_through_undo_redo: after.blocks[0]?.uuid === beforeAnalysis.blocks[0]?.uuid,
    };

    expect(after.blocks[0]?.uuid).toBe(beforeAnalysis.blocks[0]?.uuid);

    deletePost(postId);
  });

  test('op-delete-then-undo: delete followed by undo restores the block and its UUID', async ({ page }) => {
    const slug = 'op-delete-undo';
    const postId = createTaggedPost(slug, 'S5 Ops — Delete then undo', 'core-paragraph.html');
    const baseline = snapshotBaseline(postId, slug);
    const beforeAnalysis = exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis;

    await login(page, creds);
    await openBlockEditor(page, creds, postId);
    const countBefore = await blockCount(page, 'core/paragraph');
    await deleteBlock(page, 0, 'core/paragraph');
    const countAfterDelete = await blockCount(page, 'core/paragraph');
    await undo(page);
    const countAfterUndo = await blockCount(page, 'core/paragraph');
    await savePost(page);
    await assertNoBlockRecovery(page);

    const after = exportPost(postId, slug, baseline.containerPath).analysis as unknown as Analysis;

    results[slug] = {
      post_id: postId,
      count_before: countBefore,
      count_after_delete: countAfterDelete,
      count_after_undo: countAfterUndo,
      before_uuid: beforeAnalysis.blocks[0]?.uuid,
      after_uuid: after.blocks[0]?.uuid,
      uuid_restored: after.blocks[0]?.uuid === beforeAnalysis.blocks[0]?.uuid,
    };

    expect(countAfterDelete).toBeLessThan(countBefore);
    expect(countAfterUndo).toBe(countBefore);
    expect(after.blocks[0]?.uuid).toBe(beforeAnalysis.blocks[0]?.uuid);

    deletePost(postId);
  });
});
