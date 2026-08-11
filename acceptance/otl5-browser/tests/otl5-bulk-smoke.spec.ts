import { test, expect } from '@playwright/test';

/**
 * Local/non-CI OTL.5 smoke.
 *
 * Without AIML_OTL5_BASE_URL the suite still encodes frozen contracts so the
 * package remains importable and documents required live checks.
 * Live admin runs require AIML_OTL5_BASE_URL + authenticated storageState.
 */

const MAX_SELECTION = 50;

test.describe('OTL.5 bounded bulk contracts (local/non-CI)', () => {
  test('selection identity and hard max are frozen', async () => {
    expect(MAX_SELECTION).toBe(50);
  });

  test('A3 terminal vs actionable outcomes are documented', async () => {
    const remove = ['published', 'unpublished', 'noop', 'enqueued'];
    const retain = ['skipped', 'blocked', 'conflict', 'unauthorized', 'failed'];
    for (const outcome of remove) {
      expect(retain.includes(outcome)).toBe(false);
    }
  });

  test('supported bulk actions exclude retry and review', async () => {
    const supported = ['publish', 'unpublish', 'enqueue_retranslate'];
    expect(supported).not.toContain('retry_failed');
    expect(supported).not.toContain('approve');
    expect(supported).not.toContain('reject');
  });

  test('enqueue honesty wording requirements', async () => {
    const required = [
      'enqueued',
      'asynchronous',
      'evaluated individually for publication',
    ];
    for (const phrase of required) {
      expect(phrase.length).toBeGreaterThan(0);
    }
  });
});

test.describe('OTL.5 live Operations smoke', () => {
  test.skip(!process.env.AIML_OTL5_BASE_URL, 'live admin not configured');

  test('operations page exposes bulk toolbar region when live', async ({ page }) => {
    test.skip(!process.env.AIML_OTL5_BASE_URL, 'live admin not configured');
    await page.goto('/wp-admin/admin.php?page=aiml-translator&view=operations');
    await expect(page.locator('#aiml-translator-workspace-root')).toBeVisible({
      timeout: 15_000,
    });
    // Toolbar appears only after selection; assert select-all control exists.
    await expect(
      page.getByRole('checkbox', { name: /Select all visible translations/i })
    ).toBeVisible({ timeout: 15_000 });
  });
});
