import { defineConfig, devices } from '@playwright/test';
import path from 'path';

const artifactsDir = path.join(__dirname, 'artifacts');

export default defineConfig({
  testDir: './tests',
  timeout: 120_000,
  expect: { timeout: 30_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list'], ['json', { outputFile: path.join(artifactsDir, 'f10-smoke-report.json') }]],
  projects: [
    {
      name: 'f10-smoke',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 720 },
      },
      testMatch: /workspace-smoke\.spec\.ts/,
    },
  ],
  use: {
    baseURL: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu',
    headless: true,
    screenshot: 'only-on-failure',
  },
});
