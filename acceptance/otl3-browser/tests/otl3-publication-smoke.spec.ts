/**
 * Targeted OTL.3 publication + stale smoke (local, not CI-gated).
 */
import { expect, test } from '@playwright/test';
import { F10_LANGUAGE } from '../helpers/env';
import { loginWithCookies } from '../helpers/login';

const operationsUrl = `/wp-admin/admin.php?page=aiml-translator&view=operations&language=${F10_LANGUAGE}`;

test.describe('OTL.3 Publication + stale smoke', () => {
  test.beforeEach(async ({ page }) => {
    const baseUrl = process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu';
    await loginWithCookies(page, { baseUrl, user: '', password: '' });
    await page.goto(operationsUrl, { waitUntil: 'networkidle' });
    await expect(page.locator('#aiml-translator-workspace-root')).toBeAttached();
  });

  test('detail shows publication explain and approved≠published', async ({
    page,
  }) => {
    const viewDetail = page.getByRole('button', { name: 'View detail' }).first();
    await expect(viewDetail).toBeVisible({ timeout: 60_000 });
    await viewDetail.click();
    const region = page.getByRole('region', { name: /Translation detail/i });
    await expect(region).toBeVisible({ timeout: 30_000 });
    await expect(region.getByText(/Publication explain/i)).toBeVisible();
    await expect(
      region.getByText(/Approved does not mean published/i)
    ).toBeVisible();
    await expect(region.getByText(/overlay/i).first()).toBeVisible();
  });

  test('dirty draft disables publication actions when present', async ({
    page,
  }) => {
    await page.getByRole('button', { name: 'View detail' }).first().click();
    const region = page.getByRole('region', { name: /Translation detail/i });
    await expect(region).toBeVisible({ timeout: 30_000 });
    const editor = region.locator('textarea').first();
    if ((await editor.count()) === 0) {
      test.skip(true, 'No post-backed editable target on first row');
      return;
    }
    await editor.fill(`dirty-otl3-${Date.now()}`);
    const publish = region.getByRole('button', { name: /^Publish$/i });
    const unpublish = region.getByRole('button', { name: /^Unpublish$/i });
    const retranslate = region.getByRole('button', { name: /Retranslate/i });
    for (const btn of [publish, unpublish, retranslate]) {
      if ((await btn.count()) > 0) {
        await expect(btn.first()).toBeDisabled();
      }
    }
  });
});
