/**
 * WP-CLI helpers for F9 acceptance (production plugin, no spike inject).
 */
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { ARTIFACTS_DIR, SPIKE_FIXTURES, TOOLS_DIR } from './env';

const WORDPRESS_COMPOSE = '/opt/biopentra/apps/wordpress';
const AIML_ROOT = '/opt/biopentra/dev/ai-multilingual';

function wp(args: string, input?: string): string {
  const cmd = `cd ${WORDPRESS_COMPOSE} && docker compose run --rm -T -v ${AIML_ROOT}:/aiml:ro wpcli wp ${args}`;
  return execSync(cmd, {
    encoding: 'utf8',
    stdio: ['pipe', 'pipe', 'pipe'],
    input,
  }).trim();
}

export function enableStrategyFlags(includeFrontend = false): void {
  const frontend = includeFrontend ? 'true' : 'false';
  wp(
    `eval '
$flags = AIMultilingual\\Settings::defaults();
$flags["block_attr_registration_enabled"] = true;
$flags["block_uuid_injection_enabled"] = true;
$flags["block_extraction_enabled"] = true;
$flags["block_frontend_rendering_enabled"] = ${frontend};
$flags = AIMultilingual\\Settings::sanitize( $flags );
update_option( AIMultilingual\\Settings::OPTION, $flags );
if ( function_exists( "wp_cache_flush" ) ) { wp_cache_flush(); }
echo (int) $flags["block_frontend_rendering_enabled"];
' --user=1`
  );

  const settings = getSettings();
  if (includeFrontend && !settings.block_frontend_rendering_enabled) {
    throw new Error('enableStrategyFlags(true) did not persist block_frontend_rendering_enabled');
  }
  if (!settings.block_attr_registration_enabled || !settings.block_uuid_injection_enabled || !settings.block_extraction_enabled) {
    throw new Error('enableStrategyFlags() did not persist prerequisite Strategy F flags');
  }
}

export function restoreStrategyFlags(): void {
  wp(
    `eval 'update_option( AIMultilingual\\Settings::OPTION, AIMultilingual\\Settings::defaults() ); if ( function_exists( "wp_cache_flush" ) ) { wp_cache_flush(); } echo "ok";' --user=1`
  );
}

export function submitInvalidStrategyFlagCombo(): string {
  return wp('eval-file /aiml/acceptance/f9-browser/tools/submit-invalid-strategy-flag.php --user=1').trim();
}

export function deletePostsBySlug(slug: string): void {
  const ids = wp(`post list --post_type=page --name=${slug} --format=ids --user=1`);
  if (ids) {
    wp(`post delete ${ids} --force --user=1`);
  }
}

export function createDraftPost(slug: string, title: string, fixtureFile: string): number {
  deletePostsBySlug(slug);
  const contentPath = path.join(SPIKE_FIXTURES, fixtureFile);
  const content = fs.readFileSync(contentPath, 'utf8');
  const postId = wp(
    `post create - --post_type=page --post_status=draft --post_title=${JSON.stringify(title)} --post_name=${slug} --porcelain --user=1`,
    content
  );
  return parseInt(postId, 10);
}

export function publishPost(postId: number, slug: string): void {
  wp(`post update ${postId} --post_status=publish --post_name=${slug} --user=1`);
  const status = wp(`post get ${postId} --field=status --user=1`);
  if ('publish' !== status) {
    throw new Error(`publishPost(${postId}) expected publish, got ${status}`);
  }
}

/** Enable frontend rendering and fetch a public URL (cache-busted). */
export function fetchPublicHtml(path: string): string {
  enableStrategyFlags(true);
  const base = process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu';
  return httpGet(`${base}${path}${path.includes('?') ? '&' : '?'}t=${Date.now()}`);
}

export function toContainerPath(hostPath: string): string {
  return hostPath.replace(AIML_ROOT, '/aiml');
}

export function exportPost(postId: number, slug: string, baseline?: string): {
  contentFile: string;
  analysisFile: string;
  analysis: Record<string, unknown>;
} {
  fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
  const script = path.join(TOOLS_DIR, 'export-f9-post.sh');
  const baselineArg = baseline ? ` "${baseline}"` : '';
  execSync(`"${script}" ${postId} "${slug}"${baselineArg}`, { encoding: 'utf8' });

  const contentFile = path.join(ARTIFACTS_DIR, `${slug}-post-${postId}.html`);
  const analysisFile = path.join(ARTIFACTS_DIR, `${slug}-analysis.json`);
  const analysis = JSON.parse(fs.readFileSync(analysisFile, 'utf8')) as Record<string, unknown>;
  return { contentFile, analysisFile, analysis };
}

export function snapshotBaseline(postId: number, slug: string): { containerPath: string; sha1: string } {
  const { contentFile, analysis } = exportPost(postId, `${slug}-baseline`);
  return { containerPath: toContainerPath(contentFile), sha1: analysis.content_sha1 as string };
}

export function deletePost(postId: number): void {
  wp(`post delete ${postId} --force --user=1`);
}

export function runReplayGate(postId: number): Record<string, unknown> {
  const raw = wp(`eval-file /aiml/acceptance/f9-browser/tools/replay-render-gate.php ${postId} --user=1`);
  return JSON.parse(raw) as Record<string, unknown>;
}

export function getBlockSourceHtml(postId: number, blockIndex = 0): string {
  const raw = wp(
    `eval '
$post = get_post( ${postId} );
$content = (string) $post->post_content;
if ( ! function_exists( "parse_blocks" ) || ! has_blocks( $content ) ) { echo ""; return; }
$registry = new AIMultilingual\\Block\\BlockRegistry();
$walker = new AIMultilingual\\Block\\BlockTreeWalker();
$idx = 0;
$found = "";
$walker->walk(
  parse_blocks( $content ),
  function ( array $block ) use ( &$idx, &$found, $registry ) {
    if ( "" !== $found || ! $registry->is_eligible( $block ) ) {
      return;
    }
    if ( $idx === ${blockIndex} ) {
      $found = (string) ( $block["innerHTML"] ?? "" );
      return;
    }
    ++$idx;
  }
);
echo $found;
' --user=1`
  );
  return raw;
}

export function saveBlockTranslation(
  postId: number,
  uuid: string,
  translatedHtml: string,
  sourceHtml = '<p>Hello</p>'
): void {
  wp(
    `eval '
$cache = new AIMultilingual\\Cache\\Cache();
$languages = new AIMultilingual\\Language\\Languages( $cache );
$sv = $languages->find_by_code( "sv" );
if ( ! $sv ) { echo "no_sv"; return; }
$store = new AIMultilingual\\Translation\\Store( $cache );
$key = AIMultilingual\\Block\\SegmentKey::build( ${JSON.stringify(uuid)}, AIMultilingual\\Block\\Contract::FIELD_CONTENT );
$store->save_translation( array(
  "source_type" => AIMultilingual\\Translation\\Store::SOURCE_POST,
  "source_id" => ${postId},
  "source_subtype" => "page",
  "language_id" => (int) $sv->language_id,
  "field_key" => AIMultilingual\\Translation\\Extractor::FIELD_CONTENT,
  "segment_key" => $key,
  "segment_kind" => AIMultilingual\\Translation\\Store::KIND_BLOCK,
  "text_format" => AIMultilingual\\Translation\\Store::FORMAT_HTML,
  "source_text" => ${JSON.stringify(sourceHtml)},
  "translated_text" => ${JSON.stringify(translatedHtml)},
  "status" => AIMultilingual\\Translation\\Store::STATUS_REVIEWED,
) );
echo "ok";
' --user=1`
  );
}

export function blockStatusJson(): Record<string, unknown> {
  const raw = wp('aiml block status --user=1 --format=json');
  return JSON.parse(raw) as Record<string, unknown>;
}

export function migratePost(postId: number, dryRun: boolean): Record<string, unknown> {
  const flag = dryRun ? '--dry-run' : '';
  const raw = wp(`aiml block migrate --post-id=${postId} ${flag} --format=json --user=1`);
  const parsed = JSON.parse(raw) as Record<string, unknown>;
  if (Array.isArray(parsed.posts) && parsed.posts.length > 0) {
    return parsed.posts[0] as Record<string, unknown>;
  }
  return parsed;
}

export function saveTranslationForPost(
  postId: number,
  slug: string,
  translatedHtml: string,
  sourceHtml?: string
): string {
  const analysisFile = exportPost(postId, slug).analysis as { blocks?: Array<{ uuid?: string }> };
  const uuid = analysisFile.blocks?.[0]?.uuid ?? '';
  if (!uuid) {
    throw new Error(`No UUID found for post ${postId} (${slug})`);
  }
  const source = sourceHtml ?? getBlockSourceHtml(postId, 0);
  if (!source) {
    throw new Error(`No eligible block source HTML for post ${postId} (${slug})`);
  }
  saveBlockTranslation(postId, uuid, translatedHtml, source);
  return uuid;
}

export function getSettings(): Record<string, unknown> {
  const raw = wp('option get aiml_settings --format=json --user=1');
  return JSON.parse(raw) as Record<string, unknown>;
}

export function contentSha256(postId: number): string {
  return wp(`eval 'echo hash("sha256", (string) get_post(${postId})->post_content);' --user=1`);
}

export function countBlockTranslations(postId: number): number {
  const raw = wp(
    `eval '
$store = new AIMultilingual\\Translation\\Store( new AIMultilingual\\Cache\\Cache() );
echo $store->count_block_segments( AIMultilingual\\Translation\\Store::SOURCE_POST, ${postId} );
' --user=1`
  );
  return parseInt(raw, 10);
}

export function httpGet(url: string): string {
  return execSync(
    `curl -sL -H "Cache-Control: no-cache" -H "Pragma: no-cache" "${url}"`,
    { encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 }
  );
}
