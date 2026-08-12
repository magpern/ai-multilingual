/**
 * Authoritative OTL lifecycle smoke (local / non-CI).
 *
 * Soft-skips when rows or controls are absent. No live AI calls.
 */
import { expect, test } from '@playwright/test';
import { F10_LANGUAGE, loadCredentials } from '../helpers/env';
import { loginWithCookies } from '../helpers/login';

const operationsUrl = `/wp-admin/admin.php?page=aiml-translator&view=operations&language=${F10_LANGUAGE}`;

test.describe('OTL lifecycle smoke', () => {
  test.beforeEach(async ({ page }) => {
    await loginWithCookies(page, loadCredentials());
    await page.goto(operationsUrl, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#aiml-translator-workspace-root')).toBeAttached({
      timeout: 60_000,
    });
  });

  test('Operations tab and filters are present', async ({ page }) => {
    await expect(page.getByRole('tab', { name: /Operations/i })).toBeVisible({
      timeout: 60_000,
    });
    const filters = page.locator('.aiml-operations-filters');
    if ((await filters.count()) > 0) {
      await expect(filters.first()).toBeVisible();
    }
  });

  test('View detail opens when a row exists', async ({ page }) => {
    const viewDetail = page.getByRole('button', { name: /View detail/i }).first();
    if ((await viewDetail.count()) === 0) {
      test.skip(true, 'No Operations rows with View detail');
    }
    await viewDetail.click();
    await expect(page.locator('.aiml-operations-inspector')).toBeVisible({
      timeout: 30_000,
    });
  });

  test('dirty leave shows ConfirmDialog on tab change when draft dirty', async ({
    page,
  }) => {
    const viewDetail = page.getByRole('button', { name: /View detail/i }).first();
    if ((await viewDetail.count()) === 0) {
      test.skip(true, 'No Operations rows');
    }
    await viewDetail.click();
    const editor = page.locator('.aiml-operations-inspector textarea').first();
    if ((await editor.count()) === 0) {
      test.skip(true, 'No editable target textarea');
    }
    await editor.fill(`otl6-dirty-${Date.now()}`);
    await page.getByRole('tab', { name: /Translate/i }).click();
    const dialog = page.locator('.aiml-confirm-dialog, .components-modal__frame');
    await expect(dialog.first()).toBeVisible({ timeout: 15_000 });
    await page.getByRole('button', { name: /^Cancel$/i }).click();
    await expect(page.getByRole('tab', { name: /Operations/i })).toHaveAttribute(
      'aria-selected',
      'true'
    );
  });

  test('context restore after temporary tab hop', async ({ page }) => {
    await expect(page.getByRole('tab', { name: /Operations/i })).toBeVisible({
      timeout: 60_000,
    });
    const jobs = page.getByRole('tab', { name: /Jobs/i });
    if ((await jobs.count()) === 0) {
      test.skip(true, 'Jobs tab unavailable');
    }
    await jobs.click();
    await expect(page.getByRole('tab', { name: /Jobs/i })).toHaveAttribute(
      'aria-selected',
      'true'
    );
    await expect(page).not.toHaveURL(/attention=/);
    await page.getByRole('tab', { name: /Operations/i }).click();
    await expect(page).toHaveURL(/view=operations/);
    await expect(page).toHaveURL(new RegExp(`language=${F10_LANGUAGE}`));
  });

  test('Review → Operations control when visible', async ({ page }) => {
    await page.getByRole('tab', { name: /Review queue/i }).click();
    const openOps = page.getByRole('button', { name: /Open in Operations/i }).first();
    if ((await openOps.count()) === 0) {
      test.skip(true, 'Open in Operations not visible');
    }
    await openOps.click();
    await expect(page.getByRole('tab', { name: /Operations/i })).toHaveAttribute(
      'aria-selected',
      'true'
    );
    await expect(page).toHaveURL(/translation_id=/);
  });

  test('Jobs tab loads without Ops filter pollution', async ({ page }) => {
    await page.getByRole('tab', { name: /Jobs/i }).click();
    await expect(page.getByRole('tab', { name: /Jobs/i })).toHaveAttribute(
      'aria-selected',
      'true'
    );
    await expect(page).not.toHaveURL(/attention=/);
    await expect(page).not.toHaveURL(/page_num=/);
  });

  test('bulk toolbar remains present', async ({ page }) => {
    const toolbar = page.locator('.aiml-operations-bulk-toolbar');
    if ((await toolbar.count()) === 0) {
      test.skip(true, 'Bulk toolbar not rendered');
    }
    await expect(toolbar.first()).toBeVisible();
  });

  test('keyboard focus-visible smoke on Operations tab', async ({ page }) => {
    const ops = page.getByRole('tab', { name: /Operations/i });
    await ops.focus();
    await expect(ops).toBeFocused();
  });
});
