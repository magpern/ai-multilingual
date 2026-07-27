/**
 * Diagnostic: dump toolbar button HTML for block-switcher/breadcrumb. THROWAWAY.
 */
import { test } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { CORPUS_DIR } from '../helpers/env';
import { login, openBlockEditor, canvas, savePost } from '../helpers/editor';

test('diagnostic: cover block recovery confound check (untagged, no aimlBlockId)', async ({ page }) => {
  const creds = { baseUrl: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu', user: '', password: '' };
  await login(page, creds);
  await openBlockEditor(page, creds, 4879); // diag-cover-untagged, NOT injected with aimlBlockId
  await page.waitForTimeout(1000);
  const recoveryInFrame = await canvas(page).locator('text=/unexpected or invalid content/i').count();
  const recoveryInMain = await page.locator('text=/unexpected or invalid content/i').count();
  fs.writeFileSync(
    path.join(CORPUS_DIR, 'diag-cover-untagged-recovery.json'),
    JSON.stringify({ recoveryInFrame, recoveryInMain }, null, 2)
  );
  await page.screenshot({ path: path.join(CORPUS_DIR, 'diag-cover-untagged.png') });
});

test('diagnostic: rank-math toc recovery confound check (untagged)', async ({ page }) => {
  const creds = { baseUrl: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu', user: '', password: '' };
  await login(page, creds);
  await openBlockEditor(page, creds, 5024); // diag-toc-untagged, NOT injected with aimlBlockId
  await page.waitForTimeout(1000);
  const recoveryInFrame = await canvas(page).locator('text=/unexpected or invalid content/i').count();
  fs.writeFileSync(
    path.join(CORPUS_DIR, 'diag-toc-untagged-recovery.json'),
    JSON.stringify({ recoveryInFrame }, null, 2)
  );
});

test('diagnostic: capture canonical rank-math toc markup via save', async ({ page }) => {
  const creds = { baseUrl: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu', user: '', password: '' };
  await login(page, creds);
  await openBlockEditor(page, creds, 5024);
  await savePost(page);
});

test('diagnostic: toolbar buttons', async ({ page }) => {
  const creds = { baseUrl: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu', user: '', password: '' };
  await login(page, creds);
  await openBlockEditor(page, creds, 4836); // op-unwrap-group

  const block = canvas(page).locator('[data-type="core/paragraph"]').first();
  await block.click({ position: { x: 10, y: 10 } });
  await page.waitForTimeout(500);

  const breadcrumbHtml = await page.locator('.block-editor-block-breadcrumb').first().innerHTML().catch((e) => `ERR ${e}`);
  fs.writeFileSync(path.join(CORPUS_DIR, 'diag-breadcrumb.html'), breadcrumbHtml);

  // Try clicking the "Group" breadcrumb button to select the parent.
  const groupBtn = page.locator('.block-editor-block-breadcrumb button', { hasText: 'Group' }).first();
  const groupBtnVisible = await groupBtn.isVisible({ timeout: 2000 }).catch(() => false);
  fs.writeFileSync(path.join(CORPUS_DIR, 'diag-groupbtn-visible.txt'), String(groupBtnVisible));
  if (groupBtnVisible) {
    await groupBtn.click();
    await page.waitForTimeout(300);
    const toolbarHtml = await page.locator('.block-editor-block-contextual-toolbar, .block-editor-block-toolbar').first().innerHTML().catch((e) => `ERR ${e}`);
    fs.writeFileSync(path.join(CORPUS_DIR, 'diag-toolbar-after-breadcrumb.html'), toolbarHtml);
  }
});

test('diagnostic: header inserter toggle button', async ({ page }) => {
  const creds = { baseUrl: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu', user: '', password: '' };
  await login(page, creds);
  await openBlockEditor(page, creds, 5029);
  await page.waitForTimeout(1000);
  const headerHtml = await page.locator('.editor-header, .edit-post-header').first().innerHTML().catch((e) => `ERR ${e}`);
  fs.writeFileSync(path.join(CORPUS_DIR, 'diag-header.html'), headerHtml);
  await page.screenshot({ path: path.join(CORPUS_DIR, 'diag-header.png') });
});

test('diagnostic: inserter panel after click', async ({ page }) => {
  const creds = { baseUrl: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu', user: '', password: '' };
  await login(page, creds);
  await openBlockEditor(page, creds, 5029);
  await page.locator('button[aria-label="Block Inserter"]').first().click();
  await page.waitForTimeout(800);
  await page.screenshot({ path: path.join(CORPUS_DIR, 'diag-inserter-panel.png') });
  const panelHtml = await page.locator('.block-editor-inserter__menu, .block-editor-inserter__popover, [role="dialog"]').first().innerHTML().catch((e) => `ERR ${e}`);
  fs.writeFileSync(path.join(CORPUS_DIR, 'diag-inserter-panel.html'), panelHtml);
});

test('diagnostic: REST read/write, autosave, export/import UUID checks', async ({ page }) => {
  const creds = { baseUrl: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu', user: '', password: '' };
  await login(page, creds);
  const postId = Number(process.env.S5_REST_POST_ID ?? 5049);
  const results: Record<string, unknown> = {};

  // Grab a REST nonce from the admin page (wpApiSettings.nonce) first — this
  // WP setup's REST layer requires it even for an authenticated GET.
  await openBlockEditor(page, creds, postId);
  const nonce = await page.evaluate(() => (window as unknown as { wpApiSettings?: { nonce?: string } }).wpApiSettings?.nonce ?? null);
  results.rest_nonce_found = Boolean(nonce);

  const readRes = await page.request.get(`${creds.baseUrl}/wp-json/wp/v2/pages/${postId}?context=edit`, {
    headers: nonce ? { 'X-WP-Nonce': nonce } : {},
  });
  const readBody = await readRes.json().catch(() => ({}));
  results.rest_read_status = readRes.status();
  results.rest_read_raw_has_uuid = typeof readBody?.content?.raw === 'string' && readBody.content.raw.includes('aimlBlockId');
  results.rest_read_raw_snippet = readBody?.content?.raw ?? null;

  if (nonce) {
    const newContent = '<!-- wp:paragraph {"aimlBlockId":"44444444-4444-4444-8444-444444444444"} -->\n<p>REST-written paragraph.</p>\n<!-- /wp:paragraph -->';
    const writeRes = await page.request.post(`${creds.baseUrl}/wp-json/wp/v2/pages/${postId}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { content: newContent },
    });
    results.rest_write_status = writeRes.status();
    const writeBody = await writeRes.json().catch(() => ({}));
    results.rest_write_roundtrip_has_uuid = typeof writeBody?.content?.raw === 'string' && writeBody.content.raw.includes('44444444-4444-4444-8444-444444444444');

    // Autosave endpoint (used by the block editor's periodic local save).
    const autosaveContent = '<!-- wp:paragraph {"aimlBlockId":"55555555-5555-4555-8555-555555555555"} -->\n<p>Autosaved paragraph.</p>\n<!-- /wp:paragraph -->';
    const autosaveRes = await page.request.post(`${creds.baseUrl}/wp-json/wp/v2/pages/${postId}/autosaves`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { content: autosaveContent, id: postId },
    });
    results.autosave_status = autosaveRes.status();
    const autosaveBody = await autosaveRes.json().catch(() => ({}));
    results.autosave_roundtrip_has_uuid = typeof autosaveBody?.content?.raw === 'string' && autosaveBody.content.raw.includes('55555555-5555-4555-8555-555555555555');
  }

  fs.writeFileSync(path.join(CORPUS_DIR, 'diag-rest-autosave.json'), JSON.stringify(results, null, 2));
});

test('diagnostic: pattern search result item structure', async ({ page }) => {
  const creds = { baseUrl: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu', user: '', password: '' };
  await login(page, creds);
  await openBlockEditor(page, creds, 5029);
  await page.locator('button[aria-label="Block Inserter"]').first().click();
  const patternsTab = page.getByRole('tab', { name: /patterns/i }).first();
  await patternsTab.click();
  const search = page.getByPlaceholder(/search/i).first();
  await search.click();
  await search.fill('S5 Synced Pattern Test');
  await page.waitForTimeout(800);
  await page.screenshot({ path: path.join(CORPUS_DIR, 'diag-pattern-search.png') });
  const resultsHtml = await page.locator('.block-editor-inserter__panel-content, .block-editor-inserter__search-results').first().innerHTML().catch((e) => `ERR ${e}`);
  fs.writeFileSync(path.join(CORPUS_DIR, 'diag-pattern-search.html'), resultsHtml);
});
