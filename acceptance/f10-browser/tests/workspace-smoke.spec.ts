/**
 * Targeted F10 translator workspace smoke (4 tests).
 */
import { expect, test } from '@playwright/test';
import { F10_LANGUAGE, F10_POST_ID } from '../helpers/env';
import { loginWithCookies } from '../helpers/login';

const workspaceUrl = `/wp-admin/admin.php?page=aiml-translator&post_id=${F10_POST_ID}&language=${F10_LANGUAGE}`;

test.describe('F10 translator workspace smoke', () => {
  test.beforeEach(async ({ page }) => {
    const baseUrl = process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu';
    await loginWithCookies(page, { baseUrl, user: '', password: '' });
    await page.goto(workspaceUrl, { waitUntil: 'networkidle' });
    await expect(page.locator('#aiml-translator-workspace-root')).toBeAttached();
    await expect(
      page.getByRole('region', { name: 'Translation progress summary' })
    ).toBeVisible({ timeout: 90_000 });
  });

  test('loads workspace with post context and status summary', async ({ page }) => {
    await expect(
      page.getByRole('region', { name: 'Post publication context' })
    ).toBeVisible();
    await expect(
      page.getByRole('region', { name: 'Post publication context' }).getByText('F10 Translator Validation')
    ).toBeVisible();
    await expect(page.getByRole('region', { name: 'Translation progress summary' })).toContainText(
      /segments/i
    );
  });

  test('manual edit and save persists segment text', async ({ page }) => {
    const textarea = page.locator('.aiml-workspace-segment-table textarea:not([readonly])').first();
    await expect(textarea).toBeVisible({ timeout: 30_000 });

    const marker = `F10 smoke ${Date.now()}`;
    await textarea.fill(marker);

    const saveButton = page.getByRole('button', { name: /Save translation for b:/i }).first();
    await saveButton.click();

    await expect(page.getByText(/Saved/i)).toBeVisible({ timeout: 30_000 });
    await expect(textarea).toHaveValue(marker);
  });

  test('preview opens public Swedish route', async ({ page, context }) => {
    const popupPromise = context.waitForEvent('page');
    await page.getByRole('button', { name: 'Preview translation' }).click();
    const popup = await popupPromise;
    await popup.waitForLoadState('domcontentloaded');
    await expect(popup).toHaveURL(/\/sv\/f10-translator-validation\//);
  });

  test('bulk translate reports not configured', async ({ page }) => {
    const checkbox = page.locator('.aiml-workspace-segment-table input[type="checkbox"]').first();
    await checkbox.check();
    await page.getByRole('button', { name: 'Translate selected' }).click();
    await expect(
      page.locator('#aiml-translator-workspace-root').getByText('Automatic translation is not configured.')
    ).toBeVisible({
      timeout: 30_000,
    });
  });
});
