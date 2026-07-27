/**
 * Load WordPress admin credentials from dev VPS credentials file.
 * Never logs secrets. THROWAWAY — spike/s5 only.
 */
import fs from 'fs';
import path from 'path';

export type WpCredentials = {
  baseUrl: string;
  user: string;
  password: string;
};

const DEFAULT_CREDENTIALS = '/opt/biopentra/apps/wordpress/.admin-credentials';

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

export const SPIKE_ROOT = path.resolve(__dirname, '../..');
export const CORPUS_DIR = path.join(SPIKE_ROOT, 'corpus', 'browser-validation');
export const FIXTURES_DIR = path.join(__dirname, '../fixtures');
export const TOOLS_DIR = path.join(SPIKE_ROOT, 'tools');

export const WP_VERSION = '7.0.2';
