/**
 * Browser-only tests driven by host-prepared manifest (WP-CLI runs on host,
 * never inside this container — see setup-browser-validation-manifest.sh).
 * THROWAWAY — spike/s5 only.
 */
import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { CORPUS_DIR } from '../helpers/env';
import {
  login,
  openBlockEditor,
  savePost,
  editBlockText,
  duplicateBlock,
  copyBlock,
  pasteAtEnd,
  moveBlockDown,
  wrapInGroup,
  unwrapFromGroup,
  moveBlockToAppenderOfContainer,
  transformBlock,
  splitParagraph,
  mergeParagraphBackward,
  undo,
  redo,
  deleteBlock,
  assertNoBlockRecovery,
} from '../helpers/editor';

type ManifestItem = {
  slug: string;
  post_id: number;
  operation: string;
  baseline_container_path: string;
};

const manifestPath = path.join(CORPUS_DIR, process.env.BV_MANIFEST_FILE ?? 'manifest.json');
const manifest: ManifestItem[] = fs.existsSync(manifestPath)
  ? (JSON.parse(fs.readFileSync(manifestPath, 'utf8')) as ManifestItem[])
  : [];

test.describe('Strategy F browser validation (manifest-driven)', () => {
  test.skip(manifest.length === 0, 'Run setup-browser-validation-manifest.sh first');
  const creds = { baseUrl: process.env.WP_BASE_URL ?? 'https://dev.biopentra.eu', user: '', password: '' };

  for (const item of manifest) {
    test(`${item.slug}: ${item.operation}`, async ({ page }) => {
      await login(page, creds);
      await openBlockEditor(page, creds, item.post_id);

      switch (item.operation) {
        case 'noop_save':
          break;
        case 'noop_save_x3':
          await savePost(page);
          await openBlockEditor(page, creds, item.post_id);
          await savePost(page);
          await openBlockEditor(page, creds, item.post_id);
          break;
        case 'text_edit':
          await editBlockText(page, 0, 'Browser-edited text.');
          break;
        case 'text_edit_heading':
          await editBlockText(page, 0, 'Browser-edited heading.', 'core/heading');
          break;
        case 'full_rewrite':
          await editBlockText(page, 0, 'Completely rewritten paragraph content that differs entirely from the original fixture text.');
          break;
        case 'duplicate_block':
        case 'duplicate_then_edit_copy':
          await duplicateBlock(page, 0, 'core/paragraph');
          if (item.operation === 'duplicate_then_edit_copy') {
            await editBlockText(page, 1, 'Edited duplicate copy text.');
          }
          break;
        case 'duplicate_button':
          await duplicateBlock(page, 0, 'core/button');
          break;
        case 'duplicate_nested_subtree':
          await duplicateBlock(page, 0, 'core/group');
          break;
        case 'copy_paste_same_post':
          await copyBlock(page, 0, 'core/paragraph');
          await pasteAtEnd(page);
          break;
        case 'reorder_adjacent':
          await moveBlockDown(page, 0, 'core/paragraph');
          break;
        case 'reorder_nonadjacent':
          await moveBlockDown(page, 0, 'core/paragraph');
          await moveBlockDown(page, 0, 'core/paragraph');
          break;
        case 'wrap_in_group':
          await wrapInGroup(page, 0, 'core/paragraph');
          break;
        case 'unwrap_from_group':
          await unwrapFromGroup(page, 0);
          break;
        case 'move_between_columns':
          await moveBlockToAppenderOfContainer(page, 0, 'core/paragraph', '.wp-block-column:nth-child(2), [data-type="core/column"]:nth-child(2)');
          break;
        case 'transform_p2h':
          await transformBlock(page, 0, 'core/paragraph', /heading/i);
          break;
        case 'transform_p2q':
          await transformBlock(page, 0, 'core/paragraph', /quote/i);
          break;
        case 'transform_h2p':
          await transformBlock(page, 0, 'core/heading', /paragraph/i);
          break;
        case 'split_paragraph':
          await splitParagraph(page, 0);
          break;
        case 'merge_paragraphs':
          await mergeParagraphBackward(page, 1);
          break;
        case 'undo_redo_text_edit':
          await editBlockText(page, 0, 'Text edited before undo/redo cycle.');
          await undo(page);
          await redo(page);
          break;
        case 'delete_then_undo':
          await deleteBlock(page, 0, 'core/paragraph');
          await undo(page);
          break;
        case 'duplicate_thirdparty_toc':
          await duplicateBlock(page, 0, 'rank-math/toc-block');
          break;
        case 'duplicate_thirdparty_woo':
          await duplicateBlock(page, 0, 'woocommerce/customer-account');
          break;
        default:
          throw new Error(`Unknown operation in manifest: ${item.operation}`);
      }

      await savePost(page);
      await assertNoBlockRecovery(page);
      expect(item.post_id).toBeGreaterThan(0);
    });
  }
});
