/**
 * F9 canonical fixture manifest — schema, checksum, validation.
 */
import crypto from 'crypto';
import fs from 'fs';
import path from 'path';
import { ARTIFACTS_DIR } from './env';

export const MANIFEST_SCHEMA_VERSION = 1;

export type FixtureMode = 'fresh' | 'reuse';

export type CorpusKey =
  | 'paragraph_uuid_draft'
  | 'heading_uuid_draft'
  | 'button_uuid_draft'
  | 'multi_paragraph_uuid_draft'
  | 'duplicate_single_uuid_draft'
  | 'paragraph_translated_pub'
  | 'heading_translated_pub'
  | 'button_translated_pub'
  | 'paragraph_stale_pub'
  | 'scope_a_pub'
  | 'scope_b_pub'
  | 'migration_raw_draft'
  | 'migration_done_draft';

export type FixtureBlockRecord = {
  index: number;
  block_name: string;
  uuid: string;
  text_sha1: string;
};

export type FixtureTranslationRecord = {
  language: string;
  segment_key: string;
  translated_text_hash: string;
  status: string;
};

export type FixtureRecord = {
  post_id: number;
  slug: string;
  status: 'draft' | 'publish';
  template: string;
  content_hash: string;
  blocks: FixtureBlockRecord[];
  translation_rows: FixtureTranslationRecord[];
};

export type FixtureManifest = {
  schema_version: number;
  mode: FixtureMode;
  commit: string;
  wp_base_url: string;
  created_at: string;
  validated_at?: string;
  fixtures: Record<CorpusKey, FixtureRecord>;
  checksum: string;
};

export const MANIFEST_PATH = path.join(ARTIFACTS_DIR, 'f9-fixture-manifest.json');

export const ALL_CORPUS_KEYS: CorpusKey[] = [
  'paragraph_uuid_draft',
  'heading_uuid_draft',
  'button_uuid_draft',
  'multi_paragraph_uuid_draft',
  'duplicate_single_uuid_draft',
  'paragraph_translated_pub',
  'heading_translated_pub',
  'button_translated_pub',
  'paragraph_stale_pub',
  'scope_a_pub',
  'scope_b_pub',
  'migration_raw_draft',
  'migration_done_draft',
];

export function manifestBodyForChecksum(manifest: Omit<FixtureManifest, 'checksum'>): string {
  return JSON.stringify({
    schema_version: manifest.schema_version,
    mode: manifest.mode,
    commit: manifest.commit,
    wp_base_url: manifest.wp_base_url,
    created_at: manifest.created_at,
    fixtures: manifest.fixtures,
  });
}

export function computeManifestChecksum(manifest: Omit<FixtureManifest, 'checksum'>): string {
  return crypto.createHash('sha256').update(manifestBodyForChecksum(manifest)).digest('hex');
}

export function attachChecksum(manifest: Omit<FixtureManifest, 'checksum'>): FixtureManifest {
  return { ...manifest, checksum: computeManifestChecksum(manifest) };
}

export function writeManifest(manifest: FixtureManifest): void {
  fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
  fs.writeFileSync(MANIFEST_PATH, JSON.stringify(manifest, null, 2));
}

export function loadManifest(): FixtureManifest {
  if (!fs.existsSync(MANIFEST_PATH)) {
    throw new Error(`Missing fixture manifest: ${MANIFEST_PATH} — run with F9_FIXTURE_MODE=fresh`);
  }
  return JSON.parse(fs.readFileSync(MANIFEST_PATH, 'utf8')) as FixtureManifest;
}

export function validateManifestStructure(
  manifest: FixtureManifest,
  options: { expectedCommit?: string; expectedUrl?: string; mode?: FixtureMode } = {}
): void {
  if (manifest.schema_version !== MANIFEST_SCHEMA_VERSION) {
    throw new Error(`Manifest schema_version ${manifest.schema_version} != ${MANIFEST_SCHEMA_VERSION}`);
  }
  if (options.mode && manifest.mode !== options.mode) {
    throw new Error(`Manifest mode ${manifest.mode} != expected ${options.mode}`);
  }
  if (options.expectedUrl && manifest.wp_base_url !== options.expectedUrl) {
    throw new Error(`Manifest wp_base_url ${manifest.wp_base_url} != ${options.expectedUrl}`);
  }
  if (options.expectedCommit && manifest.commit !== options.expectedCommit) {
    throw new Error(
      `Manifest commit ${manifest.commit} != expected ${options.expectedCommit} (fresh acceptance requires exact commit)`
    );
  }
  const expectedChecksum = computeManifestChecksum(manifest);
  if (manifest.checksum !== expectedChecksum) {
    throw new Error('Manifest checksum mismatch — fixture manifest is stale or tampered');
  }
  for (const key of ALL_CORPUS_KEYS) {
    if (!manifest.fixtures[key]) {
      throw new Error(`Manifest missing fixture key: ${key}`);
    }
  }
}

export function hashText(value: string): string {
  return crypto.createHash('sha256').update(value).digest('hex');
}
