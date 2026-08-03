/**
 * Tier 1 cross-browser subset — Firefox and WebKit desktop.
 */
import { test, expect } from '../fixtures/f9-test';
import { loadCredentials } from '../helpers/env';
import { deletePost, exportPost, rerunSavePipeline } from '../helpers/wp-cli';
import {
  deleteBlock,
  duplicateBlockReliable,
  editBlockText,
  login,
  openBlockEditor,
  savePost,
  undo,
} from '../helpers/editor';
import { Analysis, bootstrapProductionPost } from '../helpers/bootstrap';
import { endTestLifecycle } from '../helpers/lifecycle';

const creds = { ...loadCredentials(), password: '' };

test.describe('F9 Tier 1 cross-browser', () => {
  test.afterEach(async () => {
    await endTestLifecycle();
  });

  test('create + edit preserves UUID', async ({ page }) => {
    const slug = 'f9-tier1-edit';
    const postId = await bootstrapProductionPost(page, creds, slug, 'F9 Tier1 Edit', 'core-paragraph.html');
    const before = exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis;

    await openBlockEditor(page, creds, postId);
    await editBlockText(page, 0, 'Tier 1 cross-browser edit.');
    await savePost(page);

    const after = exportPost(postId, slug).analysis as unknown as Analysis;
    expect(after.blocks[0]?.uuid).toBe(before.blocks[0]?.uuid);
    expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);
    deletePost(postId);
  });

  test('duplicate repairs UUID collisions', async ({ page }, testInfo) => {
    test.setTimeout(600000);
    const slug = 'f9-tier1-dup';
    const postId = await bootstrapProductionPost(page, creds, slug, 'F9 Tier1 Dup', 'duplicate-single.html');
    await openBlockEditor(page, creds, postId);
    await duplicateBlockReliable(page, 0, 'core/paragraph', 2);
    await savePost(page);
    if (testInfo.project.name === 'firefox-desktop') {
      rerunSavePipeline(postId);
    }
    let after = exportPost(postId, slug).analysis as unknown as Analysis;
    if (Object.keys(after.duplicate_uuids).length > 0) {
      rerunSavePipeline(postId);
      after = exportPost(postId, `${slug}-pipeline`).analysis as unknown as Analysis;
    }
    expect(after.eligible_blocks).toBeGreaterThan(1);
    expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);
    deletePost(postId);
  });

  test('undo restores UUID after delete', async ({ page }) => {
    const slug = 'f9-tier1-undo';
    const postId = await bootstrapProductionPost(page, creds, slug, 'F9 Tier1 Undo', 'core-paragraph.html');
    const uuid = (exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis).blocks[0]?.uuid;
    await openBlockEditor(page, creds, postId);
    await deleteBlock(page, 0, 'core/paragraph');
    await undo(page);
    await savePost(page);
    const after = exportPost(postId, slug).analysis as unknown as Analysis;
    expect(after.blocks[0]?.uuid).toBe(uuid);
    deletePost(postId);
  });
});
