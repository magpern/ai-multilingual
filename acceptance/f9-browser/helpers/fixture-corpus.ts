/**
 * F9 canonical fixture corpus — seed, validate, clone, shared access.
 */
import fs from 'fs';
import { TestInfo } from '@playwright/test';
import { execSync } from 'child_process';
import {
  ALL_CORPUS_KEYS,
  attachChecksum,
  computeManifestChecksum,
  CorpusKey,
  FixtureBlockRecord,
  FixtureManifest,
  FixtureRecord,
  FixtureTranslationRecord,
  hashText,
  loadManifest,
  MANIFEST_PATH,
  writeManifest,
  validateManifestStructure,
} from './fixture-manifest';
import {
  endPhase,
  incrementCloneCount,
  incrementSharedFixtureUsage,
  startPhase,
} from './fixture-instrumentation';
import { ARTIFACTS_DIR } from './env';
import {
  clonePost,
  clearSessionClones,
  contentSha256,
  countTranslationsForPost,
  createDraftPost,
  deleteF9PostsByPrefix,
  deletePost,
  enableStrategyFlags,
  exportPost,
  F9_CLONE_PREFIX,
  F9_CORPUS_PREFIX,
  getBlockSourceHtml,
  listSessionClones,
  migratePost,
  postExists,
  publishPost,
  rerunSavePipeline,
  saveTranslationForPost,
  trackSessionClone,
} from './wp-cli';

type CorpusSpec = {
  key: CorpusKey;
  slug: string;
  title: string;
  template: string;
  status: 'draft' | 'publish';
  translatedHtml?: string;
  marker?: string;
  skipPipeline?: boolean;
  migrateAfterCreate?: boolean;
};

const CORPUS_SPECS: CorpusSpec[] = [
  { key: 'paragraph_uuid_draft', slug: `${F9_CORPUS_PREFIX}paragraph-draft`, title: 'F9 Corpus Paragraph', template: 'core-paragraph.html', status: 'draft' },
  { key: 'heading_uuid_draft', slug: `${F9_CORPUS_PREFIX}heading-draft`, title: 'F9 Corpus Heading', template: 'core-heading.html', status: 'draft' },
  { key: 'button_uuid_draft', slug: `${F9_CORPUS_PREFIX}button-draft`, title: 'F9 Corpus Button', template: 'core-buttons.html', status: 'draft' },
  { key: 'multi_paragraph_uuid_draft', slug: `${F9_CORPUS_PREFIX}multi-draft`, title: 'F9 Corpus Multi', template: 'operations-multi-paragraph.html', status: 'draft' },
  { key: 'duplicate_single_uuid_draft', slug: `${F9_CORPUS_PREFIX}duplicate-draft`, title: 'F9 Corpus Duplicate', template: 'duplicate-single.html', status: 'draft' },
  {
    key: 'paragraph_translated_pub',
    slug: `${F9_CORPUS_PREFIX}paragraph-sv`,
    title: 'F9 Corpus Paragraph SV',
    template: 'core-paragraph.html',
    status: 'publish',
    translatedHtml: '<p>Hej stycke</p>',
    marker: 'Hej stycke',
  },
  {
    key: 'heading_translated_pub',
    slug: `${F9_CORPUS_PREFIX}heading-sv`,
    title: 'F9 Corpus Heading SV',
    template: 'core-heading.html',
    status: 'publish',
    translatedHtml: '<h2 class="wp-block-heading">Hej rubrik</h2>',
    marker: 'Hej rubrik',
  },
  {
    key: 'button_translated_pub',
    slug: `${F9_CORPUS_PREFIX}button-sv`,
    title: 'F9 Corpus Button SV',
    template: 'core-buttons.html',
    status: 'publish',
    translatedHtml: '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.com/a">Klicka</a></div>',
    marker: 'Klicka',
  },
  {
    key: 'paragraph_stale_pub',
    slug: `${F9_CORPUS_PREFIX}paragraph-stale`,
    title: 'F9 Corpus Stale SV',
    template: 'core-paragraph.html',
    status: 'publish',
    translatedHtml: '<p>Gammal</p>',
    marker: 'Gammal',
  },
  {
    key: 'scope_a_pub',
    slug: `${F9_CORPUS_PREFIX}scope-a`,
    title: 'F9 Corpus Scope A',
    template: 'core-paragraph.html',
    status: 'publish',
    translatedHtml: '<p>Only A</p>',
    marker: 'Only A',
  },
  {
    key: 'scope_b_pub',
    slug: `${F9_CORPUS_PREFIX}scope-b`,
    title: 'F9 Corpus Scope B',
    template: 'core-paragraph.html',
    status: 'publish',
  },
  {
    key: 'migration_raw_draft',
    slug: `${F9_CORPUS_PREFIX}migration-raw`,
    title: 'F9 Corpus Migration Raw',
    template: 'core-paragraph.html',
    status: 'draft',
    skipPipeline: true,
  },
  {
    key: 'migration_done_draft',
    slug: `${F9_CORPUS_PREFIX}migration-done`,
    title: 'F9 Corpus Migration Done',
    template: 'core-paragraph.html',
    status: 'draft',
    skipPipeline: true,
    migrateAfterCreate: true,
  },
];

function gitCommit(): string {
  if (process.env.F9_GIT_COMMIT) {
    return process.env.F9_GIT_COMMIT;
  }
  try {
    return execSync('git -c safe.directory=* rev-parse HEAD', {
      encoding: 'utf8',
      cwd: '/opt/biopentra/dev/ai-multilingual',
    }).trim();
  } catch {
    return 'unknown';
  }
}

function analysisToBlocks(analysis: Record<string, unknown>): FixtureBlockRecord[] {
  const blocks = (analysis.blocks as Array<Record<string, unknown>>) ?? [];
  return blocks.map((block, index) => ({
    index,
    block_name: String(block.block_name ?? ''),
    uuid: String(block.uuid ?? ''),
    text_sha1: String(block.text_sha1 ?? ''),
  }));
}

function buildFixtureRecord(postId: number, spec: CorpusSpec): FixtureRecord {
  const exportSlug = `${spec.slug}-manifest`;
  const { analysis } = exportPost(postId, exportSlug);
  const blocks = analysisToBlocks(analysis);
  const translation_rows: FixtureTranslationRecord[] = [];

  if (spec.translatedHtml && blocks[0]?.uuid) {
    const source = getBlockSourceHtml(postId, 0);
    translation_rows.push({
      language: 'sv',
      segment_key: blocks[0].uuid,
      translated_text_hash: hashText(spec.translatedHtml),
      status: 'reviewed',
    });
  }

  return {
    post_id: postId,
    slug: spec.slug,
    status: spec.status,
    template: spec.template,
    content_hash: contentSha256(postId),
    blocks,
    translation_rows,
  };
}

function seedOneFixture(spec: CorpusSpec): FixtureRecord {
  if (spec.skipPipeline) {
    enableStrategyFlags(false);
  } else {
    enableStrategyFlags(false);
  }

  const postId = createDraftPost(spec.slug, spec.title, spec.template);

  if (!spec.skipPipeline) {
    rerunSavePipeline(postId);
    const analysis = exportPost(postId, `${spec.slug}-seed`).analysis as { blocks?: Array<{ uuid?: string }> };
    if (!analysis.blocks?.length || !analysis.blocks[0]?.uuid) {
      throw new Error(`Corpus seed failed UUID injection for ${spec.key}`);
    }
  }

  if (spec.migrateAfterCreate) {
    migratePost(postId, false);
  }

  if (spec.status === 'publish') {
    publishPost(postId, spec.slug);
  }

  if (spec.translatedHtml) {
    saveTranslationForPost(postId, `${spec.slug}-seed-tr`, spec.translatedHtml);
  }

  return buildFixtureRecord(postId, spec);
}

function validateLiveFixture(record: FixtureRecord): void {
  if (!postExists(record.post_id)) {
    throw new Error(`Corpus fixture missing post_id=${record.post_id} slug=${record.slug}`);
  }
  const liveHash = contentSha256(record.post_id);
  if (liveHash !== record.content_hash) {
    throw new Error(`Corpus fixture hash drift for ${record.slug}: manifest=${record.content_hash} live=${liveHash}`);
  }
}

export function seedCorpusFresh(mode: 'fresh' | 'reuse', commit: string, baseUrl: string): FixtureManifest {
  startPhase('fixture_seed');
  deleteF9PostsByPrefix(F9_CLONE_PREFIX);
  if (mode === 'fresh') {
    deleteF9PostsByPrefix(F9_CORPUS_PREFIX);
  }

  const fixtures = {} as Record<CorpusKey, FixtureRecord>;
  for (const spec of CORPUS_SPECS) {
    fixtures[spec.key] = seedOneFixture(spec);
  }

  const body = {
    schema_version: 1,
    mode,
    commit,
    wp_base_url: baseUrl,
    created_at: new Date().toISOString(),
    fixtures,
  };
  const manifest = attachChecksum(body);
  writeManifest(manifest);
  endPhase('fixture_seed');
  return manifest;
}

export function validateOrSeedCorpus(options: {
  mode: 'fresh' | 'reuse';
  commit: string;
  baseUrl: string;
}): FixtureManifest {
  startPhase('fixture_validation');
  if (options.mode === 'fresh') {
    endPhase('fixture_validation');
    return seedCorpusFresh('fresh', options.commit, options.baseUrl);
  }

  if (!fs.existsSync(MANIFEST_PATH)) {
    throw new Error(`Reuse mode requires manifest at ${MANIFEST_PATH}`);
  }

  const manifest = loadManifest();
  validateManifestStructure(manifest, {
    expectedUrl: options.baseUrl,
  });

  for (const key of ALL_CORPUS_KEYS) {
    validateLiveFixture(manifest.fixtures[key]);
  }

  manifest.validated_at = new Date().toISOString();
  manifest.checksum = computeManifestChecksum(manifest);
  writeManifest(manifest);
  endPhase('fixture_validation');
  return manifest;
}

export function getSharedFixture(key: CorpusKey): FixtureRecord {
  const manifest = loadManifest();
  validateManifestStructure(manifest, { expectedUrl: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu' });
  incrementSharedFixtureUsage();
  return manifest.fixtures[key];
}

export function sanitizeTestId(testId: string): string {
  return testId.replace(/[^a-zA-Z0-9_-]+/g, '-').slice(0, 48);
}

export function cloneFixtureForTest(key: CorpusKey, testInfo: Pick<TestInfo, 'testId' | 'title'>): number {
  const source = getSharedFixture(key);
  const suffix = `${sanitizeTestId(testInfo.testId)}-${Date.now()}`;
  const slug = `${F9_CLONE_PREFIX}${suffix}`;
  const title = `F9 Clone ${testInfo.title}`.slice(0, 120);
  const postId = clonePost(source.post_id, slug, title);
  trackSessionClone(postId);
  incrementCloneCount();
  return postId;
}

export function deleteTrackedClone(postId: number): void {
  if (postExists(postId)) {
    deletePost(postId);
  }
}

export function cleanupSessionClones(): number {
  const clones = listSessionClones();
  let removed = 0;
  for (const id of clones) {
    if (postExists(id)) {
      deletePost(id);
      removed++;
    }
  }
  clearSessionClones();
  return removed;
}

export function corpusSpecForKey(key: CorpusKey): CorpusSpec | undefined {
  return CORPUS_SPECS.find((spec) => spec.key === key);
}

export { CORPUS_SPECS, MANIFEST_PATH, ARTIFACTS_DIR };
