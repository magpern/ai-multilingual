import { defineConfig, devices } from '@playwright/test';
import path from 'path';

const artifactsDir = path.join(__dirname, 'artifacts');

export default defineConfig({
  testDir: './tests',
  globalSetup: path.join(__dirname, 'global-setup.ts'),
  globalTeardown: path.join(__dirname, 'global-teardown.ts'),
  timeout: 600_000,
  expect: { timeout: 30000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [
    ['list'],
    ['json', { outputFile: path.join(artifactsDir, 'playwright-report.json') }],
    ['html', { outputFolder: path.join(artifactsDir, 'playwright-html'), open: 'never' }],
  ],
  projects: [
    {
      name: 'f9-fixture-smoke',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 720 },
      },
      testMatch: /fixture-environment\.spec\.ts/,
    },
    {
      name: 'chromium-desktop',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 720 },
      },
      testMatch: /(uuid-matrix|frontend-language|admin-flags)\.spec\.ts/,
    },
    {
      name: 'firefox-desktop',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1280, height: 720 },
      },
      testMatch: /tier1-cross-browser\.spec\.ts/,
    },
    {
      name: 'webkit-desktop',
      use: {
        ...devices['Desktop Safari'],
        viewport: { width: 1280, height: 720 },
      },
      testMatch: /tier1-cross-browser\.spec\.ts/,
    },
    {
      name: 'chromium-mobile',
      use: {
        ...devices['iPhone 12'],
        viewport: { width: 390, height: 844 },
      },
      testMatch: /tier1-mobile\.spec\.ts/,
    },
  ],
  use: {
    baseURL: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu',
    headless: true,
    screenshot: 'only-on-failure',
    trace: process.env.F9_TRACE === '1' ? 'retain-on-failure' : 'off',
    video: process.env.F9_TRACE === '1' ? 'retain-on-failure' : 'off',
  },
});
