/**
 * Stop the persistent WP-CLI container started in global-setup.
 */
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { ARTIFACTS_DIR } from './helpers/env';

const MARKER = path.join(ARTIFACTS_DIR, '.f9-wpcli-container');

export default async function globalTeardown(): Promise<void> {
  const name = fs.existsSync(MARKER)
    ? fs.readFileSync(MARKER, 'utf8').trim()
    : process.env.F9_WPCLI_CONTAINER ?? '';
  if (!name) {
    return;
  }
  try {
    execSync(`docker rm -f ${name}`, { stdio: 'ignore' });
  } catch {
    // best-effort cleanup
  }
  fs.unlinkSync(MARKER);
}
