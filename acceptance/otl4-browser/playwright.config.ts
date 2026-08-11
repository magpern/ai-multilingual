import { defineConfig } from '@playwright/test';

export default defineConfig({
	testDir: './tests',
	timeout: 120000,
	retries: 0,
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://127.0.0.1',
		trace: 'off',
	},
	projects: [
		{
			name: 'otl4-smoke',
			testMatch: /otl4-jobs-smoke\.spec\.ts/,
		},
	],
});
