/**
 * F9 suite instrumentation — WP-CLI call counts and phase timings.
 */
import fs from 'fs';
import path from 'path';
import { ARTIFACTS_DIR } from './env';

export type InstrumentationSnapshot = {
  wp_cli_calls: number;
  clone_count: number;
  shared_fixture_usage: number;
  phases_ms: Record<string, number>;
  started_at: string;
  updated_at: string;
};

const INSTRUMENTATION_FILE = path.join(ARTIFACTS_DIR, 'f9-instrumentation.json');
const TIMING_FILE = path.join(ARTIFACTS_DIR, 'f9-suite-timing.json');

let memory: InstrumentationSnapshot = {
  wp_cli_calls: 0,
  clone_count: 0,
  shared_fixture_usage: 0,
  phases_ms: {},
  started_at: new Date().toISOString(),
  updated_at: new Date().toISOString(),
};

const phaseStarts: Record<string, number> = {};

function persist(): void {
  memory.updated_at = new Date().toISOString();
  fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
  fs.writeFileSync(INSTRUMENTATION_FILE, JSON.stringify(memory, null, 2));
}

export function resetInstrumentation(): void {
  memory = {
    wp_cli_calls: 0,
    clone_count: 0,
    shared_fixture_usage: 0,
    phases_ms: {},
    started_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
  };
  persist();
}

export function loadInstrumentation(): InstrumentationSnapshot {
  if (fs.existsSync(INSTRUMENTATION_FILE)) {
    memory = JSON.parse(fs.readFileSync(INSTRUMENTATION_FILE, 'utf8')) as InstrumentationSnapshot;
  }
  return { ...memory };
}

export function incrementWpCliCalls(count = 1): void {
  memory.wp_cli_calls += count;
  persist();
}

export function incrementCloneCount(count = 1): void {
  memory.clone_count += count;
  persist();
}

export function incrementSharedFixtureUsage(count = 1): void {
  memory.shared_fixture_usage += count;
  persist();
}

export function startPhase(name: string): void {
  phaseStarts[name] = Date.now();
}

export function endPhase(name: string): void {
  const start = phaseStarts[name];
  if (!start) {
    return;
  }
  memory.phases_ms[name] = (memory.phases_ms[name] ?? 0) + (Date.now() - start);
  delete phaseStarts[name];
  persist();
}

export type SuiteTimingReport = {
  environment_setup_ms: number;
  fixture_seed_ms: number;
  fixture_validation_ms: number;
  browser_execution_ms: number;
  php_quality_gates_ms: number;
  teardown_ms: number;
  total_playwright_wall_ms: number;
  wp_cli_calls: number;
  clone_count: number;
  shared_fixture_usage: number;
  recorded_at: string;
};

export function writeSuiteTimingReport(partial: Partial<SuiteTimingReport>): void {
  const instr = loadInstrumentation();
  const report: SuiteTimingReport = {
    environment_setup_ms: partial.environment_setup_ms ?? instr.phases_ms.suite_environment ?? 0,
    fixture_seed_ms: partial.fixture_seed_ms ?? instr.phases_ms.fixture_seed ?? 0,
    fixture_validation_ms: partial.fixture_validation_ms ?? instr.phases_ms.fixture_validation ?? 0,
    browser_execution_ms: partial.browser_execution_ms ?? instr.phases_ms.browser_execution ?? 0,
    php_quality_gates_ms: partial.php_quality_gates_ms ?? 0,
    teardown_ms: partial.teardown_ms ?? instr.phases_ms.teardown ?? 0,
    total_playwright_wall_ms: partial.total_playwright_wall_ms ?? 0,
    wp_cli_calls: instr.wp_cli_calls,
    clone_count: instr.clone_count,
    shared_fixture_usage: instr.shared_fixture_usage,
    recorded_at: new Date().toISOString(),
  };
  fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
  fs.writeFileSync(TIMING_FILE, JSON.stringify(report, null, 2));
}

export function instrumentationPaths(): { instrumentation: string; timing: string } {
  return { instrumentation: INSTRUMENTATION_FILE, timing: TIMING_FILE };
}
