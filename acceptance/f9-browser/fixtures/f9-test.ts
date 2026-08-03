/**
 * F9 Playwright fixture — fresh recoverable page per test, cookie auth reused via context.
 */
import { test as base, expect } from '@playwright/test';
import { loadCredentials } from '../helpers/env';
import { login } from '../helpers/editor';
import {
  endTestLifecycle,
  f9LifecycleDiag,
  registerF9PageRef,
  unregisterF9PageRef,
} from '../helpers/lifecycle';

export const test = base.extend({
  page: async ({ context }, use, testInfo) => {
    const creds = { ...loadCredentials(), password: '' };
    const page = await context.newPage();
    const ref = { current: page, context, creds };
    registerF9PageRef(testInfo.testId, ref);
    f9LifecycleDiag('fixture:init', { testId: testInfo.testId, title: testInfo.title });
    await login(page, creds);

    await use(page);

    await endTestLifecycle(page);
    unregisterF9PageRef(testInfo.testId);
    if (!page.isClosed()) {
      await page.close().catch(() => undefined);
    }
    f9LifecycleDiag('fixture:done', { testId: testInfo.testId });
  },
});

export { expect };
