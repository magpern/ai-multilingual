/**
 * Admin Strategy F flag UI validation (F9 §14).
 */
import { test, expect } from '../fixtures/f9-test';
import fs from 'fs';
import path from 'path';
import { ARTIFACTS_DIR, loadCredentials } from '../helpers/env';
import { getSettings, restoreStrategyFlags, submitInvalidStrategyFlagCombo } from '../helpers/wp-cli';
import { login } from '../helpers/editor';

const creds = { ...loadCredentials(), password: '' };
const SETTINGS_URL = `${creds.baseUrl}/wp-admin/admin.php?page=aiml-settings`;
const FLAG_IDS = [
  'aiml-block_attr_registration_enabled',
  'aiml-block_uuid_injection_enabled',
  'aiml-block_extraction_enabled',
  'aiml-block_frontend_rendering_enabled',
];

async function openSettings(page: import('@playwright/test').Page): Promise<void> {
  await page.goto(`${SETTINGS_URL}&t=${Date.now()}`, {
    waitUntil: 'networkidle',
    headers: { 'Cache-Control': 'no-cache' },
  });
}

async function saveSettings(page: import('@playwright/test').Page): Promise<void> {
  const form = page.locator('form[action*="options.php"]');
  await Promise.all([
    page.waitForURL(/page=aiml-settings.*settings-updated=true/, { timeout: 60000 }),
    form.locator('#submit').click(),
  ]);
  await page.waitForLoadState('domcontentloaded');
}

test.describe('F9 admin flag UI', () => {
  test.beforeEach(() => {
    restoreStrategyFlags();
  });

  test.afterEach(() => {
    restoreStrategyFlags();
  });

  test('FF-1: four Strategy F checkboxes visible in dependency order', async ({ page }) => {
    await login(page, creds);
    await openSettings(page);

    for (const id of FLAG_IDS) {
      await expect(page.locator(`#${id}`)).toBeVisible();
    }

    const labels = await page.locator('.aiml-strategy-f-flag').evaluateAll((els) =>
      els.map((el) => (el as HTMLInputElement).id)
    );
    expect(labels).toEqual(FLAG_IDS);
  });

  test('FF-2: valid enable sequence persists via settings form', async ({ page }) => {
    await login(page, creds);
    await openSettings(page);

    await page.locator('#aiml-block_attr_registration_enabled').check();
    await saveSettings(page);
    await openSettings(page);

    await page.locator('#aiml-block_uuid_injection_enabled').check();
    await saveSettings(page);
    await openSettings(page);

    await page.locator('#aiml-block_extraction_enabled').check();
    await saveSettings(page);
    await openSettings(page);

    page.once('dialog', (dialog) => dialog.accept());
    await page.locator('#aiml-block_frontend_rendering_enabled').check();
    await saveSettings(page);

    const settings = getSettings();
    expect(settings.block_attr_registration_enabled).toBe(true);
    expect(settings.block_uuid_injection_enabled).toBe(true);
    expect(settings.block_extraction_enabled).toBe(true);
    expect(settings.block_frontend_rendering_enabled).toBe(true);

    fs.writeFileSync(
      path.join(ARTIFACTS_DIR, 'admin-flags-valid-save.json'),
      JSON.stringify(settings, null, 2)
    );
  });

  test('FF-3: invalid combination shows admin notice and normalizes flags', async ({ page }) => {
    expect(submitInvalidStrategyFlagCombo()).toBe('queued');
    await login(page, creds);
    await openSettings(page);

    await expect(page.getByText('Strategy F flag combination adjusted')).toBeVisible();
    await expect(page.locator('[data-notice-id="aiml_strategy_f_flag_combo_rejected"]')).toContainText(
      'block_uuid_injection_enabled'
    );
    const settings = getSettings();
    expect(settings.block_uuid_injection_enabled).toBe(false);

    fs.writeFileSync(
      path.join(ARTIFACTS_DIR, 'admin-flags-invalid-save.json'),
      JSON.stringify(settings, null, 2)
    );
  });

  test('FF-5: frontend rendering checkbox requires confirmation', async ({ page }) => {
    await login(page, creds);
    await openSettings(page);

    await page.locator('#aiml-block_attr_registration_enabled').check();
    await saveSettings(page);
    await openSettings(page);
    await page.locator('#aiml-block_uuid_injection_enabled').check();
    await saveSettings(page);
    await openSettings(page);
    await page.locator('#aiml-block_extraction_enabled').check();
    await saveSettings(page);
    await openSettings(page);

    await expect(page.locator('#aiml-block_frontend_rendering_enabled')).toHaveAttribute(
      'data-aiml-requires-confirm',
      '1'
    );
    const html = await page.content();
    expect(html).toContain('Enabling frontend rendering may show translated block content');
  });
});
