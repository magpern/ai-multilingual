/**
 * WP-CLI helpers for post setup and content export. THROWAWAY — spike/s5 only.
 */
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { CORPUS_DIR, FIXTURES_DIR, TOOLS_DIR } from './env';

const WORDPRESS_COMPOSE = '/opt/biopentra/apps/wordpress';

function wp(args: string, input?: string): string {
  const aimlRoot = '/opt/biopentra/dev/ai-multilingual';
  const cmd = `cd ${WORDPRESS_COMPOSE} && docker compose run --rm -T -v ${aimlRoot}:/aiml:ro wpcli wp ${args}`;
  return execSync(cmd, {
    encoding: 'utf8',
    stdio: ['pipe', 'pipe', 'pipe'],
    input,
  }).trim();
}

export function createTaggedPost(slug: string, title: string, fixtureFile: string): number {
  const contentPath = path.join(FIXTURES_DIR, fixtureFile);
  const content = fs.readFileSync(contentPath, 'utf8');
  const postId = wp(
    `post create - --post_type=page --post_status=draft --post_title=${JSON.stringify(title)} --post_name=${slug} --porcelain`,
    content
  );
  wp(`eval-file /aiml/spike/s5/tools/inject-aiml-block-ids.php ${postId}`);
  return parseInt(postId, 10);
}

/**
 * Container path (inside the wpcli image, where AIML_ROOT is mounted at
 * /aiml:ro) for a host path under the AIML repo root.
 */
export function toContainerPath(hostPath: string): string {
  const aimlRoot = '/opt/biopentra/dev/ai-multilingual';
  return hostPath.replace(aimlRoot, '/aiml');
}

/**
 * Export post_content + UUID analysis.
 *
 * `baseline` may be:
 *  - omitted: no before/after comparison, just current-state analysis.
 *  - a numeric post ID of a genuinely DISTINCT, untouched WordPress post
 *    (e.g. a pristine duplicate created for comparison purposes).
 *  - a baseline content-file container path (see `toContainerPath`) — the
 *    correct choice for "before this post_id was edited in the browser"
 *    comparisons. Passing `postId` itself as `baseline` is a self-comparison
 *    bug: it fetches the same live DB row twice and trivially reports
 *    "preserved" / "content_identical" regardless of what happened in the
 *    browser. (Discovered during Phase 3 harness repair — see
 *    IMPLEMENTATION_LOG.md.)
 */
export function exportPost(postId: number, slug: string, baseline?: number | string): {
  contentFile: string;
  analysisFile: string;
  analysis: Record<string, unknown>;
} {
  fs.mkdirSync(CORPUS_DIR, { recursive: true });
  const script = path.join(TOOLS_DIR, 'export-browser-validation-post.sh');
  const baselineArg = baseline !== undefined ? ` "${baseline}"` : '';
  execSync(`"${script}" ${postId} "${slug}"${baselineArg}`, { encoding: 'utf8' });

  const contentFile = path.join(CORPUS_DIR, `${slug}-post-${postId}.html`);
  const analysisFile = path.join(CORPUS_DIR, `${slug}-analysis.json`);
  const analysis = JSON.parse(fs.readFileSync(analysisFile, 'utf8')) as Record<string, unknown>;
  return { contentFile, analysisFile, analysis };
}

/** Capture a pre-operation baseline snapshot; returns the container path usable as `exportPost`'s baseline arg. */
export function snapshotBaseline(postId: number, slug: string): { hostPath: string; containerPath: string; sha1: string } {
  const { contentFile, analysis } = exportPost(postId, `${slug}-baseline`);
  return { hostPath: contentFile, containerPath: toContainerPath(contentFile), sha1: analysis.content_sha1 as string };
}

export function getPostContent(postId: number): string {
  return wp(`post get ${postId} --field=content`);
}

export function countRevisions(postId: number): number {
  const raw = wp(`post list --post_type=revision --post_parent=${postId} --format=count`);
  return parseInt(raw, 10) || 0;
}

export function deletePost(postId: number): void {
  wp(`post delete ${postId} --force`);
}

export function restGetPost(postId: number, authHeader: string): Record<string, unknown> {
  const url = `https://dev.biopentra.eu/wp-json/wp/v2/pages/${postId}?context=edit`;
  const raw = execSync(`curl -sS -H "${authHeader}" "${url}"`, { encoding: 'utf8' });
  return JSON.parse(raw) as Record<string, unknown>;
}

export function createAppPassword(user: string, appName: string): string {
  try {
    return wp(`user application-password create ${user} "${appName}" --porcelain`);
  } catch {
    // May already exist from prior run; create with unique suffix.
    return wp(
      `user application-password create ${user} "${appName}-${Date.now()}" --porcelain`
    );
  }
}

export function buildBasicAuth(user: string, appPassword: string): string {
  const token = Buffer.from(`${user}:${appPassword}`).toString('base64');
  return `Authorization: Basic ${token}`;
}
