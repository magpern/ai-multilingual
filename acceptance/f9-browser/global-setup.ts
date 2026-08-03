/**
 * Start a persistent WP-CLI container for the F9 suite to avoid per-call compose spin-up.
 */
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { ARTIFACTS_DIR } from './helpers/env';

const WORDPRESS_COMPOSE = '/opt/biopentra/apps/wordpress';
const AIML_ROOT = '/opt/biopentra/dev/ai-multilingual';
const MARKER = path.join(ARTIFACTS_DIR, '.f9-wpcli-container');

export default async function globalSetup(): Promise<void> {
  fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
  const name = `f9-wpcli-${process.pid}`;
  try {
    execSync(`docker rm -f ${name}`, { stdio: 'ignore' });
  } catch {
    // absent container is fine
  }

  execSync(
    `cd ${WORDPRESS_COMPOSE} && docker compose --profile tools run -d --name ${name} -T ` +
      `-v ${AIML_ROOT}:/aiml:ro wpcli sleep 7200`,
    { stdio: 'pipe', encoding: 'utf8' }
  );

  execSync(`docker exec ${name} wp --info --path=/var/www/html >/dev/null`, {
    stdio: 'pipe',
    encoding: 'utf8',
  });

  fs.writeFileSync(MARKER, name, 'utf8');
  process.env.F9_WPCLI_CONTAINER = name;
}
