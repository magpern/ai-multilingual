/**
 * Suite teardown — restore settings, cleanup clones/corpus, stop WP-CLI pool.
 */
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { ARTIFACTS_DIR } from './helpers/env';
import { cleanupSessionClones } from './helpers/fixture-corpus';
import { endPhase, startPhase, writeSuiteTimingReport } from './helpers/fixture-instrumentation';
import { loadManifest, MANIFEST_PATH } from './helpers/fixture-manifest';
import { releaseSuiteLock } from './helpers/suite-environment';
import {
  clearSessionClones,
  deleteF9PostsByPrefix,
  deletePost,
  F9_CLONE_PREFIX,
  F9_CORPUS_PREFIX,
  restoreSettingsBaseline,
} from './helpers/wp-cli';

const MARKER = path.join(ARTIFACTS_DIR, '.f9-wpcli-container');

export default async function globalTeardown(): Promise<void> {
  startPhase('teardown');

  try {
    restoreSettingsBaseline();
  } catch (err) {
    console.error('[f9-teardown] settings restore failed:', err);
  }

  const clonesRemoved = cleanupSessionClones();
  deleteF9PostsByPrefix(F9_CLONE_PREFIX);

  const mode = process.env.F9_FIXTURE_MODE ?? 'reuse';
  let corpusRemoved = 0;
  if (mode === 'fresh') {
    if (fs.existsSync(MANIFEST_PATH)) {
      try {
        const manifest = loadManifest();
        for (const record of Object.values(manifest.fixtures)) {
          if (record.post_id) {
            try {
              deletePost(record.post_id);
              corpusRemoved++;
            } catch {
              // best-effort
            }
          }
        }
      } catch {
        deleteF9PostsByPrefix(F9_CORPUS_PREFIX);
      }
      fs.unlinkSync(MANIFEST_PATH);
    }
  }

  clearSessionClones();

  const summary = {
    clones_removed: clonesRemoved,
    corpus_removed: corpusRemoved,
    mode,
    completed_at: new Date().toISOString(),
  };
  fs.writeFileSync(path.join(ARTIFACTS_DIR, 'f9-teardown-summary.json'), JSON.stringify(summary, null, 2));

  endPhase('teardown');
  writeSuiteTimingReport({ teardown_ms: undefined });

  const name = fs.existsSync(MARKER)
    ? fs.readFileSync(MARKER, 'utf8').trim()
    : process.env.F9_WPCLI_CONTAINER ?? '';
  if (name) {
    try {
      execSync(`docker rm -f ${name}`, { stdio: 'ignore' });
    } catch {
      // best-effort
    }
    fs.unlinkSync(MARKER);
  }

  releaseSuiteLock();
}
