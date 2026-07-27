import { defineConfig } from '@playwright/test';
import path from 'path';

export default defineConfig({
  testDir: './tests',
  timeout: 180000,
  expect: { timeout: 30000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [
    ['list'],
    ['json', { outputFile: path.join(__dirname, '../corpus/browser-validation/playwright-report.json') }],
    ['html', { outputFolder: path.join(__dirname, '../corpus/browser-validation/playwright-html'), open: 'never' }],
  ],
  use: {
    baseURL: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu',
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    video: 'retain-on-failure',
  },
});
