/**
 * Tier 1 cross-browser subset — Firefox and WebKit desktop.
 */
import { test, expect } from '@playwright/test';
import { loadCredentials } from '../helpers/env';
import { deletePost, exportPost } from '../helpers/wp-cli';
import {
  blockCount,
  deleteBlock,
  duplicateBlock,
  editBlockText,
  login,
  openBlockEditor,
  savePost,
  undo,
} from '../helpers/editor';
import { Analysis, bootstrapProductionPost } from '../helpers/bootstrap';

const creds = { ...loadCredentials(), password: '' };

test.describe('F9 Tier 1 cross-browser', () => {
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

  test('duplicate repairs UUID collisions', async ({ page }) => {
    test.setTimeout(600000);
    const slug = 'f9-tier1-dup';
    const postId = await bootstrapProductionPost(page, creds, slug, 'F9 Tier1 Dup', 'duplicate-single.html');
    await openBlockEditor(page, creds, postId);
    await duplicateBlock(page, 0, 'core/paragraph');
    await expect.poll(async () => blockCount(page, 'core/paragraph'), { timeout: 60000 }).toBeGreaterThan(1);
    await savePost(page);
    const after = exportPost(postId, slug).analysis as unknown as Analysis;
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
