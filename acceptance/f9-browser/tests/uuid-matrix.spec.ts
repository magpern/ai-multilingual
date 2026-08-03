/**
 * F9 UUID persistence matrix — Chromium desktop full F9-R coverage.
 */
import { test, expect } from '../fixtures/f9-test';
import fs from 'fs';
import path from 'path';
import { ARTIFACTS_DIR, loadCredentials } from '../helpers/env';
import {
  Analysis,
  cloneFixtureForTest,
  createBrowserFirstSaveFixture,
  deleteTrackedClone,
  getSharedFixture,
} from '../helpers/bootstrap';
import { exportPost, runReplayGate, snapshotBaseline } from '../helpers/wp-cli';
import {
  assertNoBlockRecovery,
  closeEditorSession,
  copyBlock,
  deleteBlock,
  duplicateBlock,
  editBlockText,
  mergeParagraphBackward,
  moveBlockDown,
  openBlockEditor,
  pasteAsNewBlockAfterLast,
  redo,
  savePost,
  selectBlock,
  splitParagraph,
  transformBlock,
  undo,
  waitForDocumentDirty,
  wrapInGroup,
  unwrapFromGroup,
} from '../helpers/editor';
import { endTestLifecycle } from '../helpers/lifecycle';

const creds = { ...loadCredentials(), password: '' };
const matrixResults: Record<string, unknown> = {};

test.describe('F9 UUID matrix (production plugin)', () => {
  test.beforeAll(() => {
    fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
  });

  test.afterAll(() => {
    fs.writeFileSync(
      path.join(ARTIFACTS_DIR, 'uuid-matrix-results.json'),
      JSON.stringify(matrixResults, null, 2)
    );
  });

  test.afterEach(async () => {
    await endTestLifecycle();
  });

  test('create: first save injects valid UUID', async ({ page }, testInfo) => {
    const postId = await createBrowserFirstSaveFixture(page, creds, testInfo, 'core-paragraph.html');
    try {
      const after = exportPost(postId, `f9-clone-create-${testInfo.testId.slice(0, 8)}`).analysis as unknown as Analysis;
      matrixResults.create = { post_id: postId, blocks: after.blocks.length, valid: after.blocks[0]?.valid_uuid };
      expect(after.blocks[0]?.valid_uuid).toBe(true);
      expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);
      const replay = runReplayGate(postId);
      expect(replay.rendered_false_positive).toBe(0);
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('edit: text change preserves UUID', async ({ page }, testInfo) => {
    const postId = cloneFixtureForTest('paragraph_uuid_draft', testInfo);
    try {
      const baseline = snapshotBaseline(postId, `f9-clone-edit-${testInfo.testId.slice(0, 8)}`);
      await openBlockEditor(page, creds, postId);
      await editBlockText(page, 0, 'Edited paragraph for F9.');
      await savePost(page);
      await assertNoBlockRecovery(page);
      const after = exportPost(postId, `f9-clone-edit-${testInfo.testId.slice(0, 8)}`, baseline.containerPath).analysis as unknown as Analysis;
      matrixResults.edit = { uuid_preserved: after.blocks[0]?.uuid, duplicate_uuids: after.duplicate_uuids };
      expect(after.blocks[0]?.valid_uuid).toBe(true);
      expect(after.uuid_preservation?.[0]).toBe('preserved');
      expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('duplicate: repair leaves no duplicate UUIDs', async ({ page }, testInfo) => {
    const postId = cloneFixtureForTest('duplicate_single_uuid_draft', testInfo);
    try {
      const baseline = snapshotBaseline(postId, `f9-clone-dup-${testInfo.testId.slice(0, 8)}`);
      await openBlockEditor(page, creds, postId);
      await duplicateBlock(page, 0, 'core/paragraph');
      await savePost(page);
      const after = exportPost(postId, `f9-clone-dup-${testInfo.testId.slice(0, 8)}`, baseline.containerPath).analysis as unknown as Analysis;
      matrixResults.duplicate = { eligible: after.eligible_blocks, duplicate_uuids: after.duplicate_uuids };
      expect(after.eligible_blocks).toBeGreaterThan(1);
      expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('copy-paste: pasted block gets distinct UUID after save', async ({ page }, testInfo) => {
    const postId = cloneFixtureForTest('paragraph_uuid_draft', testInfo);
    try {
      const baseline = snapshotBaseline(postId, `f9-clone-cp-${testInfo.testId.slice(0, 8)}`);
      await openBlockEditor(page, creds, postId);
      await copyBlock(page, 0, 'core/paragraph');
      await pasteAsNewBlockAfterLast(page);
      await savePost(page);
      const after = exportPost(postId, `f9-clone-cp-${testInfo.testId.slice(0, 8)}`, baseline.containerPath).analysis as unknown as Analysis;
      matrixResults.copy_paste = { blocks: after.blocks.length, duplicate_uuids: after.duplicate_uuids };
      expect(after.blocks.length).toBeGreaterThan(1);
      expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('undo-redo: UUID tracks edit stack', async ({ page }, testInfo) => {
    await closeEditorSession(page, creds);
    const postId = cloneFixtureForTest('paragraph_uuid_draft', testInfo);
    try {
      const before = exportPost(postId, `f9-clone-ur-${testInfo.testId.slice(0, 8)}-t0`).analysis as unknown as Analysis;
      await openBlockEditor(page, creds, postId);
      await editBlockText(page, 0, 'Temporary edit.');
      await undo(page);
      const afterUndo = exportPost(postId, `f9-clone-ur-${testInfo.testId.slice(0, 8)}`).analysis as unknown as Analysis;
      expect(afterUndo.blocks[0]?.uuid).toBe(before.blocks[0]?.uuid);
      await editBlockText(page, 0, 'Redo path edit.');
      await redo(page).catch(() => undefined);
      await savePost(page);
      matrixResults.undo_redo = { uuid_after_undo: afterUndo.blocks[0]?.uuid };
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('transform: paragraph to heading preserves UUID when allowlisted', async ({ page }, testInfo) => {
    const postId = cloneFixtureForTest('paragraph_uuid_draft', testInfo);
    try {
      const baseline = snapshotBaseline(postId, `f9-clone-xform-${testInfo.testId.slice(0, 8)}`);
      await openBlockEditor(page, creds, postId);
      await transformBlock(page, 0, 'core/paragraph', /^heading\b/i);
      await savePost(page);
      const after = exportPost(postId, `f9-clone-xform-${testInfo.testId.slice(0, 8)}`, baseline.containerPath).analysis as unknown as Analysis;
      matrixResults.transform = { block_name: after.blocks[0]?.block_name, uuid: after.blocks[0]?.uuid };
      expect(after.blocks[0]?.block_name).toBe('core/heading');
      expect(after.blocks[0]?.valid_uuid).toBe(true);
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('move: reorder preserves UUID', async ({ page }, testInfo) => {
    const postId = cloneFixtureForTest('multi_paragraph_uuid_draft', testInfo);
    try {
      const baseline = snapshotBaseline(postId, `f9-clone-move-${testInfo.testId.slice(0, 8)}`);
      const firstUuid = (exportPost(postId, `f9-clone-move-${testInfo.testId.slice(0, 8)}-t0`).analysis as unknown as Analysis).blocks[0]?.uuid;
      await openBlockEditor(page, creds, postId);
      await moveBlockDown(page, 0, 'core/paragraph');
      await savePost(page);
      const after = exportPost(postId, `f9-clone-move-${testInfo.testId.slice(0, 8)}`, baseline.containerPath).analysis as unknown as Analysis;
      matrixResults.move = { first_uuid: firstUuid, after_first_uuid: after.blocks[0]?.uuid };
      expect(after.blocks.some((b) => b.uuid === firstUuid)).toBe(true);
      expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('group-ungroup: inner UUID preserved', async ({ page }, testInfo) => {
    const postId = cloneFixtureForTest('paragraph_uuid_draft', testInfo);
    try {
      const uuid = (exportPost(postId, `f9-clone-grp-${testInfo.testId.slice(0, 8)}-t0`).analysis as unknown as Analysis).blocks[0]?.uuid;
      await openBlockEditor(page, creds, postId);
      await wrapInGroup(page, 0, 'core/paragraph');
      await savePost(page);
      await unwrapFromGroup(page, 0);
      await selectBlock(page, 0, 'core/paragraph');
      await waitForDocumentDirty(page);
      await savePost(page);
      const after = exportPost(postId, `f9-clone-grp-${testInfo.testId.slice(0, 8)}`).analysis as unknown as Analysis;
      matrixResults.group = { uuid, after_uuid: after.blocks[0]?.uuid };
      expect(after.blocks[0]?.uuid).toBe(uuid);
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('split-merge: operations preserve or repair UUIDs', async ({ page }, testInfo) => {
    const postId = cloneFixtureForTest('paragraph_uuid_draft', testInfo);
    try {
      await openBlockEditor(page, creds, postId);
      await splitParagraph(page, 0);
      await savePost(page);
      let after = exportPost(postId, `f9-clone-split-${testInfo.testId.slice(0, 8)}`).analysis as unknown as Analysis;
      expect(after.blocks.length).toBeGreaterThan(1);
      expect(Object.keys(after.duplicate_uuids)).toHaveLength(0);
      await openBlockEditor(page, creds, postId);
      await mergeParagraphBackward(page, 1);
      await savePost(page);
      after = exportPost(postId, `f9-clone-split-${testInfo.testId.slice(0, 8)}-merge`).analysis as unknown as Analysis;
      matrixResults.split_merge = { blocks: after.blocks.length };
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('delete-undo: UUID preserved', async ({ page }, testInfo) => {
    const postId = cloneFixtureForTest('paragraph_uuid_draft', testInfo);
    try {
      const uuid = (exportPost(postId, `f9-clone-del-${testInfo.testId.slice(0, 8)}-t0`).analysis as unknown as Analysis).blocks[0]?.uuid;
      await openBlockEditor(page, creds, postId);
      await deleteBlock(page, 0, 'core/paragraph');
      await undo(page);
      await savePost(page);
      const after = exportPost(postId, `f9-clone-del-${testInfo.testId.slice(0, 8)}`).analysis as unknown as Analysis;
      matrixResults.delete_undo = { uuid, after_uuid: after.blocks[0]?.uuid };
      expect(after.blocks[0]?.uuid).toBe(uuid);
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('noop-save: three cycles byte-stable UUIDs', async ({ page }, testInfo) => {
    const postId = cloneFixtureForTest('paragraph_uuid_draft', testInfo);
    try {
      const firstSha = (exportPost(postId, `f9-clone-noop-${testInfo.testId.slice(0, 8)}-c1`).analysis as unknown as Analysis).content_sha1;
      for (let i = 0; i < 3; i++) {
        await openBlockEditor(page, creds, postId);
        await savePost(page);
      }
      const after = exportPost(postId, `f9-clone-noop-${testInfo.testId.slice(0, 8)}`).analysis as unknown as Analysis;
      matrixResults.noop_save = { stable: after.content_sha1 === firstSha };
      expect(after.content_sha1).toBe(firstSha);
    } finally {
      deleteTrackedClone(postId);
    }
  });

  test('heading and button allowlist blocks inject UUID', async () => {
    const heading = getSharedFixture('heading_uuid_draft');
    const button = getSharedFixture('button_uuid_draft');
    const h = exportPost(heading.post_id, 'f9-corpus-heading-check').analysis as unknown as Analysis;
    const b = exportPost(button.post_id, 'f9-corpus-button-check').analysis as unknown as Analysis;
    matrixResults.allowlist_blocks = { heading: h.blocks[0]?.block_name, button: b.blocks[0]?.block_name };
    expect(h.blocks[0]?.valid_uuid).toBe(true);
    expect(b.blocks[0]?.valid_uuid).toBe(true);
  });
});
