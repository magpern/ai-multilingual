/**
 * Start suite environment and persistent WP-CLI pool before F9 tests.
 */
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { ARTIFACTS_DIR } from './helpers/env';
import { setupSuiteEnvironment } from './helpers/suite-environment';

const WORDPRESS_COMPOSE = '/opt/biopentra/apps/wordpress';
const AIML_ROOT = '/opt/biopentra/dev/ai-multilingual';
const MARKER = path.join(ARTIFACTS_DIR, '.f9-wpcli-container');

export default async function globalSetup(): Promise<void> {
  fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });

  if (fs.existsSync(MARKER)) {
    const stale = fs.readFileSync(MARKER, 'utf8').trim();
    if (stale) {
      try {
        execSync(`docker rm -f ${stale}`, { stdio: 'ignore' });
      } catch {
        // absent container is fine
      }
    }
    fs.unlinkSync(MARKER);
  }

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

  const env = await setupSuiteEnvironment();
  fs.writeFileSync(path.join(ARTIFACTS_DIR, 'f9-suite-environment.json'), JSON.stringify(env, null, 2));
}
