import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  timeout: 60_000,
  retries: 0,
  use: {
    baseURL: process.env.AIML_OTL5_BASE_URL || 'http://127.0.0.1',
    headless: true,
  },
});
