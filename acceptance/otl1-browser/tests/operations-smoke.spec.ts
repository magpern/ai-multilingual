/**
 * Targeted OTL.1 Operations smoke (local, not CI-gated).
 */
import { expect, test } from '@playwright/test';
import { F10_LANGUAGE } from '../helpers/env';
import { loginWithCookies } from '../helpers/login';

const operationsUrl = `/wp-admin/admin.php?page=aiml-translator&view=operations&language=${F10_LANGUAGE}`;

test.describe('OTL.1 Operations smoke', () => {
  test.beforeEach(async ({ page }) => {
    const baseUrl = process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu';
    await loginWithCookies(page, { baseUrl, user: '', password: '' });
    await page.goto(operationsUrl, { waitUntil: 'networkidle' });
    await expect(page.locator('#aiml-translator-workspace-root')).toBeAttached();
  });

  test('Operations tab loads with honesty copy and counts', async ({ page }) => {
    await expect(
      page.getByRole('tab', { name: 'Operations' })
    ).toBeVisible({ timeout: 60_000 });
    await expect(page.getByRole('note')).toContainText(/operational attention/i);
    await expect(
      page.getByRole('list', { name: /operational attention counts/i })
    ).toBeVisible();
    await expect(page.getByText(/Pending review/i).first()).toBeVisible();
    await expect(page.getByText(/needs_review/i)).toHaveCount(0);
  });

  test('attention filter and pagination controls are present', async ({ page }) => {
    await expect(page.getByRole('search', { name: /Operations filters/i })).toBeVisible({
      timeout: 60_000,
    });
    const attention = page.locator('.aiml-operations-filters select').nth(1);
    await attention.selectOption('review_pending');
    await expect(page.getByRole('navigation', { name: /pagination/i })).toBeVisible();
  });

  test('table exposes distinct lifecycle axes', async ({ page }) => {
    await expect(page.locator('.aiml-operations-table')).toBeVisible({ timeout: 60_000 });
    await expect(page.getByRole('columnheader', { name: 'Review' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'Publish' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'Stale' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'Attention' })).toBeVisible();
  });

  test('View detail opens read-only inspector region', async ({ page }) => {
    const viewDetail = page.getByRole('button', { name: 'View detail' }).first();
    await expect(viewDetail).toBeVisible({ timeout: 60_000 });
    await viewDetail.click();
    await expect(
      page.getByRole('region', { name: 'Translation inspector' })
    ).toBeVisible({ timeout: 30_000 });
    await expect(page.getByText(/read-only/i)).toBeVisible();
  });

  test('Open in Translate and Open in Review navigation controls exist', async ({
    page,
  }) => {
    await expect(page.locator('.aiml-operations-table')).toBeVisible({
      timeout: 60_000,
    });
    const translate = page.getByRole('button', { name: 'Open in Translate' }).first();
    const review = page.getByRole('button', { name: 'Open in Review' }).first();
    // Buttons appear when capabilities allow; at least one navigation control should exist for operators.
    const translateCount = await translate.count();
    const reviewCount = await review.count();
    expect(translateCount + reviewCount).toBeGreaterThan(0);
  });
});
