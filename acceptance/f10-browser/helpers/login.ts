/**
 * WordPress admin cookie login for F10 smoke tests.
 */
import fs from 'fs';
import { Page } from '@playwright/test';
import { WpCredentials, authCookieFile } from './env';

export async function loginWithCookies(page: Page, creds: WpCredentials): Promise<void> {
  const cookieFile = authCookieFile();
  if (!fs.existsSync(cookieFile)) {
    throw new Error(`Missing ${cookieFile} — run F9 auth cookie bootstrap on host first`);
  }

  const payload = JSON.parse(fs.readFileSync(cookieFile, 'utf8')) as {
    cookies: { auth: string; logged_in: string; secure_auth: string };
    names: { auth: string; logged_in: string; secure_auth: string };
  };
  const domain = new URL(creds.baseUrl).hostname;

  await page.context().addCookies([
    {
      name: payload.names.auth,
      value: payload.cookies.auth,
      domain,
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'Lax',
    },
    {
      name: payload.names.logged_in,
      value: payload.cookies.logged_in,
      domain,
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'Lax',
    },
    {
      name: payload.names.secure_auth,
      value: payload.cookies.secure_auth,
      domain,
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'Lax',
    },
  ]);
}
