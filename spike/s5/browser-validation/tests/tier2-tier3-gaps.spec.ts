/**
 * Spike S5 Phase 3 — remaining mandatory gaps:
 *   - Tier 1: copy/paste INTO ANOTHER POST (not same-post)
 *   - Tier 2: non-synced pattern insertion containing a tagged block
 *   - Tier 2: duplicate a pattern-derived subtree (the wp:block reference itself)
 *
 * Post IDs are created ad hoc by tools/setup-tier2-tier3-gaps.sh (host-side).
 *
 * THROWAWAY — spike/s5 only.
 */
import { test } from '@playwright/test';
import {
  login,
  openBlockEditor,
  savePost,
  copyBlock,
  pasteAsNewBlockAfterLast,
  convertSelectedBlockToSyncedPattern,
  insertPatternByName,
  duplicateBlock,
  assertNoBlockRecovery,
} from '../helpers/editor';

const creds = { baseUrl: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu', user: '', password: '' };

const CROSS_POST_SRC = Number(process.env.S5_CROSSPOST_SRC ?? 5037);
const CROSS_POST_TGT = Number(process.env.S5_CROSSPOST_TGT ?? 5038);
const NONSYNCED_SRC = Number(process.env.S5_NONSYNCED_SRC ?? 5039);
const NONSYNCED_TGT = Number(process.env.S5_NONSYNCED_TGT ?? 5040);
const DUP_PATTERN_REF_TGT = Number(process.env.S5_DUP_PATTERN_REF_TGT ?? 5041);
const NONSYNCED_PATTERN_NAME = 'S5 NonSynced Pattern Test';

test('tier1: copy block in source post, paste into a different post', async ({ page }) => {
  await login(page, creds);
  await openBlockEditor(page, creds, CROSS_POST_SRC);
  await copyBlock(page, 0, 'core/paragraph');

  await openBlockEditor(page, creds, CROSS_POST_TGT);
  await pasteAsNewBlockAfterLast(page);
  await savePost(page);
  await assertNoBlockRecovery(page);
});

test('tier2: create non-synced pattern, insert into another post', async ({ page }) => {
  await login(page, creds);
  await openBlockEditor(page, creds, NONSYNCED_SRC);
  await convertSelectedBlockToSyncedPattern(page, 0, 'core/paragraph', NONSYNCED_PATTERN_NAME, false);
  await page.waitForTimeout(1500);
  await savePost(page);
  await assertNoBlockRecovery(page);

  await openBlockEditor(page, creds, NONSYNCED_TGT);
  await insertPatternByName(page, NONSYNCED_PATTERN_NAME);
  await page.waitForTimeout(500);
  await savePost(page);
  await assertNoBlockRecovery(page);
});

test('tier2: duplicate a pattern-derived subtree (the wp:block reference)', async ({ page }) => {
  await login(page, creds);
  await openBlockEditor(page, creds, DUP_PATTERN_REF_TGT);
  await duplicateBlock(page, 0, 'core/block');
  await savePost(page);
  await assertNoBlockRecovery(page);
});
