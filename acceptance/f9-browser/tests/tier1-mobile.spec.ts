/**
 * Tier 1 mobile subset — iPhone 12 viewport.
 */
import { test, expect } from '../fixtures/f9-test';
import { loadCredentials } from '../helpers/env';
import {
  deletePost,
  exportPost,
  fetchPublicHtml,
  publishPost,
  saveTranslationForPost,
} from '../helpers/wp-cli';
import { duplicateBlock, editBlockText, login, openBlockEditor, savePost } from '../helpers/editor';
import { Analysis, bootstrapProductionPost } from '../helpers/bootstrap';

const creds = { ...loadCredentials(), password: '' };

test.describe('F9 Tier 1 mobile', () => {
  test('create + edit + save on mobile viewport', async ({ page }) => {
    const slug = 'f9-mobile-edit';
    const postId = await bootstrapProductionPost(page, creds, slug, 'F9 Mobile Edit', 'core-paragraph.html');
    const before = (exportPost(postId, `${slug}-t0`).analysis as unknown as Analysis).blocks[0]?.uuid;

    await openBlockEditor(page, creds, postId);
    await editBlockText(page, 0, 'Mobile viewport edit.');
    await savePost(page);

    const after = exportPost(postId, slug).analysis as unknown as Analysis;
    expect(after.blocks[0]?.uuid).toBe(before);
    deletePost(postId);
  });

  test('duplicate on mobile viewport', async ({ page }) => {
    const slug = 'f9-mobile-dup';
    const postId = await bootstrapProductionPost(page, creds, slug, 'F9 Mobile Dup', 'duplicate-single.html');
    await openBlockEditor(page, creds, postId);
    await duplicateBlock(page, 0, 'core/paragraph');
    await savePost(page);
    const after = exportPost(postId, slug).analysis as unknown as Analysis;
    expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);
    deletePost(postId);
  });

  test('frontend spot-check on mobile', async ({ page }) => {
    const slug = 'f9-mobile-fe';
    const postId = await bootstrapProductionPost(page, creds, slug, 'F9 Mobile FE', 'core-paragraph.html');
    publishPost(postId, slug);
    saveTranslationForPost(postId, `${slug}-pub`, '<p>Hej mobil</p>');

    const html = fetchPublicHtml(`/sv/${slug}/`);
    expect(html).toContain('Hej mobil');
    deletePost(postId);
  });
});
