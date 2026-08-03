/**
 * Frontend rendering, language routing, translation, migration, rollback (F9 §10–§13, §21).
 */
import { test, expect } from '../fixtures/f9-test';
import fs from 'fs';
import path from 'path';
import { ARTIFACTS_DIR, loadCredentials } from '../helpers/env';
import {
  contentSha256,
  countBlockTranslations,
  createDraftPost,
  deletePost,
  enableStrategyFlags,
  exportPost,
  httpGet,
  fetchPublicHtml,
  migratePost,
  publishPost,
  restoreStrategyFlags,
  runReplayGate,
  saveBlockTranslation,
  saveTranslationForPost,
} from '../helpers/wp-cli';
import { editBlockText, login, openBlockEditor, savePost } from '../helpers/editor';
import { endTestLifecycle } from '../helpers/lifecycle';
import { Analysis, bootstrapProductionPost } from '../helpers/bootstrap';

const creds = { ...loadCredentials(), password: '' };
const FIXTURE_PARAGRAPH_SOURCE = 'Strategy F browser validation — paragraph block.';
const results: Record<string, unknown> = {};

test.describe('F9 frontend language translation migration', () => {
  test.beforeAll(() => {
    fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
  });

  test.afterAll(() => {
    restoreStrategyFlags();
    fs.writeFileSync(path.join(ARTIFACTS_DIR, 'frontend-language-results.json'), JSON.stringify(results, null, 2));
  });

  test.afterEach(async () => {
    await endTestLifecycle();
  });

  const fr1Cases = [
    { slug: 'f9-fr-para', fixture: 'core-paragraph.html', sv: '<p>Hej stycke</p>', marker: 'Hej stycke' },
    { slug: 'f9-fr-head', fixture: 'core-heading.html', sv: '<h2 class="wp-block-heading">Hej rubrik</h2>', marker: 'Hej rubrik' },
    {
      slug: 'f9-fr-btn',
      fixture: 'core-buttons.html',
      sv: '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.com/a">Klicka</a></div>',
      marker: 'Klicka',
    },
  ] as const;

  for (const c of fr1Cases) {
    test(`FR-1: ${c.slug} renders Swedish overlay`, async ({ page }) => {
      test.setTimeout(600000);
      const postId = await bootstrapProductionPost(page, creds, c.slug, `F9 FR ${c.slug}`, c.fixture);
      publishPost(postId, c.slug);
      saveTranslationForPost(postId, `${c.slug}-pub`, c.sv);

      const html = fetchPublicHtml(`/sv/${c.slug}/`);
      expect(html, c.slug).toContain(c.marker);

      results[`fr1_${c.slug}`] = { post_id: postId, pass: true };
      deletePost(postId);
    });
  }

  test('FR-2/FR-3: kill switch and re-enable', async ({ page }) => {
    test.setTimeout(600000);
    const slug = 'f9-killswitch';
    const postId = await bootstrapProductionPost(page, creds, slug, 'F9 Kill switch', 'core-paragraph.html');
    publishPost(postId, slug);
    saveTranslationForPost(postId, `${slug}-pub`, '<p>Hej av</p>');

    let html = fetchPublicHtml(`/sv/${slug}/`);
    expect(html).toContain('Hej av');

    enableStrategyFlags(false);
    html = httpGet(`${creds.baseUrl}/sv/${slug}/?t=${Date.now()}`);
    expect(html).toContain(FIXTURE_PARAGRAPH_SOURCE);
    expect(html).not.toContain('Hej av');

    html = fetchPublicHtml(`/sv/${slug}/`);
    expect(html).toContain('Hej av');

    results.kill_switch = { post_id: postId, pass: true };
    deletePost(postId);
  });

  test('LS-1/LS-2: language prefix routing without cookie dependency', async ({ page }) => {
    test.setTimeout(600000);
    const slug = 'f9-lang-route';
    const postId = await bootstrapProductionPost(page, creds, slug, 'F9 Lang route', 'core-paragraph.html');
    publishPost(postId, slug);
    saveTranslationForPost(postId, `${slug}-pub`, '<p>Svenska</p>');

    enableStrategyFlags(true);
    const en = fetchPublicHtml(`/${slug}/`);
    const sv = fetchPublicHtml(`/sv/${slug}/`);
    expect(en).toContain(FIXTURE_PARAGRAPH_SOURCE);
    expect(sv).toContain('Svenska');

    results.language_routing = { post_id: postId, pass: true };
    deletePost(postId);
  });

  test('TR-1/TR-2: store segment_key and stale after edit', async ({ page }) => {
    test.setTimeout(600000);
    const slug = 'f9-tr-stale';
    const postId = await bootstrapProductionPost(page, creds, slug, 'F9 TR stale', 'core-paragraph.html');
    publishPost(postId, slug);
    saveTranslationForPost(postId, `${slug}-pub`, '<p>Gammal</p>');

    let html = fetchPublicHtml(`/sv/${slug}/`);
    expect(html).toContain('Gammal');

    await openBlockEditor(page, creds, postId);
    await editBlockText(page, 0, 'Changed source text.');
    await savePost(page);

    html = fetchPublicHtml(`/sv/${slug}/`);
    expect(html).toContain('Changed source text.');
    expect(html).not.toContain('Gammal');

    results.translation_stale = { post_id: postId, pass: true };
    deletePost(postId);
  });

  test('TR-5: cross-post UUID scope — no foreign row attachment', async ({ page }) => {
    enableStrategyFlags(true);
    const slugA = 'f9-tr-scope-a';
    const slugB = 'f9-tr-scope-b';
    const idA = await bootstrapProductionPost(page, creds, slugA, 'F9 Scope A', 'core-paragraph.html');
    const idB = await bootstrapProductionPost(page, creds, slugB, 'F9 Scope B', 'core-paragraph.html');
    publishPost(idA, slugA);
    publishPost(idB, slugB);
    enableStrategyFlags(true);
    const uuidA = saveTranslationForPost(idA, `${slugA}-pub`, '<p>Only A</p>');

    const htmlB = fetchPublicHtml(`/sv/${slugB}/`);
    expect(htmlB).not.toContain('Only A');

    results.cross_post_scope = { post_a: idA, post_b: idB, uuid_a: uuidA, pass: true };
    deletePost(idA);
    deletePost(idB);
  });

  test('MG-1..MG-3: migration dry-run, live, idempotent', async () => {
    restoreStrategyFlags();
    const slug = 'f9-migrate';
    const postId = createDraftPost(slug, 'F9 Migrate', 'core-paragraph.html');
    enableStrategyFlags(false);
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

    const analysis = exportPost(postId, slug).analysis as unknown as Analysis;
    expect(analysis.blocks[0]?.valid_uuid).toBe(true);

    results.migration = { post_id: postId, dry, live1_status: live1.status, live2_status: live2.status };
    deletePost(postId);
  });

  test('MG-4: browser edit after migration preserves keys', async ({ page }) => {
    enableStrategyFlags(false);
    const slug = 'f9-migrate-edit';
    const postId = createDraftPost(slug, 'F9 Migrate edit', 'core-paragraph.html');
    migratePost(postId, false);

    await login(page, creds);
    await openBlockEditor(page, creds, postId);
    await editBlockText(page, 0, 'Post-migration edit.');
    await savePost(page);

    const after = exportPost(postId, slug).analysis as unknown as Analysis;
    expect(after.blocks[0]?.valid_uuid).toBe(true);
    expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);
    const replay = runReplayGate(postId);
    expect(replay.rendered_false_positive).toBe(0);

    results.migration_browser_edit = { post_id: postId, segments: countBlockTranslations(postId) };
    deletePost(postId);
  });

  test('RB-1..RB-5: rollback via flags preserves post_content hash', async ({ page }) => {
    enableStrategyFlags(true);
    const slug = 'f9-rollback';
    const postId = await bootstrapProductionPost(page, creds, slug, 'F9 Rollback', 'core-paragraph.html');
    enableStrategyFlags(true);
    publishPost(postId, slug);
    saveTranslationForPost(postId, `${slug}-pub`, '<p>Hej rb</p>');
    const hashAtStart = contentSha256(postId);

    let html = fetchPublicHtml(`/sv/${slug}/`);
    expect(html).toContain('Hej rb');

    enableStrategyFlags(false);
    html = httpGet(`${creds.baseUrl}/sv/${slug}/?t=${Date.now()}`);
    expect(html).toContain(FIXTURE_PARAGRAPH_SOURCE);

    html = fetchPublicHtml(`/sv/${slug}/`);
    expect(html).toContain('Hej rb');

    expect(contentSha256(postId)).toBe(hashAtStart);
    results.rollback = { post_id: postId, content_unchanged: true };
    deletePost(postId);
  });
});
