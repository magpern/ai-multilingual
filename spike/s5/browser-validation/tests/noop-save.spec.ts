/**
 * No-op save stability analysis (3 cycles). THROWAWAY — spike/s5 only.
 */
import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { loadCredentials, CORPUS_DIR } from '../helpers/env';
import { createTaggedPost, exportPost, deletePost, countRevisions } from '../helpers/wp-cli';
import { login, openBlockEditor, savePost } from '../helpers/editor';

test('three no-op save cycles measure revision and byte stability', async ({ page }) => {
  const creds = loadCredentials();
  const slug = 'bv-noop-save';
  const postId = createTaggedPost(slug, 'S5 BV — No-op save cycles', 'operations-multi-paragraph.html');
  const snapshots: Array<Record<string, unknown>> = [];
  const revisionsBefore = countRevisions(postId);

  await login(page, creds);

  let prevSha1: string | null = null;
  for (let cycle = 1; cycle <= 3; cycle++) {
    await openBlockEditor(page, creds, postId);
    await savePost(page);
    const snap = exportPost(postId, `${slug}-cycle-${cycle}`, cycle === 1 ? postId : postId);
    snapshots.push({
      cycle,
      content_sha1: snap.analysis.content_sha1,
      content_bytes: snap.analysis.content_bytes,
      content_identical_to_first: cycle > 1 ? snap.analysis.content_identical : true,
      uuid_preservation: snap.analysis.uuid_preservation,
      duplicate_uuids: snap.analysis.duplicate_uuids,
    });
    if (prevSha1 && snap.analysis.content_sha1 !== prevSha1) {
      // byte drift detected
    }
    prevSha1 = snap.analysis.content_sha1 as string;
  }

  const revisionsAfter = countRevisions(postId);
  const result = {
    post_id: postId,
    cycles: snapshots,
    revisions_before: revisionsBefore,
    revisions_after: revisionsAfter,
    revisions_created: revisionsAfter - revisionsBefore,
    stable_after_first: snapshots.slice(1).every((s) => s.content_identical_to_first === true),
    all_uuids_preserved: snapshots.every((s) => {
      const p = s.uuid_preservation as Record<string, string> | undefined;
      return !p || Object.values(p).every((v) => v === 'preserved');
    }),
  };

  fs.mkdirSync(CORPUS_DIR, { recursive: true });
  fs.writeFileSync(path.join(CORPUS_DIR, 'noop-save-results.json'), JSON.stringify(result, null, 2));

  expect(result.all_uuids_preserved).toBe(true);
  deletePost(postId);
});
