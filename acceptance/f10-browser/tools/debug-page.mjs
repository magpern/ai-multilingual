import fs from 'fs';
import { chromium } from '@playwright/test';

const cookieFile = process.env.F10_AUTH_COOKIES ?? '/app/artifacts/auth-cookies.json';
const payload = JSON.parse(fs.readFileSync(cookieFile, 'utf8'));
const domain = 'dev.biopentra.eu';
const browser = await chromium.launch();
const context = await browser.newContext();
await context.addCookies([
  { name: payload.names.auth, value: payload.cookies.auth, domain, path: '/', httpOnly: true, secure: true, sameSite: 'Lax' },
  { name: payload.names.logged_in, value: payload.cookies.logged_in, domain, path: '/', httpOnly: true, secure: true, sameSite: 'Lax' },
  { name: payload.names.secure_auth, value: payload.cookies.secure_auth, domain, path: '/', httpOnly: true, secure: true, sameSite: 'Lax' },
]);
const page = await context.newPage();
page.on('console', (m) => console.log('CONSOLE', m.type(), m.text()));
page.on('pageerror', (e) => console.log('PAGEERROR', e.message));
await page.goto('https://dev.biopentra.eu/wp-admin/admin.php?page=aiml-translator&post_id=6321&language=sv', { waitUntil: 'networkidle' });
const srcs = await page.locator('script[src]').evaluateAll((els) =>
  els.map((el) => el.getAttribute('src') ?? '').filter((src) => src.includes('ai-multilingual') || src.includes('translator'))
);
console.log('scripts', JSON.stringify(srcs, null, 2));
console.log('root length', (await page.locator('#aiml-translator-workspace-root').innerHTML()).length);
await browser.close();
