import { test, expect } from '@playwright/test';
import { loginWithCookies } from '../helpers/login';
import { f10Language } from '../helpers/env';

test.describe('OTL.4 Jobs integration smoke', () => {
	test('operations detail exposes Jobs section and Jobs tab deep-link', async ({
		page,
	}) => {
		await loginWithCookies(page);
		const language = f10Language();
		await page.goto(
			`/wp-admin/admin.php?page=aiml-translator&view=operations&language=${encodeURIComponent(
				language
			)}`
		);
		await expect(page.getByRole('tab', { name: /Operations/i })).toBeVisible({
			timeout: 60000,
		});

		const viewDetail = page.getByRole('button', { name: /View detail/i }).first();
		if (await viewDetail.count()) {
			await viewDetail.click();
			const jobsHeading = page.getByText(/Background Jobs|Jobs association|bounded lookup/i).first();
			await expect(jobsHeading).toBeVisible({ timeout: 30000 });
		}

		await page.goto(
			`/wp-admin/admin.php?page=aiml-translator&view=jobs`
		);
		await expect(page.getByRole('tab', { name: /Jobs/i })).toBeVisible({
			timeout: 60000,
		});
	});
});
