/**
 * Bootstrap a draft page through production SavePipeline (first canonical save).
 */
import { Page } from '@playwright/test';
import { WpCredentials } from './env';
import { createDraftPost, enableStrategyFlags, exportPost } from './wp-cli';
import { closeEditorSession, ensurePageAlive, login, openBlockEditor, savePost } from './editor';

export async function bootstrapProductionPost(
  page: Page,
  creds: WpCredentials,
  slug: string,
  title: string,
  fixtureFile: string
): Promise<number> {
  ensurePageAlive(page, 'bootstrapProductionPost');
  await closeEditorSession(page, creds);
  enableStrategyFlags(false);
  const postId = createDraftPost(slug, title, fixtureFile);
  await login(page, creds);
  await openBlockEditor(page, creds, postId);
  await savePost(page);
  const analysis = exportPost(postId, `${slug}-bootstrap`).analysis as { blocks?: Array<{ uuid?: string }> };
  if (!analysis.blocks?.length || !analysis.blocks[0]?.uuid) {
    throw new Error(`Production bootstrap failed to inject UUID for post ${postId}`);
  }
  return postId;
}

export type Analysis = {
  content_sha1: string;
  content_identical?: boolean;
  eligible_blocks: number;
  blocks: Array<{ uuid: string; valid_uuid: boolean; block_name: string; text_sha1: string }>;
  duplicate_uuids: Record<string, number>;
  has_aimlBlockId: boolean;
  uuid_preservation?: Record<string, string>;
};
