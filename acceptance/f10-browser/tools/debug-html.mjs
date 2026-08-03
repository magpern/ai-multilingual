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
const response = await page.goto('https://dev.biopentra.eu/wp-admin/admin.php?page=aiml-translator&post_id=6321&language=sv', { waitUntil: 'networkidle' });
const html = await response?.text();
console.log('has bundle error', html?.includes('workspace bundle is not built'));
console.log('has script handle', html?.includes('aiml-translator-workspace'));
console.log('has index.js path', html?.includes('translator-workspace/build/index.js'));
console.log('has aimlTranslatorWorkspace', html?.includes('aimlTranslatorWorkspace'));
const scriptLines = html?.split('\n').filter((l) => l.includes('aiml-translator') || l.includes('aimlTranslator')) ?? [];
console.log('matching lines', scriptLines.slice(0, 5));
await browser.close();
