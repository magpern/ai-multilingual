/**
 * Spike S5 Phase 3 — mandatory pattern / synced-pattern gate.
 *
 * Flow (post IDs hardcoded from tools/setup-pattern-workflow.sh output):
 *   1. pat-source: select the tagged paragraph, convert to a synced pattern, save.
 *   2. pat-target-insert: insert the same synced pattern by name, save (leave as reference).
 *   3. pat-target-detach: insert the same synced pattern by name, detach it, save.
 *
 * Host-side (after this spec runs) queries `wp_block` CPT + all three page
 * posts to determine: pattern source identity, post-local block identity,
 * synced reference identity, and detached local copy identity.
 *
 * THROWAWAY — spike/s5 only.
 */
import { test } from '@playwright/test';
import {
  login,
  openBlockEditor,
  savePost,
  convertSelectedBlockToSyncedPattern,
  insertPatternByName,
  detachPattern,
  assertNoBlockRecovery,
} from '../helpers/editor';

const SRC_POST_ID = Number(process.env.S5_PATTERN_SRC ?? 5027);
const TGT_INSERT_POST_ID = Number(process.env.S5_PATTERN_TGT_INSERT ?? 5029);
const TGT_DETACH_POST_ID = Number(process.env.S5_PATTERN_TGT_DETACH ?? 5030);
const PATTERN_NAME = process.env.S5_PATTERN_NAME ?? 'S5 Synced Pattern Test';

const creds = { baseUrl: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu', user: '', password: '' };

test('pattern workflow: create synced pattern from tagged block', async ({ page }) => {
  await login(page, creds);
  await openBlockEditor(page, creds, SRC_POST_ID);
  await convertSelectedBlockToSyncedPattern(page, 0, 'core/paragraph', PATTERN_NAME);
  await page.waitForTimeout(1500); // pattern creation is an async REST call
  await savePost(page);
  await assertNoBlockRecovery(page);
});

test('pattern workflow: insert synced pattern into another post (reference only)', async ({ page }) => {
  await login(page, creds);
  await openBlockEditor(page, creds, TGT_INSERT_POST_ID);
  await insertPatternByName(page, PATTERN_NAME);
  await page.waitForTimeout(500);
  await savePost(page);
  await assertNoBlockRecovery(page);
});

test('pattern workflow: insert then detach synced pattern', async ({ page }) => {
  await login(page, creds);
  await openBlockEditor(page, creds, TGT_DETACH_POST_ID);
  await insertPatternByName(page, PATTERN_NAME);
  await page.waitForTimeout(500);
  await detachPattern(page, 0);
  await savePost(page);
  await assertNoBlockRecovery(page);
});
