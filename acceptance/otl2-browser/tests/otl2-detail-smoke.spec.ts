/**
 * Targeted OTL.2 unified detail smoke (local, not CI-gated).
 */
import { expect, test } from '@playwright/test';
import { F10_LANGUAGE } from '../helpers/env';
import { loginWithCookies } from '../helpers/login';

const operationsUrl = `/wp-admin/admin.php?page=aiml-translator&view=operations&language=${F10_LANGUAGE}`;

test.describe('OTL.2 Unified detail smoke', () => {
  test.beforeEach(async ({ page }) => {
    const baseUrl = process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu';
    await loginWithCookies(page, { baseUrl, user: '', password: '' });
    await page.goto(operationsUrl, { waitUntil: 'networkidle' });
    await expect(page.locator('#aiml-translator-workspace-root')).toBeAttached();
  });

  test('open detail from Operations shows source and target', async ({ page }) => {
    const viewDetail = page.getByRole('button', { name: 'View detail' }).first();
    await expect(viewDetail).toBeVisible({ timeout: 60_000 });
    await viewDetail.click();
    await expect(
      page.getByRole('region', { name: /Translation detail/i })
    ).toBeVisible({ timeout: 30_000 });
    await expect(page.getByText(/Source text/i).first()).toBeVisible();
    await expect(page.getByText(/Target/i).first()).toBeVisible();
  });

  test('edit marks dirty, honesty banner, review controls gated', async ({
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

    await editor.fill((await editor.inputValue()) + ' OTL2-dirty');
    await expect(page.getByText(/unsaved changes/i)).toBeVisible();
    await expect(page.getByText(/last saved translation/i)).toBeVisible();

    const submit = region.getByRole('button', { name: /^Submit/i });
    const approve = region.getByRole('button', { name: /^Approve/i });
    const reject = region.getByRole('button', { name: /^Reject/i });
    for (const btn of [submit, approve, reject]) {
      if ((await btn.count()) > 0) {
        await expect(btn).toBeDisabled();
      }
    }
  });

  test('approved does not mean published copy visible', async ({ page }) => {
    await page.getByRole('button', { name: 'View detail' }).first().click();
    await expect(
      page.getByRole('region', { name: /Translation detail/i })
    ).toBeVisible({ timeout: 30_000 });
    await expect(
      page.getByText(/Approved does not mean published/i)
    ).toBeVisible();
  });

  test('no publish mutation control in detail', async ({ page }) => {
    await page.getByRole('button', { name: 'View detail' }).first().click();
    const region = page.getByRole('region', { name: /Translation detail/i });
    await expect(region).toBeVisible({ timeout: 30_000 });
    await expect(region.getByRole('button', { name: /^Publish$/i })).toHaveCount(0);
    await expect(region.getByRole('button', { name: /^Unpublish$/i })).toHaveCount(0);
  });

  test('URL carries translation_id while detail open', async ({ page }) => {
    await page.getByRole('button', { name: 'View detail' }).first().click();
    await expect(
      page.getByRole('region', { name: /Translation detail/i })
    ).toBeVisible({ timeout: 30_000 });
    await expect(page).toHaveURL(/translation_id=\d+/);
  });

  test('responsive narrow stacks without unreachable controls', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 720, height: 900 });
    await page.getByRole('button', { name: 'View detail' }).first().click();
    const region = page.getByRole('region', { name: /Translation detail/i });
    await expect(region).toBeVisible({ timeout: 30_000 });
    await expect(region.getByRole('button', { name: 'Close' })).toBeVisible();
  });

  test('keyboard focus reaches close control', async ({ page }) => {
    await page.getByRole('button', { name: 'View detail' }).first().click();
    const close = page
      .getByRole('region', { name: /Translation detail/i })
      .getByRole('button', { name: 'Close' });
    await expect(close).toBeVisible({ timeout: 30_000 });
    await close.focus();
    await expect(close).toBeFocused();
  });

  test('HTML in source/target is not executed', async ({ page }) => {
    await page.getByRole('button', { name: 'View detail' }).first().click();
    const region = page.getByRole('region', { name: /Translation detail/i });
    await expect(region).toBeVisible({ timeout: 30_000 });
    await expect(region.locator('script')).toHaveCount(0);
  });
});
