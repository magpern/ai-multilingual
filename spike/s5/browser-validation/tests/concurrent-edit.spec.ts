/**
 * Spike S5 Phase 3 — mandatory concurrent-edit simulation.
 *
 * Two browser sessions (two pages sharing one authenticated context) load
 * the SAME registered-attribute-tagged post, both independently duplicate
 * the same block, session A saves first, then session B saves. The final
 * post_content is exported for Strategy F duplicate-repair replay.
 *
 * Requires the throwaway attribute-registration mu-plugin (re-added
 * specifically for this test — see wp-content/mu-plugins/zzz-s5-attribute-
 * registration-spike.php) because the unregistered attribute never survives
 * a duplicate action and so cannot exercise this race condition at all.
 *
 * THROWAWAY — spike/s5 only.
 */
import { test } from '@playwright/test';
import { login, openBlockEditor, savePost, duplicateBlock, assertNoBlockRecovery } from '../helpers/editor';

const creds = { baseUrl: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu', user: '', password: '' };
const POST_ID = Number(process.env.S5_CONCURRENT_POST_ID ?? 5052);

test('concurrent edit: two sessions duplicate the same block, A saves then B saves', async ({ page, context }) => {
  const pageA = page;
  const pageB = await context.newPage();

  await login(pageA, creds);
  // Cookies are added to the shared BrowserContext, so pageB is already authenticated.

  await openBlockEditor(pageA, creds, POST_ID);
  await openBlockEditor(pageB, creds, POST_ID);

  // Both sessions duplicate the same (only) paragraph before either saves —
  // this is the adversarial "both loaded the same pre-duplicate state" case.
  await duplicateBlock(pageA, 0, 'core/paragraph');
  await duplicateBlock(pageB, 0, 'core/paragraph');

  await savePost(pageA);
  await assertNoBlockRecovery(pageA);

  await savePost(pageB);
  await assertNoBlockRecovery(pageB);

  await pageB.close();
});
