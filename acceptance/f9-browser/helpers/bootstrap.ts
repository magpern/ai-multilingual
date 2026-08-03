/**
 * Bootstrap helpers — browser first-save and legacy production bootstrap.
 */
import { Page, TestInfo } from '@playwright/test';
import { WpCredentials } from './env';
import { sanitizeTestId } from './fixture-corpus';
import { createDraftPost, enableStrategyFlags, exportPost, F9_CLONE_PREFIX, trackSessionClone } from './wp-cli';
import { closeEditorSession, login, openBlockEditor, savePost } from './editor';
import { resolveF9Page } from './lifecycle';

export async function bootstrapProductionPost(
  page: Page,
  creds: WpCredentials,
  slug: string,
  title: string,
  fixtureFile: string
): Promise<number> {
  page = await resolveF9Page(page);
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
  trackSessionClone(postId);
  return postId;
}

/** Browser-driven first save — only for the create matrix cell. */
export async function createBrowserFirstSaveFixture(
  page: Page,
  creds: WpCredentials,
  testInfo: Pick<TestInfo, 'testId' | 'title'>,
  fixtureFile: string
): Promise<number> {
  const slug = `${F9_CLONE_PREFIX}create-${sanitizeTestId(testInfo.testId)}-${Date.now()}`;
  return bootstrapProductionPost(page, creds, slug, `F9 Create ${testInfo.title}`, fixtureFile);
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

export { cloneFixtureForTest, deleteTrackedClone, getSharedFixture } from './fixture-corpus';
export type { CorpusKey } from './fixture-manifest';
