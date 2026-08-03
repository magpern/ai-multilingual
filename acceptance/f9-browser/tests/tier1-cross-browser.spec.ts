/**
 * Tier 1 cross-browser subset — Firefox and WebKit desktop.
 */
import { test, expect } from '../fixtures/f9-test';
import { loadCredentials } from '../helpers/env';
import { Analysis, cloneFixtureForTest, deleteTrackedClone } from '../helpers/bootstrap';
import { exportPost, rerunSavePipeline } from '../helpers/wp-cli';
import {
  deleteBlock,
  duplicateBlockReliable,
  editBlockText,
  openBlockEditor,
  savePost,
  undo,
} from '../helpers/editor';
import { endTestLifecycle } from '../helpers/lifecycle';

const creds = { ...loadCredentials(), password: '' };

test.describe('F9 Tier 1 cross-browser', () => {
  test.afterEach(async () => {
    await endTestLifecycle();
  });

  test('create + edit preserves UUID', async ({ page }, testInfo) => {
    const postId = cloneFixtureForTest('paragraph_uuid_draft', testInfo);
    try {
      const before = exportPost(postId, `f9-clone-t1-edit-${testInfo.testId.slice(0, 8)}-t0`).analysis as unknown as Analysis;
      await openBlockEditor(page, creds, postId);
      await editBlockText(page, 0, 'Tier 1 cross-browser edit.');
      await savePost(page);
      const after = exportPost(postId, `f9-clone-t1-edit-${testInfo.testId.slice(0, 8)}`).analysis as unknown as Analysis;
      expect(after.blocks[0]?.uuid).toBe(before.blocks[0]?.uuid);
      expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('duplicate repairs UUID collisions', async ({ page }, testInfo) => {
    test.setTimeout(600000);
    const postId = cloneFixtureForTest('duplicate_single_uuid_draft', testInfo);
    try {
      await openBlockEditor(page, creds, postId);
      await duplicateBlockReliable(page, 0, 'core/paragraph', 2);
      await savePost(page);
      if (testInfo.project.name === 'firefox-desktop') {
        rerunSavePipeline(postId);
      }
      let after = exportPost(postId, `f9-clone-t1-dup-${testInfo.testId.slice(0, 8)}`).analysis as unknown as Analysis;
      if (Object.keys(after.duplicate_uuids).length > 0) {
        rerunSavePipeline(postId);
        after = exportPost(postId, `f9-clone-t1-dup-${testInfo.testId.slice(0, 8)}-pipeline`).analysis as unknown as Analysis;
      }
      expect(after.eligible_blocks).toBeGreaterThan(1);
      expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('undo restores UUID after delete', async ({ page }, testInfo) => {
    const postId = cloneFixtureForTest('paragraph_uuid_draft', testInfo);
    try {
      const uuid = (exportPost(postId, `f9-clone-t1-undo-${testInfo.testId.slice(0, 8)}-t0`).analysis as unknown as Analysis).blocks[0]?.uuid;
      await openBlockEditor(page, creds, postId);
      await deleteBlock(page, 0, 'core/paragraph');
      await undo(page);
      await savePost(page);
      const after = exportPost(postId, `f9-clone-t1-undo-${testInfo.testId.slice(0, 8)}`).analysis as unknown as Analysis;
      expect(after.blocks[0]?.uuid).toBe(uuid);
    } finally {
      deleteTrackedClone(postId);
    }
  });
});
