/**
 * F9 browser acceptance environment paths.
 */
import fs from 'fs';
import path from 'path';

export type WpCredentials = {
  baseUrl: string;
  user: string;
  password: string;
};

const DEFAULT_CREDENTIALS = '/opt/biopentra/apps/wordpress/.admin-credentials';
const REPO_ROOT = path.resolve(__dirname, '../../..');
export const F9_ROOT = path.resolve(__dirname, '..');
export const ARTIFACTS_DIR = path.join(F9_ROOT, 'artifacts');
export const SPIKE_FIXTURES = path.join(REPO_ROOT, 'spike/s5/browser-validation/fixtures');
export const TOOLS_DIR = path.join(F9_ROOT, 'tools');

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

export const authCookieFile = (): string => path.join(ARTIFACTS_DIR, 'auth-cookies.json');
