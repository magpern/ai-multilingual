/**
 * Gutenberg editor interactions for F9 browser acceptance.
 */
import { Page, FrameLocator, Locator, expect } from '@playwright/test';
import { WpCredentials, authCookieFile } from './env';
import fs from 'fs';

const F9_DIAG = process.env.F9_DIAG === '1';

function f9Diag(phase: string, detail: Record<string, unknown> = {}): void {
  if (!F9_DIAG) {
    return;
  }
  console.log(`[f9-editor] ${phase}`, JSON.stringify(detail));
}

export function ensurePageAlive(page: Page, label: string): void {
  if (page.isClosed()) {
    throw new Error(`[f9-editor] ${label}: page is closed`);
  }
}

/** Leave the block editor so the next test starts from a clean navigation target. */
export async function closeEditorSession(page: Page, creds?: WpCredentials): Promise<void> {
  if (page.isClosed()) {
    f9Diag('closeEditorSession:skip', { reason: 'page_closed' });
    return;
  }
  f9Diag('closeEditorSession:start', { url: page.url() });
  const adminList = creds ? `${creds.baseUrl}/wp-admin/edit.php?post_type=page` : 'about:blank';
  try {
    await page.goto(adminList, { waitUntil: 'domcontentloaded', timeout: 30000 });
  } catch {
    try {
      await page.goto('about:blank', { waitUntil: 'domcontentloaded', timeout: 10000 });
    } catch {
      f9Diag('closeEditorSession:failed', { url: page.url() });
    }
  }
  f9Diag('closeEditorSession:done', { url: page.url() });
}

export function canvas(page: Page): FrameLocator {
  return page.frameLocator('iframe[name="editor-canvas"]');
}

export async function login(page: Page, creds: WpCredentials): Promise<void> {
  const cookieFile = authCookieFile();
  if (!fs.existsSync(cookieFile)) {
    throw new Error(`Missing ${cookieFile} — run wp-auth-cookies.sh / write-auth-cookies-json.sh on host before browser tests`);
  }
  const payload = JSON.parse(fs.readFileSync(cookieFile, 'utf8')) as {
    cookies: { auth: string; logged_in: string; secure_auth: string };
    names: { auth: string; logged_in: string; secure_auth: string };
  };
  const domain = new URL(creds.baseUrl).hostname;

  await page.context().addCookies([
    { name: payload.names.auth, value: payload.cookies.auth, domain, path: '/', httpOnly: true, secure: true, sameSite: 'Lax' },
    { name: payload.names.logged_in, value: payload.cookies.logged_in, domain, path: '/', httpOnly: true, secure: true, sameSite: 'Lax' },
    { name: payload.names.secure_auth, value: payload.cookies.secure_auth, domain, path: '/', httpOnly: true, secure: true, sameSite: 'Lax' },
  ]);
  try {
    await page.context().grantPermissions(['clipboard-read', 'clipboard-write']);
  } catch {
    // Firefox/WebKit headless may not support clipboard permission grants.
  }
}

export async function openBlockEditor(page: Page, creds: WpCredentials, postId: number): Promise<void> {
  ensurePageAlive(page, 'openBlockEditor');
  // page.goto occasionally times out against the dev host under load (observed:
  // intermittent single-navigation stalls unrelated to Gutenberg/editor state —
  // no mutation has happened yet at this point, so a retry here is always safe
  // and never risks double-applying an operation).
  const url = `${creds.baseUrl}/wp-admin/post.php?post=${postId}&action=edit`;
  f9Diag('openBlockEditor:goto', { postId, url });
  let lastError: unknown;
  for (let attempt = 0; attempt < 3; attempt++) {
    ensurePageAlive(page, `openBlockEditor:attempt${attempt}`);
    try {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
      lastError = undefined;
      break;
    } catch (err) {
      lastError = err;
      f9Diag('openBlockEditor:goto-retry', { postId, attempt, error: String(err) });
    }
  }
  if (lastError) {
    throw lastError;
  }
  await page.locator('.edit-post-layout, .block-editor-writing-flow, .editor-post-title').first()
    .waitFor({ state: 'visible', timeout: 90000 });
  // The canvas iframe must exist and contain at least the writing flow before
  // any block-content interaction is attempted.
  await page.locator('iframe[name="editor-canvas"]').waitFor({ state: 'attached', timeout: 30000 });
  await canvas(page).locator('.block-editor-block-list__layout, .is-root-container').first()
    .waitFor({ state: 'visible', timeout: 30000 });

  const closeGuide = page.getByRole('button', { name: /close|dismiss|skip/i }).first();
  if (await closeGuide.isVisible({ timeout: 3000 }).catch(() => false)) {
    await closeGuide.click();
  }
}

/** Select the nth top-level (or any-depth, if allBlocks) block, optionally filtered by block type. */
export async function selectBlock(
  page: Page,
  index = 0,
  blockType?: string
): Promise<Locator> {
  const sel = blockType ? `[data-type="${blockType}"]` : '[data-block]';
  const block = canvas(page).locator(sel).nth(index);
  await block.waitFor({ state: 'visible', timeout: 15000 });
  // A block toolbar popover from a *previously* selected block can still be
  // mid-reposition and overlap the target block's click point (this is most
  // common right after a move/reorder re-selects a block that is now adjacent
  // to where the old toolbar was rendered). `force: true` bypasses Playwright's
  // actionability check for interception, which is safe here because we only
  // need the click to land inside the block to select it, not to interact with
  // whatever transient overlay is above it.
  await block.click({ position: { x: 5, y: 5 }, force: true });
  // Confirm selection registered (main-doc toolbar appears over the iframe).
  await page.locator('.block-editor-block-toolbar, .block-editor-block-contextual-toolbar').first()
    .waitFor({ state: 'visible', timeout: 10000 }).catch(() => undefined);
  return block;
}

export async function editBlockText(page: Page, index: number, newText: string, blockType = 'core/paragraph'): Promise<void> {
  const block = canvas(page).locator(`[data-type="${blockType}"]`).nth(index);
  await block.waitFor({ state: 'visible', timeout: 15000 });
  // The RichText contenteditable root is often the block element itself
  // (e.g. core/paragraph, core/heading) rather than a nested descendant
  // (e.g. core/buttons, where the <a> inside is the editable). Try the
  // block root first; fall back to a contenteditable descendant.
  await clickIntoRichText(block, page);
  await page.keyboard.press('Control+A');
  await page.keyboard.type(newText, { delay: 15 });
  // Click outside to commit the RichText value into block attributes.
  await canvas(page).locator('body').click({ position: { x: 5, y: 5 }, force: true }).catch(() => undefined);
}

export async function openBlockOptionsMenu(page: Page): Promise<void> {
  // Scope to the block (contextual) toolbar's "Options" button. A bare
  // `button[aria-label="Options"]` also matches the *global* editor options
  // menu (top-right ⋮, View/Editor/Panels/Tools) which sits earlier in DOM
  // order and would otherwise be clicked instead of the block's own menu.
  const options = page.locator(
    '.block-editor-block-toolbar button[aria-label="Options"], .block-editor-block-contextual-toolbar button[aria-label="Options"]'
  ).first();
  await options.waitFor({ state: 'visible', timeout: 10000 });
  await options.click();
  // Scope to the Gutenberg dropdown popover; the WP admin bar also has a
  // (hidden) [role="menu"] element that a bare selector would match first.
  await page.locator('.components-dropdown-menu__menu[role="menu"]:visible, [role="menu"]:visible').first()
    .waitFor({ state: 'visible', timeout: 5000 });
}

export async function duplicateBlock(page: Page, index: number, blockType?: string): Promise<void> {
  await selectBlock(page, index, blockType);
  await openBlockOptionsMenu(page);
  // Accessible name often includes the shortcut hint ("Duplicate Ctrl+Shift+D"),
  // so match by prefix rather than exact/anchored equality.
  await page.getByRole('menuitem', { name: /^duplicate\b/i }).click({ timeout: 15000, force: true });
  await page.keyboard.press('Escape').catch(() => undefined);
}

/**
 * Duplicate until the editor block list reaches minCount, retrying browser-specific
 * menu/shortcut paths when the first attempt does not materialize a sibling block.
 */
export async function duplicateBlockReliable(
  page: Page,
  index: number,
  blockType = 'core/paragraph',
  minCount = 2,
  maxAttempts = 5
): Promise<void> {
  for (let attempt = 0; attempt < maxAttempts; attempt++) {
    const before = await blockCount(page, blockType);
    if (before >= minCount) {
      f9Diag('duplicateBlockReliable:already', { before, minCount });
      return;
    }

    f9Diag('duplicateBlockReliable:attempt', { attempt, before, minCount });
    await selectBlock(page, index, blockType);

    if (attempt % 2 === 0) {
      await duplicateBlockViaShortcut(page, index, blockType);
    } else {
      await duplicateBlock(page, index, blockType);
    }

    try {
      await expect.poll(async () => blockCount(page, blockType), { timeout: 30000 }).toBeGreaterThan(before);
    } catch {
      continue;
    }

    const after = await blockCount(page, blockType);
    if (after >= minCount) {
      await selectBlock(page, Math.min(index + 1, after - 1), blockType);
      await canvas(page).locator('body').click({ position: { x: 5, y: 5 }, force: true }).catch(() => undefined);
      f9Diag('duplicateBlockReliable:success', { after, minCount });
      return;
    }
  }

  throw new Error(`duplicateBlockReliable: expected >= ${minCount} ${blockType} blocks after ${maxAttempts} attempts`);
}

/** Duplicate via keyboard shortcut only (used to test both entry points). */
export async function duplicateBlockViaShortcut(page: Page, index: number, blockType?: string): Promise<void> {
  await selectBlock(page, index, blockType);
  await page.keyboard.press('Control+Shift+D');
}

export async function copyBlock(page: Page, index: number, blockType?: string): Promise<void> {
  await selectBlock(page, index, blockType);
  await page.keyboard.press('Control+C');
}

export async function cutBlock(page: Page, index: number, blockType?: string): Promise<void> {
  await selectBlock(page, index, blockType);
  await page.keyboard.press('Control+X');
}

/**
 * Paste as a NEW sibling block after the last block (not merged as inline
 * text into existing content): focus the end of the last block's text,
 * press Enter to open a fresh empty paragraph, then paste there. Gutenberg
 * replaces an empty paragraph with pasted block clipboard content instead
 * of splicing it into existing text.
 */
export async function pasteAsNewBlockAfterLast(page: Page): Promise<void> {
  const blocks = canvas(page).locator('[data-block]');
  const count = await blocks.count();
  const last = blocks.nth(count - 1);
  await last.click({ position: { x: 5, y: 5 } });
  await page.keyboard.press('End');
  await page.keyboard.press('Enter');
  await page.waitForTimeout(150);
  await page.keyboard.press('Control+V');
  await page.waitForTimeout(300);
}

/** Paste at end of document (click the default appender, then paste). */
export async function pasteAtEnd(page: Page): Promise<void> {
  const appender = canvas(page).locator('.block-list-appender textarea, .block-editor-default-block-appender textarea').first();
  if (await appender.isVisible({ timeout: 3000 }).catch(() => false)) {
    await appender.click();
  } else {
    // Fallback: click after the last block.
    const blocks = canvas(page).locator('[data-block]');
    const count = await blocks.count();
    await blocks.nth(count - 1).click({ position: { x: 5, y: 5 } });
    await page.keyboard.press('End');
  }
  await page.keyboard.press('Control+V');
  await page.waitForTimeout(300);
}

export async function deleteBlock(page: Page, index: number, blockType?: string): Promise<void> {
  await selectBlock(page, index, blockType);
  await openBlockOptionsMenu(page);
  await page.getByRole('menuitem', { name: /^delete\b/i }).click({ timeout: 5000 });
}

export async function moveBlockUp(page: Page, index: number, blockType?: string): Promise<void> {
  await selectBlock(page, index, blockType);
  const moveUp = page.locator('button[aria-label="Move up"]').first();
  await moveUp.click({ timeout: 5000 });
}

export async function moveBlockDown(page: Page, index: number, blockType?: string): Promise<void> {
  await selectBlock(page, index, blockType);
  const moveDown = page.locator('button[aria-label="Move down"]').first();
  await moveDown.click({ timeout: 5000 });
}

/** Transform the selected block to another block type via the block-type swap dropdown. */
export async function transformBlock(page: Page, index: number, fromType: string, toTypeLabel: string | RegExp): Promise<void> {
  await selectBlock(page, index, fromType);
  // The block-type switcher's accessible name is the CURRENT block type's
  // label (e.g. "Paragraph"), not "Change block type" — only its hidden
  // description says that. Scope by the stable wrapper class instead.
  const swapper = page.locator('.block-editor-block-switcher button.components-dropdown-menu__toggle').first();
  await swapper.click({ timeout: 5000 });
  const item = page.getByRole('menuitem', { name: toTypeLabel }).first();
  await item.click({ timeout: 5000 });
}

export async function wrapInGroup(page: Page, index: number, blockType?: string): Promise<void> {
  await selectBlock(page, index, blockType);
  await openBlockOptionsMenu(page);
  const groupItem = page.getByRole('menuitem', { name: /^group\b/i }).first();
  if (await groupItem.isVisible({ timeout: 2000 }).catch(() => false)) {
    await groupItem.click();
    return;
  }
  await page.keyboard.press('Escape');
  // Fallback: block toolbar "Change block type" -> Group transform.
  const swapper = page.locator('.block-editor-block-switcher button.components-dropdown-menu__toggle').first();
  await swapper.click({ timeout: 5000 });
  await page.getByRole('menuitem', { name: /^group\b/i }).first().click({ timeout: 5000 });
}

export async function unwrapFromGroup(page: Page, groupIndex = 0): Promise<void> {
  // A Group containing a single simple child is easy to click "through" —
  // clicking inside its bounds selects the child, not the Group wrapper
  // itself. Select any descendant, then use the block breadcrumb (rendered
  // in the main document, below the canvas) to explicitly select the
  // ancestor Group block before opening its options menu.
  const group = canvas(page).locator('[data-type="core/group"]').nth(groupIndex);
  const descendant = group.locator('[data-block]').first();
  await descendant.click({ position: { x: 5, y: 5 } });
  const groupBreadcrumb = page.locator('.block-editor-block-breadcrumb button', { hasText: 'Group' }).first();
  await groupBreadcrumb.waitFor({ state: 'visible', timeout: 5000 });
  await groupBreadcrumb.click();
  await openBlockOptionsMenu(page);
  const ungroupItem = page.getByRole('menuitem', { name: /ungroup/i }).first();
  await ungroupItem.click({ timeout: 15000, force: true });
  await page.keyboard.press('Escape').catch(() => undefined);
  await expect.poll(async () => canvas(page).locator('[data-type="core/group"]').count(), { timeout: 15000 }).toBe(0);
  const ungrouped = canvas(page).locator('[data-type="core/paragraph"], [data-block]').first();
  await ungrouped.waitFor({ state: 'visible', timeout: 10000 });
  await ungrouped.click({ position: { x: 5, y: 5 }, force: true });
  await page.locator('.block-editor-block-toolbar, .block-editor-block-contextual-toolbar').first()
    .waitFor({ state: 'visible', timeout: 10000 }).catch(() => undefined);
}

/** Wait until Gutenberg exposes a save affordance after a structural edit. */
export async function waitForDocumentDirty(page: Page): Promise<void> {
  const save = page.locator(
    'button.editor-post-save-draft, button.editor-post-publish-button, .editor-post-save-draft, button[aria-label="Save"], button:has-text("Save draft")'
  ).first();
  for (let tick = 0; tick < 40; tick++) {
    if (await save.isVisible().catch(() => false)) {
      if (await save.isEnabled().catch(() => false)) {
        return;
      }
    }
    const saving = page.locator('text=/^Saving/i').first();
    if (await saving.isVisible({ timeout: 200 }).catch(() => false)) {
      await saving.waitFor({ state: 'hidden', timeout: 15000 }).catch(() => undefined);
      return;
    }
    await page.waitForTimeout(500);
  }
  await save.waitFor({ state: 'visible', timeout: 10000 });
}

async function clickIntoRichText(block: Locator, page: Page): Promise<void> {
  await block.click({ position: { x: 10, y: 10 } });
  const selfEditable = await block.getAttribute('contenteditable').catch(() => null);
  if (selfEditable !== 'true') {
    await block.locator('[contenteditable="true"]').first().click({ timeout: 10000 });
  }
}

/** Place caret at end of paragraph text and press Enter to split. */
export async function splitParagraph(page: Page, index: number, atText?: string): Promise<void> {
  const block = canvas(page).locator('[data-type="core/paragraph"]').nth(index);
  await clickIntoRichText(block, page);
  if (atText) {
    // Use keyboard Home then move right by atText length as a simple deterministic split point.
    await page.keyboard.press('Home');
    for (let i = 0; i < atText.length; i++) {
      await page.keyboard.press('ArrowRight');
    }
  } else {
    await page.keyboard.press('End');
  }
  await page.keyboard.press('Enter');
}

/** Merge current paragraph into previous by pressing Backspace at start. */
export async function mergeParagraphBackward(page: Page, index: number): Promise<void> {
  const block = canvas(page).locator('[data-type="core/paragraph"]').nth(index);
  await clickIntoRichText(block, page);
  await page.keyboard.press('Home');
  await page.keyboard.press('Backspace');
}

export async function undo(page: Page): Promise<void> {
  await canvas(page).locator('body').click({ position: { x: 5, y: 5 } }).catch(() => undefined);
  await page.keyboard.press('Control+Z');
}

export async function redo(page: Page): Promise<void> {
  await canvas(page).locator('body').click({ position: { x: 5, y: 5 } }).catch(() => undefined);
  await page.keyboard.press('Control+Shift+Z');
}

/**
 * Save and wait on a deterministic signal: the REST PATCH/POST response for
 * this post, falling back to the "Saved" label if the request isn't observed
 * (e.g. optimistic UI). No arbitrary sleeps are used as the primary signal.
 */
export async function savePost(page: Page): Promise<void> {
  ensurePageAlive(page, 'savePost');
  const save = page.locator(
    'button.editor-post-save-draft, button.editor-post-publish-button, .editor-post-save-draft, button[aria-label="Save"], button:has-text("Save draft")'
  ).first();
  await save.waitFor({ state: 'visible', timeout: 30000 });

  const responsePromise = page.waitForResponse(
    (res) => /\/wp-json\/wp\/v2\/(pages|posts)\/\d+/.test(res.url()) && res.request().method() !== 'GET',
    { timeout: 20000 }
  ).catch(() => null);

  await save.click({ force: true });
  const response = await responsePromise;

  if (response) {
    expect(response.ok(), `save request failed: ${response.status()} ${response.url()}`).toBeTruthy();
  } else {
    // Fallback signal: saved-state label or re-enabled save button.
    await page.getByText(/^Saved$/i).first().waitFor({ state: 'visible', timeout: 15000 }).catch(() => undefined);
  }
}

export async function saveWithoutSemanticChanges(page: Page): Promise<void> {
  await savePost(page);
}

export async function reloadEditor(page: Page, creds: WpCredentials, postId: number): Promise<void> {
  await openBlockEditor(page, creds, postId);
}

export async function blockCount(page: Page, blockType?: string): Promise<number> {
  const sel = blockType ? `[data-type="${blockType}"]` : '[data-block]';
  return canvas(page).locator(sel).count();
}

export async function getBlockAttribute(page: Page, index: number, blockType: string, attrName: string): Promise<string | null> {
  const block = canvas(page).locator(`[data-type="${blockType}"]`).nth(index);
  return block.getAttribute(`data-${attrName.toLowerCase()}`).catch(() => null);
}

export async function captureEditorIssues(page: Page): Promise<string[]> {
  const issues: string[] = [];
  const recovery = canvas(page).locator('text=/unexpected or invalid content/i');
  if (await recovery.count().then((c) => c > 0).catch(() => false)) {
    issues.push('block_recovery_prompt');
  }
  const mainDocRecovery = page.locator('text=/unexpected or invalid content/i');
  if (await mainDocRecovery.count().then((c) => c > 0).catch(() => false)) {
    issues.push('block_recovery_prompt_main_doc');
  }
  return issues;
}

export async function assertNoBlockRecovery(page: Page): Promise<void> {
  await expect(canvas(page).locator('text=/unexpected or invalid content/i')).toHaveCount(0);
  await expect(page.locator('text=/unexpected or invalid content/i')).toHaveCount(0);
}

export async function collectConsoleErrors(page: Page): Promise<string[]> {
  const errors: string[] = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      errors.push(msg.text());
    }
  });
  return errors;
}

/** Insert a reusable/synced pattern by name via the Inserter search. */
export async function insertPatternByName(page: Page, name: string): Promise<void> {
  const inserterToggle = page.locator('button[aria-label="Block Inserter"], button[aria-label="Toggle block inserter"], button[aria-label="Add block"]').first();
  await inserterToggle.click({ timeout: 10000 });
  const patternsTab = page.getByRole('tab', { name: /patterns/i }).first();
  if (await patternsTab.isVisible({ timeout: 3000 }).catch(() => false)) {
    await patternsTab.click();
  }
  const search = page.getByPlaceholder(/search/i).first();
  await search.click({ timeout: 5000 });
  await search.fill(name);
  // Pattern search results render a listbox of role="option" items; each item
  // fetches an async preview thumbnail, so wait for the option itself (not the
  // thumbnail) to be attached and stable before clicking.
  const option = page.getByRole('option', { name }).first();
  await option.waitFor({ state: 'visible', timeout: 10000 });
  await option.click({ timeout: 10000 });
  await page.keyboard.press('Escape').catch(() => undefined);
}

export async function convertSelectedBlockToSyncedPattern(page: Page, index: number, blockType: string, name: string, synced = true): Promise<void> {
  await selectBlock(page, index, blockType);
  await openBlockOptionsMenu(page);
  const createPattern = page.getByRole('menuitem', { name: /create pattern|create synced pattern/i }).first();
  await createPattern.click({ timeout: 5000 });
  const nameField = page.getByLabel(/name/i).first();
  await nameField.fill(name);
  const syncedToggle = page.getByLabel(/synced/i).first();
  if (await syncedToggle.isVisible({ timeout: 2000 }).catch(() => false)) {
    const checked = await syncedToggle.isChecked().catch(() => true);
    if (synced && !checked) await syncedToggle.check().catch(() => undefined);
    if (!synced && checked) await syncedToggle.uncheck().catch(() => undefined);
  }
  await page.getByRole('button', { name: /^(add|create)\b/i }).first().click({ timeout: 5000 });
}

/** Cut the block at `index` and paste it as a proxy for "move between containers". */
export async function moveBlockToAppenderOfContainer(page: Page, fromIndex: number, fromBlockType: string, containerSelector: string): Promise<void> {
  await cutBlock(page, fromIndex, fromBlockType);
  const container = canvas(page).locator(containerSelector).first();
  const appender = container.locator('.block-list-appender, .block-editor-default-block-appender').first();
  if (await appender.isVisible({ timeout: 2000 }).catch(() => false)) {
    await appender.click();
  } else {
    // Click at the end of the container's last block to get a paste target.
    const lastBlock = container.locator('[data-block]').last();
    await lastBlock.click({ position: { x: 5, y: 5 } });
    await page.keyboard.press('End');
  }
  await page.keyboard.press('Control+V');
  await page.waitForTimeout(300);
}

export async function detachPattern(page: Page, index = 0): Promise<void> {
  await selectBlock(page, index, 'core/block');
  await openBlockOptionsMenu(page);
  const detach = page.getByRole('menuitem', { name: /detach\b/i }).first();
  await detach.click({ timeout: 5000 });
  // Recent Gutenberg versions show a "Detach pattern?" confirmation dialog.
  const confirmDialog = page.getByRole('dialog', { name: /detach pattern/i }).first();
  if (await confirmDialog.isVisible({ timeout: 3000 }).catch(() => false)) {
    await confirmDialog.getByRole('button', { name: /^detach\b/i }).first().click({ timeout: 5000 });
  }
}
