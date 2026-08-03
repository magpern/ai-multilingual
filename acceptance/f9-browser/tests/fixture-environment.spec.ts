/**
 * F9 fixture environment safety and smoke tests.
 */
import { test, expect } from '../fixtures/f9-test';
import {
  cloneFixtureForTest,
  deleteTrackedClone,
  getSharedFixture,
  validateOrSeedCorpus,
} from '../helpers/fixture-corpus';
import { loadInstrumentation } from '../helpers/fixture-instrumentation';
import {
  loadManifest,
  validateManifestStructure,
} from '../helpers/fixture-manifest';
import {
  contentSha256,
  countTranslationsForPost,
  createDraftPost,
  deleteF9PostsByPrefix,
  deletePost,
  fetchPublicHtml,
  F9_CLONE_PREFIX,
  F9_CORPUS_PREFIX,
  getPostSlug,
  getSettings,
  listPostIdsBySlugPrefix,
  restoreSettingsBaseline,
} from '../helpers/wp-cli';
import { editBlockText, openBlockEditor, savePost } from '../helpers/editor';
import { loadCredentials } from '../helpers/env';

const creds = { ...loadCredentials(), password: '' };

test.describe('F9 fixture manifest unit checks', () => {
  test('manifest exists with all corpus keys and valid checksum', () => {
    const manifest = loadManifest();
    validateManifestStructure(manifest, { expectedUrl: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu' });
    expect(Object.keys(manifest.fixtures).length).toBe(13);
  });

  test('stale manifest checksum is rejected', () => {
    const manifest = loadManifest();
    const tampered = { ...manifest, checksum: 'deadbeef' };
    expect(() => validateManifestStructure(tampered)).toThrow(/checksum mismatch/);
  });

  test('wrong commit rejected in fresh acceptance validation helper', () => {
    const manifest = loadManifest();
    expect(() =>
      validateManifestStructure(manifest, {
        expectedCommit: '0000000000000000000000000000000000000000',
        expectedUrl: manifest.wp_base_url,
      })
    ).toThrow(/commit/);
  });

  test('reuse mode validates existing manifest without re-seeding', () => {
    const manifest = loadManifest();
    const validated = validateOrSeedCorpus({
      mode: 'reuse',
      commit: manifest.commit,
      baseUrl: manifest.wp_base_url,
    });
    expect(Object.keys(validated.fixtures).length).toBe(13);
  });
});

test.describe('F9 fixture isolation safety', () => {
  test('clone receives unique post ID and slug', async ({}, testInfo) => {
    const a = cloneFixtureForTest('paragraph_uuid_draft', testInfo);
    const b = cloneFixtureForTest('paragraph_uuid_draft', { ...testInfo, testId: `${testInfo.testId}-b` });
    try {
      expect(a).not.toBe(b);
      expect(getPostSlug(a)).not.toBe(getPostSlug(b));
      expect(getPostSlug(a).startsWith(F9_CLONE_PREFIX)).toBe(true);
    } finally {
      deleteTrackedClone(a);
      deleteTrackedClone(b);
    }
  });

  test('clone does not inherit translation rows from published corpus', async ({}, testInfo) => {
    const source = getSharedFixture('paragraph_translated_pub');
    expect(countTranslationsForPost(source.post_id)).toBeGreaterThan(0);
    const cloneId = cloneFixtureForTest('paragraph_translated_pub', testInfo);
    try {
      expect(countTranslationsForPost(cloneId)).toBe(0);
    } finally {
      deleteTrackedClone(cloneId);
    }
  });

  test('mutation clone does not alter canonical corpus hash', async ({ page }, testInfo) => {
    const corpus = getSharedFixture('paragraph_uuid_draft');
    const hashBefore = contentSha256(corpus.post_id);
    const cloneId = cloneFixtureForTest('paragraph_uuid_draft', testInfo);
    try {
      await openBlockEditor(page, creds, cloneId);
      await editBlockText(page, 0, 'Mutation isolation check.');
      await savePost(page);
      expect(contentSha256(corpus.post_id)).toBe(hashBefore);
    } finally {
      deleteTrackedClone(cloneId);
    }
  });

  test('teardown scope preserves unrelated posts', () => {
    const unrelated = createDraftPost('f9-unrelated-smoke-post', 'Unrelated Smoke', 'core-paragraph.html');
    const f9Before = listPostIdsBySlugPrefix(F9_CORPUS_PREFIX).length;
    deleteF9PostsByPrefix(F9_CLONE_PREFIX);
    expect(listPostIdsBySlugPrefix('f9-unrelated-smoke-post').length).toBe(1);
    expect(listPostIdsBySlugPrefix(F9_CORPUS_PREFIX).length).toBe(f9Before);
    deletePost(unrelated);
  });
});

test.describe('F9 fixture smoke paths', () => {
  test('read-only frontend overlay uses shared fixture', () => {
    const fixture = getSharedFixture('paragraph_translated_pub');
    const html = fetchPublicHtml(`/sv/${fixture.slug}/`);
    expect(html).toContain('Hej stycke');
  });

  test('mutating editor path uses clone fixture', async ({ page }, testInfo) => {
    const postId = cloneFixtureForTest('paragraph_uuid_draft', testInfo);
    try {
      await openBlockEditor(page, creds, postId);
      await editBlockText(page, 0, 'Smoke edit.');
      await savePost(page);
      expect(getPostSlug(postId).startsWith(F9_CLONE_PREFIX)).toBe(true);
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('settings restore after baseline mutation', () => {
    restoreSettingsBaseline();
    const settings = getSettings();
    expect(settings.block_attr_registration_enabled).toBe(true);
  });

  test('instrumentation records WP-CLI activity', () => {
    const instr = loadInstrumentation();
    expect(instr.wp_cli_calls).toBeGreaterThan(0);
  });
});
