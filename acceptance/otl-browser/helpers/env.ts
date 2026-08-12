/**
 * OTL browser smoke environment helpers.
 */
import fs from 'fs';
import path from 'path';

export type WpCredentials = {
  baseUrl: string;
  user: string;
  password: string;
};

const DEFAULT_CREDENTIALS = '/opt/biopentra/apps/wordpress/.admin-credentials';
const F9_ARTIFACTS =
  process.env.F10_AUTH_COOKIES_DIR ??
  path.resolve(__dirname, '../../f9-browser/artifacts');

export const F10_POST_ID = Number(process.env.F10_POST_ID ?? '6321');
export const F10_LANGUAGE = process.env.F10_LANGUAGE ?? 'sv';

export function authCookieFile(): string {
  if (process.env.F10_AUTH_COOKIES) {
    return process.env.F10_AUTH_COOKIES;
  }

  return path.join(F9_ARTIFACTS, 'auth-cookies.json');
}

export function loadCredentials(): WpCredentials {
  const baseUrl = process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu';
  const file = process.env.WP_CREDENTIALS_FILE ?? DEFAULT_CREDENTIALS;

  if (process.env.WP_ADMIN_USER && process.env.WP_ADMIN_PASS) {
    return {
      baseUrl,
      user: process.env.WP_ADMIN_USER,
      password: process.env.WP_ADMIN_PASS,
    };
  }

  const raw = fs.readFileSync(file, 'utf8');
  const userMatch = raw.match(/^user:\s*(\S+)/m);
  const passMatch = raw.match(/^password:\s*(\S+)/m);

  if (!userMatch || !passMatch) {
    throw new Error(`Could not parse user/password from ${file}`);
  }

  return {
    baseUrl,
    user: userMatch[1],
    password: passMatch[1],
  };
}
