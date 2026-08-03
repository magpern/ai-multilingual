/**
 * Tier 1 mobile subset — iPhone 12 viewport.
 */
import { test, expect } from '../fixtures/f9-test';
import { loadCredentials } from '../helpers/env';
import { Analysis, cloneFixtureForTest, deleteTrackedClone, getSharedFixture } from '../helpers/bootstrap';
import { exportPost, fetchPublicHtml } from '../helpers/wp-cli';
import { duplicateBlock, editBlockText, openBlockEditor, savePost } from '../helpers/editor';

const creds = { ...loadCredentials(), password: '' };

test.describe('F9 Tier 1 mobile', () => {
  test('create + edit + save on mobile viewport', async ({ page }, testInfo) => {
    const postId = cloneFixtureForTest('paragraph_uuid_draft', testInfo);
    try {
      const before = (exportPost(postId, `f9-clone-mobile-edit-${testInfo.testId.slice(0, 8)}-t0`).analysis as unknown as Analysis).blocks[0]?.uuid;
      await openBlockEditor(page, creds, postId);
      await editBlockText(page, 0, 'Mobile viewport edit.');
      await savePost(page);
      const after = exportPost(postId, `f9-clone-mobile-edit-${testInfo.testId.slice(0, 8)}`).analysis as unknown as Analysis;
      expect(after.blocks[0]?.uuid).toBe(before);
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('duplicate on mobile viewport', async ({ page }, testInfo) => {
    const postId = cloneFixtureForTest('duplicate_single_uuid_draft', testInfo);
    try {
      await openBlockEditor(page, creds, postId);
      await duplicateBlock(page, 0, 'core/paragraph');
      await savePost(page);
      const after = exportPost(postId, `f9-clone-mobile-dup-${testInfo.testId.slice(0, 8)}`).analysis as unknown as Analysis;
      expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('frontend spot-check on mobile', async () => {
    const fixture = getSharedFixture('paragraph_translated_pub');
    const html = fetchPublicHtml(`/sv/${fixture.slug}/`);
    expect(html).toContain('Hej stycke');
  });
});
