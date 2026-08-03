/**
 * F9 suite-level environment — runs once before Playwright workers.
 */
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { ARTIFACTS_DIR, authCookieFile } from './env';
import { validateOrSeedCorpus } from './fixture-corpus';
import { endPhase, resetInstrumentation, startPhase, writeSuiteTimingReport } from './fixture-instrumentation';
import {
  deleteF9PostsByPrefix,
  enableStrategyFlags,
  F9_CLONE_PREFIX,
  F9_CORPUS_PREFIX,
  getWpCoreVersion,
  httpGet,
  saveSettingsBaseline,
} from './wp-cli';

const AIML_ROOT = '/opt/biopentra/dev/ai-multilingual';
const LOCK_FILE = '/tmp/biopentra-browser-acceptance.lock';
const LOCK_META = path.join(ARTIFACTS_DIR, 'f9-suite-lock.json');

export type SuiteEnvironmentResult = {
  commit: string;
  baseUrl: string;
  mode: 'fresh' | 'reuse';
  wpVersion: string;
};

function gitCommit(): string {
  if (process.env.F9_GIT_COMMIT) {
    return process.env.F9_GIT_COMMIT;
  }
  try {
    return execSync('git -c safe.directory=* rev-parse HEAD', { encoding: 'utf8', cwd: AIML_ROOT }).trim();
  } catch {
    return 'unknown';
  }
}

function fixtureMode(): 'fresh' | 'reuse' {
  const mode = process.env.F9_FIXTURE_MODE ?? 'reuse';
  if (mode !== 'fresh' && mode !== 'reuse') {
    throw new Error(`Invalid F9_FIXTURE_MODE=${mode} — expected fresh or reuse`);
  }
  return mode;
}

function acquireSuiteLock(): void {
  if (process.env.F9_SKIP_LOCK === '1') {
    return;
  }
  fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
  try {
    execSync(`bash -c 'exec 200>"${LOCK_FILE}" && flock -n 200 || exit 2'`, { stdio: 'pipe' });
    fs.writeFileSync(
      LOCK_META,
      JSON.stringify({ pid: process.pid, acquired_at: new Date().toISOString(), commit: gitCommit() }, null, 2)
    );
  } catch {
    throw new Error(`F9 acceptance lock busy: ${LOCK_FILE}`);
  }
}

function verifyReachable(baseUrl: string): void {
  const status = execSync(`curl -s -o /dev/null -w '%{http_code}' "${baseUrl}/"`, { encoding: 'utf8' }).trim();
  if (status !== '200') {
    throw new Error(`${baseUrl} unreachable — HTTP ${status}`);
  }
}

function generateAuthCookies(): void {
  execSync(`bash "${AIML_ROOT}/acceptance/f9-browser/tools/write-auth-cookies-json.sh"`, {
    encoding: 'utf8',
    stdio: 'pipe',
  });
  if (!fs.existsSync(authCookieFile())) {
    throw new Error('Auth cookie generation failed');
  }
}

export async function setupSuiteEnvironment(): Promise<SuiteEnvironmentResult> {
  resetInstrumentation();
  startPhase('suite_environment');
  fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });

  acquireSuiteLock();
  const baseUrl = process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu';
  const commit = gitCommit();
  const mode = fixtureMode();

  verifyReachable(baseUrl);
  generateAuthCookies();

  const wpVersion = getWpCoreVersion();
  if (!wpVersion) {
    throw new Error('WP-CLI pool unavailable — could not read WordPress version');
  }

  // Remove interrupted clone debris; fresh mode also clears corpus before re-seed.
  deleteF9PostsByPrefix(F9_CLONE_PREFIX);
  if (mode === 'fresh') {
    deleteF9PostsByPrefix(F9_CORPUS_PREFIX);
  }

  enableStrategyFlags(false);
  saveSettingsBaseline();

  validateOrSeedCorpus({ mode, commit, baseUrl });

  // Smoke probe — ensures public routing works before tests start.
  httpGet(`${baseUrl}/?t=${Date.now()}`);

  endPhase('suite_environment');
  writeSuiteTimingReport({});

  return { commit, baseUrl, mode, wpVersion };
}

export function releaseSuiteLock(): void {
  if (fs.existsSync(LOCK_META)) {
    fs.unlinkSync(LOCK_META);
  }
}
