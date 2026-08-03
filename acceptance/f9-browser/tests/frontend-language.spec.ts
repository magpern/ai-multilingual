/**
 * Frontend rendering, language routing, translation, migration, rollback (F9 §10–§13, §21).
 */
import { test, expect } from '../fixtures/f9-test';
import fs from 'fs';
import path from 'path';
import { ARTIFACTS_DIR, loadCredentials } from '../helpers/env';
import {
  Analysis,
  cloneFixtureForTest,
  deleteTrackedClone,
  getSharedFixture,
} from '../helpers/bootstrap';
import {
  contentSha256,
  countBlockTranslations,
  enableStrategyFlags,
  exportPost,
  fetchPublicHtml,
  getPostSlug,
  httpGet,
  migratePost,
  publishPost,
  restoreSettingsBaseline,
  runReplayGate,
  saveTranslationForPost,
} from '../helpers/wp-cli';
import { editBlockText, openBlockEditor, savePost } from '../helpers/editor';
import { endTestLifecycle } from '../helpers/lifecycle';

const creds = { ...loadCredentials(), password: '' };
const FIXTURE_PARAGRAPH_SOURCE = 'Strategy F browser validation — paragraph block.';
const results: Record<string, unknown> = {};

test.describe('F9 frontend language translation migration', () => {
  test.beforeAll(() => {
    fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
  });

  test.afterAll(() => {
    restoreSettingsBaseline();
    fs.writeFileSync(path.join(ARTIFACTS_DIR, 'frontend-language-results.json'), JSON.stringify(results, null, 2));
  });

  test.afterEach(async () => {
    await endTestLifecycle();
  });

  const fr1Cases = [
    { key: 'paragraph_translated_pub' as const, marker: 'Hej stycke' },
    { key: 'heading_translated_pub' as const, marker: 'Hej rubrik' },
    { key: 'button_translated_pub' as const, marker: 'Klicka' },
  ] as const;

  for (const c of fr1Cases) {
    test(`FR-1: ${c.key} renders Swedish overlay`, async () => {
      test.setTimeout(600000);
      const fixture = getSharedFixture(c.key);
      const html = fetchPublicHtml(`/sv/${fixture.slug}/`);
      expect(html, fixture.slug).toContain(c.marker);
      results[`fr1_${fixture.slug}`] = { post_id: fixture.post_id, pass: true };
    });
  }

  test('FR-2/FR-3: kill switch and re-enable', async () => {
    test.setTimeout(600000);
    const fixture = getSharedFixture('paragraph_translated_pub');

    let html = fetchPublicHtml(`/sv/${fixture.slug}/`);
    expect(html).toContain('Hej stycke');

    enableStrategyFlags(false);
    html = httpGet(`${creds.baseUrl}/sv/${fixture.slug}/?t=${Date.now()}`);
    expect(html).toContain(FIXTURE_PARAGRAPH_SOURCE);
    expect(html).not.toContain('Hej stycke');

    html = fetchPublicHtml(`/sv/${fixture.slug}/`);
    expect(html).toContain('Hej stycke');

    results.kill_switch = { post_id: fixture.post_id, pass: true };
  });

  test('LS-1/LS-2: language prefix routing without cookie dependency', async () => {
    test.setTimeout(600000);
    const fixture = getSharedFixture('paragraph_translated_pub');

    const en = fetchPublicHtml(`/${fixture.slug}/`);
    const sv = fetchPublicHtml(`/sv/${fixture.slug}/`);
    expect(en).toContain(FIXTURE_PARAGRAPH_SOURCE);
    expect(sv).toContain('Hej stycke');

    results.language_routing = { post_id: fixture.post_id, pass: true };
  });

  test('TR-1/TR-2: store segment_key and stale after edit', async ({ page }, testInfo) => {
    test.setTimeout(600000);
    const postId = cloneFixtureForTest('paragraph_stale_pub', testInfo);

    try {
      publishPost(postId, getPostSlug(postId));
      saveTranslationForPost(postId, `${getPostSlug(postId)}-pub`, '<p>Gammal</p>');

      let html = fetchPublicHtml(`/sv/${getPostSlug(postId)}/`);
      expect(html).toContain('Gammal');

      await openBlockEditor(page, creds, postId);
      await editBlockText(page, 0, 'Changed source text.');
      await savePost(page);

      html = fetchPublicHtml(`/sv/${getPostSlug(postId)}/`);
      expect(html).toContain('Changed source text.');
      expect(html).not.toContain('Gammal');

      results.translation_stale = { post_id: postId, pass: true };
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('TR-5: cross-post UUID scope — no foreign row attachment', async () => {
    const fixtureA = getSharedFixture('scope_a_pub');
    const fixtureB = getSharedFixture('scope_b_pub');

    const htmlB = fetchPublicHtml(`/sv/${fixtureB.slug}/`);
    expect(htmlB).not.toContain('Only A');

    results.cross_post_scope = { post_a: fixtureA.post_id, post_b: fixtureB.post_id, pass: true };
  });

  test('MG-1..MG-3: migration dry-run, live, idempotent', async ({}, testInfo) => {
    const postId = cloneFixtureForTest('migration_raw_draft', testInfo);
    try {
      const hashBefore = contentSha256(postId);

      const dry = migratePost(postId, true);
      expect(dry.dry_run).toBe(true);
      expect(contentSha256(postId)).toBe(hashBefore);

      const live1 = migratePost(postId, false);
      expect(['complete', 'skipped']).toContain(live1.status);
      if ('skipped' === live1.status) {
        expect(live1.skip_reason).toBe('already_compliant');
      }

      const live2 = migratePost(postId, false);
      expect(live2.status).toBe('skipped');
      expect(live2.skip_reason).toBe('already_compliant');

      const analysis = exportPost(postId, `f9-clone-migrate-${testInfo.testId.slice(0, 8)}`).analysis as unknown as Analysis;
      expect(analysis.blocks[0]?.valid_uuid).toBe(true);

      results.migration = { post_id: postId, dry, live1_status: live1.status, live2_status: live2.status };
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('MG-4: browser edit after migration preserves keys', async ({ page }, testInfo) => {
    const postId = cloneFixtureForTest('migration_done_draft', testInfo);
    try {
      await openBlockEditor(page, creds, postId);
      await editBlockText(page, 0, 'Post-migration edit.');
      await savePost(page);

      const after = exportPost(postId, `f9-clone-migrate-edit-${testInfo.testId.slice(0, 8)}`).analysis as unknown as Analysis;
      expect(after.blocks[0]?.valid_uuid).toBe(true);
      expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);
      const replay = runReplayGate(postId);
      expect(replay.rendered_false_positive).toBe(0);

      results.migration_browser_edit = { post_id: postId, segments: countBlockTranslations(postId) };
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('RB-1..RB-5: rollback via flags preserves post_content hash', async () => {
    const fixture = getSharedFixture('paragraph_translated_pub');
    const hashAtStart = contentSha256(fixture.post_id);

    let html = fetchPublicHtml(`/sv/${fixture.slug}/`);
    expect(html).toContain('Hej stycke');

    enableStrategyFlags(false);
    html = httpGet(`${creds.baseUrl}/sv/${fixture.slug}/?t=${Date.now()}`);
    expect(html).toContain(FIXTURE_PARAGRAPH_SOURCE);

    html = fetchPublicHtml(`/sv/${fixture.slug}/`);
    expect(html).toContain('Hej stycke');

    expect(contentSha256(fixture.post_id)).toBe(hashAtStart);
    results.rollback = { post_id: fixture.post_id, content_unchanged: true };
  });
});
