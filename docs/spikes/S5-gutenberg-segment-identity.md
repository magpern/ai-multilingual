# S5 — Gutenberg segment identity (Strategies A–G)

Status: **Spike Phase 3 complete.** Strategy F passes PHP exit gate; mandatory
browser validation gates closed; Strategy G costed. Strategy F is
**conditionally accepted for production planning**, still **not
production-approved** — ADR-0013 remains Proposed. See §21.

Machine: dev VPS `dev.biopentra.eu`, WP **7.0.2**, PHP **8.4**, MariaDB **11.8.8**,
Playwright **1.51.0** (Docker `mcr.microsoft.com/playwright:v1.51.0-jammy`).

Evidence JSON: `spike/s5/corpus/strategy-{a..f}-*.json`,
`spike/s5/corpus/browser-validation/summary.json`,
`spike/s5/corpus/strategy-f-browser-replay.json` (Phase 3, 59 fixtures),
`spike/s5/corpus/browser-validation/duplicate-repair-browser-replay.json`
(Phase 3, 22 cases).

---

## 1. Executive summary

Spike S5 tested seven segment-identity strategies against
`rendered_false_positive == 0`. **Only Strategy F passes.** Strategies A–E
failed for distinct, evidenced reasons. Strategy E's render gate is necessary
but insufficient without persistent identity.

**Strategy F** injects `aimlBlockId` (RFC 4122 v4) into Gutenberg block JSON,
segment key `b:<uuid>:content`, with UUID-direct reconciliation and a render
gate. PHP spike: **0 rendered false positives** across 39 operations,
adversarial corpus, and scale benchmarks.

**Browser validation** (Playwright, real Gutenberg on dev site, Phase 2 + 3
combined): every mandatory Tier 1 identity operation (text edit, full
rewrite, duplicate ×3 shapes, copy/paste same-post and cross-post, reorder
×2, wrap/unwrap Group, move between Columns, transform ×3, split, merge,
undo/redo, delete+undo), the mandatory pattern gate (synced create/insert/
edit-propagation/detach, non-synced create/insert, duplicate of a
pattern-derived reference), duplicate-UUID repair against real
browser-produced content (22 cases, 0 failures), a concurrent-edit
simulation, and autosave/revision/REST/export/import checks all completed
with real browser or REST automation — no operation was blocked. **Governing
finding:** Gutenberg strips `aimlBlockId` (an unregistered custom attribute)
on every real edit, preserving it only through no-op saves and delete+undo;
a spike-only attribute-registration test proves this is fixable by
registering the attribute in block metadata, at the cost of duplication then
genuinely copying the UUID — which Strategy F's repair resolves. No-op save
is **byte-stable** across repeated cycles. Browser-derived replay through
Strategy F: **59 fixtures, 0 rendered false positives.**

**Strategy G** (registry table `aiml_block_map`) was not implemented. Cost
analysis concludes G avoids `post_content` mutation but **does not automatically
solve logical identity** without a persistent marker or C–E-equivalent
heuristics.

**Recommendation:** Conditionally adopt Strategy F for production *planning*,
contingent on (a) registering `aimlBlockId` in block metadata for the
eligible block set, (b) excluding synced-pattern-referenced content from
tagging, and (c) migration/backfill acceptance. See
`docs/adr/0013-gutenberg-segment-identity.md` (still Proposed, not Accepted).

---

## 2. Problem definition

Gutenberg blocks lack stable native IDs. Segment reconciliation must bind
translation rows to the correct logical block through edits, reorder, moves,
duplication, and identical content — without rendering a translation on wrong
content.

---

## 3. Non-negotiable invariants

From `INVARIANTS.md` and planning docs:

- Renderer purity (no identity inference at render time)
- `rendered_false_positive == 0` (spike exit gate)
- Stale translations suppress rather than false-render (Strategy E/F gate)
- No production `src/` changes in spike

---

## 4. Exit criteria

| Criterion | Result |
|---|---|
| `rendered_false_positive == 0` | **F: pass**; A–E: fail |
| Four gate artifacts evidenced | Identity, reconciliation, confidence model, assembly |
| Browser compatibility | **Mandatory gates closed** (Phase 3) — see §13; a few minor gaps remain non-blocking |
| Performance O(n) ≤ 15× | All strategies measured; F: 9.7× |

---

## 5–10. Strategy results (summary)

| Strategy | Key | Exit gate | Primary failure |
|---|---|---|---|
| A | `block:N` | fail | reorder/insert FP |
| B | `b:name:hash` | fail | collisions |
| C | structural path | fail | path reuse FP |
| D | C + hash rematch | fail | path reuse FP render |
| E | D + render gate | fail | identical at path |
| F | `b:uuid:content` | **pass** | availability loss on delete+reinsert |
| G | registry id | not built | costed only |

Detail: `docs/spike-s5/IMPLEMENTATION_LOG.md`.

---

## 11. Strategy G cost analysis

### Proposed schema (plausible model, not implemented)

```sql
CREATE TABLE {prefix}aiml_block_map (
  registry_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_type       VARCHAR(20) NOT NULL,
  source_id         BIGINT UNSIGNED NOT NULL,
  revision_id       BIGINT UNSIGNED NULL,
  block_fingerprint VARCHAR(64) NOT NULL,
  allocated_at      DATETIME NOT NULL,
  last_seen_at      DATETIME NOT NULL,
  status            VARCHAR(20) NOT NULL DEFAULT 'active',
  KEY idx_source (source_type, source_id),
  KEY idx_fingerprint (block_fingerprint)
);
```

Segment key: `g:<registry_id>:content`.

### Central question

**Without a persistent marker in `post_content`, what observable evidence
links an edited block to its prior registry row?**

Candidates: structural path + hash (C/D/E — failed exit gate), fuzzy rematch
(E — rejected), or **hidden metadata elsewhere** (postmeta per block — violates
I2 spirit and Gutenberg model). **G does not escape the need for either
persistent markers or heuristic reconciliation.**

### Cost areas

| Area | F | G |
|---|---|---|
| Schema migration | none | **large** |
| Save-path complexity | inject filter | allocate + match + cleanup |
| `post_content` mutation | yes (~55 B/block) | no |
| Copy/paste cross-post | UUID in content | registry scope ambiguous |
| Concurrency | last-writer on inject | row allocation races |
| Orphan cleanup | UUID missing → orphan | fingerprint drift → orphan |
| Debugging | read attr in content | join map + content |
| Implementation effort | **medium** | **very large** |

### Strategy G verdict

Costed as honest fallback if F rejected; **not required for exit gate** (F
passes). Does not automatically provide logical identity without heuristics
equivalent to C–E or content mutation (F).

---

## 12. Browser validation methodology

1. Host WP-CLI creates draft pages from fixtures; `inject-aiml-block-ids.php`
   adds UUIDs (spike helper only).
2. Playwright loads Gutenberg via **WP auth cookies** (avoids Cloudflare login
   form in headless Docker), scoping every block-content interaction through
   a `canvas(page)` iframe frame-locator (`iframe[name="editor-canvas"]`).
3. Editor operations per manifest via reusable helpers in
   `browser-validation/helpers/editor.ts` (select/edit/duplicate/copy-paste/
   move/save/wait-for-save/export); Save.
4. Host exports `post_content` via WP-CLI (true pre-operation baseline files
   captured separately, not self-comparison); `analyze-aiml-content.php`
   compares UUID state against the baseline.
5. Strategy F replay: `StrategyFBrowserReplayTest.php` (auto-discovers every
   browser-authored fixture and runs the real inject→extract→reconcile→
   render-gate pipeline order).
6. Duplicate-repair replay against real browser-produced duplicates:
   `tools/replay-duplicate-repair.php`.

Phase 2 run: `spike/s5/tools/run-browser-validation.sh`. Phase 3 additions:
`browser-validation/tests/pattern-workflow.spec.ts`,
`tier2-tier3-gaps.spec.ts`, `concurrent-edit.spec.ts`, plus a
`diagnostic.spec.ts` REST/autosave/export check and a spike-only
attribute-registration mu-plugin
(`wp-content/mu-plugins/zzz-s5-attribute-registration-spike.php`, added and
removed per evidence capture — never left active).

---

## 13. Browser validation results

Combined Phase 2 (core-block no-op save) + Phase 3 (mandatory identity,
pattern, duplicate-repair, third-party, autosave/REST/export,
concurrent-edit gates) results.

### Environment

- URL: `https://dev.biopentra.eu`
- WP **7.0.2**, Gutenberg block editor (bundled), PHP 8.4, MariaDB 11.8.8
- Playwright **1.51.0** / Chromium (Docker `mcr.microsoft.com/playwright:v1.51.0-jammy`)
- Auth: WP-CLI generated cookies (`write-auth-cookies-json.sh`)

### Harness failure diagnosis (fix-automation-first)

The Phase 2 `text-edit` and `duplicate-block` automations failed to complete.
Root cause: **iframe/canvas behavior** — WP 6.3+ renders the block editor
canvas inside `iframe[name="editor-canvas"]`, and the Phase 2 selectors
targeted block content directly on the top-level page, so every locator
timed out waiting for elements that only exist inside the iframe. Not a
selector-instability, focus, save-detection, toolbar, keyboard-shortcut,
auth, or Cloudflare issue. Fix: all block-content interaction routed through
a `canvas(page)` frame-locator helper. Additional Phase 3 harness fixes
(pattern dialog button label, inserter accessible name, pattern search
result being an async-loading `role="option"`, a "Detach pattern?"
confirmation dialog, and cross-post paste merging into existing text instead
of creating a sibling block) are detailed in
`docs/spike-s5/IMPLEMENTATION_LOG.md` → "Browser validation (Phase 3)". No
operation in Phase 3 was ultimately blocked; every attempted Tier 1/2/3
operation completed with real browser automation.

### Playwright helper changes

`browser-validation/helpers/editor.ts` gained/repaired: `canvas()` iframe
scoping, `selectBlock`, `duplicateBlock`, `copyBlock`,
`pasteAsNewBlockAfterLast` (new — focuses end of last block, `Enter`s a
fresh empty paragraph, then pastes, since Gutenberg replaces an empty
paragraph with pasted clipboard content instead of merging),
`insertPatternByName` (rewritten to use the Patterns tab + `role="option"`),
`convertSelectedBlockToSyncedPattern` (now accepts a `synced` flag for
non-synced pattern creation), `detachPattern` (handles the confirm dialog),
`savePost`/wait-for-save, and `assertNoBlockRecovery`.

### Block matrix tested

| Block | Tested | aimlBlockId preserved (no-op save) | Validation errors |
|---|---|---|---|
| core/paragraph | yes | yes | none |
| core/heading | yes | yes | none |
| core/list (+ list-item, implicit) | yes | yes | none |
| core/image | yes | yes | none |
| core/button / core/buttons | yes | yes | none |
| core/group | yes | yes | none |
| core/columns / core/column | yes | yes | none |
| core/quote | yes | yes | none |
| core/pullquote | yes | yes | none |
| core/table | yes | yes | none |
| core/cover | yes | yes | intermittent, **pre-existing, attribute-unrelated**: "unexpected or invalid content" recovery notice reproduces on an untagged control post too (see IMPLEMENTATION_LOG final-regression-pass note) |
| core/media-text | yes | yes | none |
| core/separator | yes | yes | none |
| core/spacer | yes | yes | none |
| core/html | yes | yes | none |
| core/shortcode | yes | yes | none |
| WooCommerce `customer-account` (dynamic) | yes | yes (content); **leaks to frontend HTML** as `data-aiml-block-id` | none |
| Rank Math TOC / FAQ (static/dynamic) | yes | yes | pre-existing plugin defect on an unrelated untagged control post, not attribute-related |

`core/list-item` was exercised inside the `core/list` fixture but not
isolated. No arbitrary plugins were installed for this spike; the two
third-party samples (WooCommerce, Rank Math) were already active on the dev
site.

### Operation matrix (Tier 1 — mandatory identity operations)

All completed with real browser automation; **not** automation failures.

| Operation | UUID before → after | Classification |
|---|---|---|
| Open + save (no-op), ×3 cycles | preserved, byte-stable | same logical block, UUID preserved |
| Delete then undo | preserved | same logical block, UUID preserved |
| Text edit / full rewrite | present → **stripped** | new logical block, UUID absent |
| Duplicate block / nested subtree / button (unregistered attr) | present → **stripped before duplication** | new logical block, UUID absent (no duplicate UUID ever reaches `post_content`) |
| Duplicate (**registered** attr, spike-only) | present → **copied verbatim** | new logical block, UUID copied |
| Copy/paste same-post | present → stripped | new logical block, UUID absent |
| Copy/paste into another post | present → stripped | new logical block, UUID absent |
| Reorder adjacent / non-adjacent | present → stripped | new logical block, UUID absent |
| Wrap in Group / unwrap from Group | present → stripped | new logical block, UUID absent |
| Move between Columns | present → stripped | new logical block, UUID absent |
| Transform p→h, p→q, h→p (unregistered) | present → stripped | block transformed, UUID dropped |
| Split paragraph / merge paragraphs | present → stripped | new logical block, UUID absent |
| Undo/redo of an edit | tracks the edit it undoes/redoes | same as edit outcome |

**Governing finding:** Gutenberg strips *any unregistered* custom block
attribute — including `aimlBlockId` — on every real edit operation, but
preserves it through pure no-op saves and delete+undo. Confirmed across ~40
browser-authored fixtures. This is safe for Strategy F: a stripped block
receives a fresh UUID on the next inject pass and renders as source-fallback
(never a wrong translation).

### Registered vs. unregistered attribute

Spike-only mu-plugin registered `aimlBlockId` for `core/paragraph`/
`core/heading` via `block_type_metadata` (never left active outside evidence
capture). Registered-attribute results: text edit **preserves** the UUID;
duplicate **copies** the UUID verbatim (the only real Gutenberg-produced
duplicate UUID in the whole corpus); transform to a different block type
still **drops** it (destination schema doesn't declare it); serialization
stays stable. **Conclusion: production must register `aimlBlockId`** via
`block_type_metadata`/`block.json` if survival through ordinary edits is
required — unregistered, it only survives no-op saves.

### Move/reorder, wrap/unwrap, transform, split/merge, undo/redo results

All behave identically under the governing finding above: any operation
that is a genuine Gutenberg "edit" strips the unregistered attribute; no
operation produced a validation warning, a block-recovery prompt, or
unrelated markup drift. Nested child UUIDs inside a wrapped/moved subtree
strip along with their parent's — no partial-survival case was observed.

### Pattern and synced-pattern results (mandatory gate)

| Sub-case | Result |
|---|---|
| Create synced pattern from a tagged block | `aimlBlockId` stripped on the pattern entity itself |
| Post-local identity after conversion | source block replaced by a bare `<!-- wp:block {"ref":N} /-->` reference — no attributes of its own |
| Insert synced pattern elsewhere | same reference; **no pattern content or UUID lives in the referencing post's `post_content` at all** |
| Edit pattern centrally | propagates **instantly** to every reference (patterns render live from the `wp_block` entity at render time, not by copy) |
| Detach synced pattern | materializes a real local block from the entity's *current* content; since the entity never carried the attribute, the detached copy has none |
| Non-synced pattern create + insert | same stripping on creation; insertion **materializes an independent local copy** stamped with `metadata.patternName` provenance — a one-time copy, not a live reference |
| Duplicate a pattern-derived reference (`wp:block`) | produces a second reference to the same entity; no new pattern, no identity ambiguity |
| Reusable block workflow | in WP 7.0.2, classic reusable blocks and synced patterns are the same `wp_block` mechanism — no separate code path to test |

**Recommendation:** Strategy F must **not** tag blocks inside pattern
entities, and must treat synced `wp:block` references as **out of scope**
(not a gap): the referencing post never contains the pattern's content to
tag, and the entity itself is stripped on every pattern-creation edit.
Strategy F should tag only post-local materialized blocks — ordinary
content, non-synced pattern insertions after materialization, and detached
pattern copies.

### Duplicate UUID repair against real browser-produced content

`tools/replay-duplicate-repair.php` run against all 22 browser
`dup-*`/`*-dup-*` fixtures (including third-party captures): **0 failures**.
Cases without the registered attribute are duplicate-free by construction
(attribute stripped before duplication); the one case with a genuine
Gutenberg-copied duplicate (`reg-duplicate`, registered attribute) is fully
repaired — first document-order occurrence retains the UUID, the later
occurrence is regenerated, the regenerated block carries no inherited
translation, the original continues to render, and a second repair pass is
idempotent (no further changes). First-wins-after-reorder was not observed
to produce any surprising behavior beyond "whichever copy is now first in
document order keeps the UUID" — an expected, documented consequence of the
policy, not a defect.

### Third-party block validation

| Block | Preservation | Stripping | Validation warnings | Serializer | Dynamic render | Frontend impact |
|---|---|---|---|---|---|---|
| WooCommerce `customer-account` | yes (content, no-op) | on real edits, same as core | none | stable | unaffected | **leaks `aimlBlockId` as `data-aiml-block-id`** via Interactivity API hydration data |
| Rank Math TOC/FAQ | yes (content, no-op) | on real edits, same as core | pre-existing unrelated plugin defect on an untagged control post | stable | unaffected | not observed |

No third-party block strips the attribute differently from core blocks
(same unregistered-attribute rule applies uniformly); registering the
attribute is expected to fix preservation the same way it does for core
blocks, but was not separately re-verified for third-party blocks under
registration (core-block-only registration spike).

### Autosave, revision, REST, export/import

| Check | Result |
|---|---|
| Autosave preserves UUID | proven (`POST .../autosaves` round-trip + resulting revision row) |
| Manual revision preserves UUID | proven (snapshot verbatim) |
| Restoring a revision restores prior UUID | inferred (byte-for-byte snapshot restore; no attribute-specific logic), not independently browser-tested |
| REST read preserves UUID | proven (`GET ?context=edit`; this install requires `X-WP-Nonce` even for authenticated GETs) |
| REST write preserves UUID | proven (`POST` round-trip) |
| Preview preserves UUID | inferred from proven autosave/revision path; no dedicated preview-iframe test |
| XML export preserves UUID | proven (WXR `<content:encoded>`) |
| XML import preserves UUID | proven (`wp import` round-trip) |
| Post duplication (native) | not applicable — WordPress core has no native duplicate-post feature |

No workflow above created duplicate identity across *different* posts (the
one duplicate-identity event observed all phase was the same-post
concurrent-edit race below).

### Concurrent-edit simulation (adversarial, single scenario)

Two Playwright pages in one authenticated context opened the same
registered-attribute post; both duplicated the same block; A saved, then B
saved. Result: classic **last-write-wins** — B's save fully overwrote A's
(WordPress performs no merge, no optimistic lock). Final content contained
a genuine duplicate UUID. Strategy F's repair fully resolved it (first-wins
retention, single regeneration, idempotent replay,
`rendered_false_positive == 0` after repair). This is one adversarial
simulation, not proof of general concurrency safety — the lost-update
itself is a WordPress editing property outside Strategy F's control; Strategy
F's contribution is that no wrong translation can render even through it.

### Third-party / production content caveat

Elementor is the primary production authoring tool on the eventual
migration target; this spike's Gutenberg validation used isolated draft
pages and does not characterize Elementor-authored production content.

### Browser evidence table

Per the decision-rules requirement, `blocked` and `failed` are kept distinct:
`blocked` means an environment/tooling constraint prevented the attempt
(not a Gutenberg or Strategy F defect); `failed` would mean the attempt ran
and produced an unsafe or incorrect result (none occurred).

| Gate | Status |
|---|---|
| Harness repair (text-edit, duplicate-block automation) | **passed** |
| Tier 1 mandatory identity operations (all 19 listed) | **passed** |
| Tier 2 pattern gate (synced create/insert/edit/detach, non-synced, duplicate reference) | **passed** |
| Tier 3 same-site cross-post copy/paste | **passed** |
| Tier 3 cross-browser-tab copy/paste (isolated clipboard) | **not tested** |
| Tier 3 native post duplication | **not applicable** (no core feature) |
| Tier 3 cross-site copy/paste | **blocked** (no second WordPress installation available) |
| Duplicate-UUID repair vs. real browser content (22 cases) | **passed** |
| Block-type transformation UUID behavior | **passed** (attribute drop confirmed safe) |
| Split/merge UUID behavior | **passed** |
| Registered-vs-unregistered attribute spike | **passed** |
| Third-party block validation (WooCommerce, Rank Math) | **passed** |
| Autosave / manual revision preserve UUID | **passed** |
| Revision-restore preserves UUID | **not tested** (inferred from proven snapshot immutability) |
| Preview preserves UUID | **not tested** (inferred from proven autosave/revision path) |
| REST read/write preserve UUID | **passed** |
| XML export/import preserve UUID | **passed** |
| Concurrent-edit simulation | **passed** (adversarial single-scenario evidence only) |
| Browser-derived Strategy F replay (59 fixtures) | **passed** — `rendered_false_positive == 0` |
| Exhaustive third-party sample beyond 2 blocks | **not tested** |
| Elementor-authored production content characterization | **not tested** — out of scope for this spike |

---

## 14. Full A–G comparison

See IMPLEMENTATION_LOG cumulative table and §11 above.

---

## 15. Performance comparison

| Strategy | 1000-block total ms | 1000/100 ratio | FP at scale |
|---|---|---|---|
| E | 19.5 | 10.2× | 0 (stable doc) |
| F | 35.3 | 9.7× | 0 |

F injection adds ~8–28 ms per 100–1000 blocks (parse+serialize).

---

## 16. Content-mutation implications (Strategy F)

- ~55 bytes per eligible block on first inject.
- Browser noop save: **no drift** (evidenced on 18 block types + 3-cycle test).
- Second PHP inject pass: idempotent.
- Revisions: first backfill will create revision entries (production concern).
- Real Gutenberg edits **strip an unregistered custom attribute**, so a
  save-time re-inject filter is mandatory in production, not optional; this
  was assumed at Phase 1 and is now **directly confirmed** by Phase 3
  browser evidence rather than inferred from PHP alone.
- Registering the attribute (Phase 3 spike) makes duplication genuinely copy
  the UUID rather than dropping the block untagged — first-wins repair was
  verified against exactly that real browser-produced case.

---

## 17. Database-registry implications (Strategy G)

New table, allocation on save, fingerprint matching, orphan GC, multisite
isolation, post duplication copying map rows — **very large** effort. See §11.

---

## 18. Remaining risks

1. `aimlBlockId` is **not registered** in block.json/block metadata in the
   spike contract — Phase 3 proved this means it survives only no-op saves
   and delete+undo, not ordinary edits. Production must decide to register
   it (recommended) or explicitly accept re-inject-on-every-save as the sole
   preservation mechanism.
2. Elementor-primary production content may not use Gutenberg blocks at
   all — out of scope for this spike; unresolved for the eventual migration.
3. Synced-pattern-referenced content requires a separate translation
   strategy (tag the `wp_block` entity as its own document) — not
   addressed by tagging referencing posts, per the Phase 3 pattern-gate
   finding.
4. WooCommerce Interactivity-API blocks leak `aimlBlockId` into frontend
   HTML (`data-aiml-block-id`) — needs a production allowlist/strip-on-render
   decision; not a `rendered_false_positive` but a metadata-exposure concern.
5. Concurrent-edit lost-update (last-write-wins) is a WordPress editing
   property Strategy F cannot prevent; only one adversarial scenario was
   simulated — not proof of general concurrency safety.
6. Minor non-blocking gaps remain: cross-tab clipboard isolation, cross-site
   copy/paste (no second install available), native post duplication (no
   core feature to test), and dedicated preview-iframe / revision-restore
   browser proof (both inferred from already-proven adjacent paths).

---

## 19. Recommended architecture

**Strategy F** with:

- Attribute: `aimlBlockId` (RFC 4122 v4), **registered** via
  `block_type_metadata`/`block.json` for every eligible block type
  (Phase 3 finding: required for survival through ordinary edits)
- Key: `b:<uuid>:content`
- Reconciliation: UUID direct match only
- Repair: first-wins on duplicate UUID (proven against real
  browser-produced duplicates, including under a concurrent-edit race)
- Render gate: Strategy E safety rules adapted for UUID reasons
- Scope: post-local materialized blocks only — synced pattern (`wp:block`)
  references explicitly excluded; pattern entities require their own
  independent tagging/translation strategy if desired

Conditionally accepted for production **planning** (ADR-0013 remains
Proposed — see §21).

---

## 20. Production prerequisites

Detailed implementation sequencing, module layout, migration design, rollout
stages, and open decisions: [`docs/plans/STRATEGY_F_PRODUCTION_IMPLEMENTATION.md`](../plans/STRATEGY_F_PRODUCTION_IMPLEMENTATION.md).

1. ~~Extended browser validation (duplicate, paste, patterns)~~ — **done**
   (Phase 3, all mandatory gates; minor non-blocking gaps listed in §18).
2. Decide and implement `aimlBlockId` registration strategy
   (`block_type_metadata`/`block.json`) — Phase 3 evidence shows this is
   required, not optional, if ordinary-edit survival matters.
3. Production save-time inject filter (not spike WP-CLI tooling).
4. Explicit architectural decision to exclude synced-pattern references from
   tagging (translate pattern entities separately if needed).
5. Migration/backfill runbook, including revision-entry impact of first
   backfill.
6. Architect/PO sign-off on `post_content` mutation and on the WooCommerce
   frontend-leakage risk (§18.4).
7. ADR-0013 accepted.
8. M1 regression suite unchanged.

---

## 21. Production-readiness statement

**This spike is not production-ready; Strategy F is conditionally accepted
for planning only.** PHP exit gate passes for Strategy F
(`rendered_false_positive == 0`). Phase 3 browser validation closed every
mandatory gate from the Phase 3 charter — Tier 1 identity operations, the
pattern/synced-pattern gate, duplicate-repair against real browser content,
third-party blocks, registered-vs-unregistered attribute behavior,
autosave/revision/REST/export, and an adversarial concurrent-edit
simulation — with **zero rendered false positives** across 59
browser-derived replay fixtures and **zero unrepaired duplicates** across 22
duplicate-repair cases. No mandatory workflow produced unsafe continuity;
Strategy F is not rejected. However, production readiness is **not** claimed:
attribute registration, a production inject filter, the synced-pattern
exclusion decision, and a migration/backfill runbook are still unbuilt (§20),
and a handful of non-blocking gaps remain untested (§18.6). Do not implement
production UUID injection or promote ADR-0013 to Accepted until §20's
prerequisites are complete and explicitly accepted by architecture review.

---

## Confirmations

- Strategy G **not implemented**
- Production `src/` **untouched**
- No production UUID injection was implemented (spike WP-CLI/mu-plugin
  tooling only, all spike-scoped and removed after evidence capture)
- Strategies A–F PHP evidence **reproducible**
- Strategy E exit-gate failure **intentional**
- All browser claims in this document use **browser-authored evidence**
  (Playwright-driven real Gutenberg saves on dev.biopentra.eu, or REST/WP-CLI
  calls made through an authenticated browser context); no claim is asserted
  from PHP-only synthetic fixtures
- No blocked automation is reported as a product/Gutenberg failure — every
  `blocked`/`not tested` row in §13's evidence table is a stated
  environment/tooling constraint (no second WP install, no core
  post-duplication feature, shared-clipboard test context), not a defect
- Production readiness is **not** claimed; ADR-0013 remains **Proposed**
