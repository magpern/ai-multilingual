/**
 * Central F9 browser page lifecycle — one recoverable page per test, shared auth.
 */
import { BrowserContext, Page, test } from '@playwright/test';
import { WpCredentials } from './env';

const F9_DIAG = process.env.F9_DIAG === '1';

export function f9LifecycleDiag(phase: string, detail: Record<string, unknown> = {}): void {
  if (!F9_DIAG) {
    return;
  }
  console.log(`[f9-lifecycle] ${phase}`, JSON.stringify(detail));
}

export type F9PageRef = {
  current: Page;
  context: BrowserContext;
  creds: WpCredentials;
};

const refs = new Map<string, F9PageRef>();

export function registerF9PageRef(testId: string, ref: F9PageRef): void {
  refs.set(testId, ref);
  f9LifecycleDiag('register', { testId });
}

export function unregisterF9PageRef(testId: string): void {
  refs.delete(testId);
  f9LifecycleDiag('unregister', { testId });
}

function activeRef(callerPage?: Page): F9PageRef {
  const testId = test.info().testId;
  const ref = refs.get(testId);
  if (ref) {
    return ref;
  }
  if (callerPage && !callerPage.isClosed()) {
    throw new Error('[f9-lifecycle] page ref missing — import test from fixtures/f9-test.ts');
  }
  throw new Error('[f9-lifecycle] no active page ref and caller page is closed');
}

/** Return a live Page for the current test, recreating when the prior page closed. */
export async function resolveF9Page(callerPage?: Page): Promise<Page> {
  const ref = activeRef(callerPage);
  if (!ref.current.isClosed()) {
    return ref.current;
  }
  return replaceF9Page(ref, 'page-closed');
}

/** Replace the test page after context/browser loss; updates the registered ref. */
export async function replaceF9Page(ref: F9PageRef, reason: string): Promise<Page> {
  f9LifecycleDiag('page:replace', { reason, wasClosed: ref.current.isClosed() });
  if (ref.current && !ref.current.isClosed()) {
    await closeEditorSessionSafe(ref.current, ref.creds);
    await ref.current.close().catch(() => undefined);
  }
  ref.current = await ref.context.newPage();
  const { login } = await import('./editor');
  await login(ref.current, ref.creds);
  f9LifecycleDiag('page:created', { reason, url: ref.current.url() });
  return ref.current;
}

/** Idempotent teardown — navigate away from the editor; never closes the page. */
export async function closeEditorSessionSafe(page: Page, creds?: WpCredentials): Promise<void> {
  if (page.isClosed()) {
    f9LifecycleDiag('closeEditorSession:skip', { reason: 'page_closed' });
    return;
  }
  f9LifecycleDiag('closeEditorSession:start', { url: page.url() });
  const adminList = creds ? `${creds.baseUrl}/wp-admin/edit.php?post_type=page` : 'about:blank';
  try {
    await page.goto(adminList, { waitUntil: 'domcontentloaded', timeout: 30000 });
  } catch (err) {
    f9LifecycleDiag('closeEditorSession:goto-admin-failed', { error: String(err) });
    try {
      await page.goto('about:blank', { waitUntil: 'domcontentloaded', timeout: 10000 });
    } catch (inner) {
      f9LifecycleDiag('closeEditorSession:goto-blank-failed', { error: String(inner) });
    }
  }
  f9LifecycleDiag('closeEditorSession:done', { url: page.isClosed() ? 'closed' : page.url() });
}

/** Shared afterEach / fixture teardown — cleanup failure must not poison the next test. */
export async function endTestLifecycle(callerPage?: Page): Promise<void> {
  try {
    const ref = refs.get(test.info().testId);
    const page = ref?.current ?? callerPage;
    if (!page || page.isClosed()) {
      return;
    }
    await closeEditorSessionSafe(page, ref?.creds);
  } catch (err) {
    f9LifecycleDiag('endTestLifecycle:swallowed', { error: String(err) });
  }
}
