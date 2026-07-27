# Strategy F — Production Implementation Plan

**Status:** Planning only — no production code, hooks, migrations, or schema changes in this document.  
**Spike:** S5 complete (branch `spike/s5`, HEAD `42237bd`).  
**Selected model:** Strategy F — `aimlBlockId` attribute, segment key `b:<uuid>:content`.  
**ADR-0013:** Proposed — not Accepted.  
**Production implementation:** Not started.  
**Production readiness:** Not approved.

This plan translates spike evidence into implementable Milestone 2 work packages. It does **not** supersede [`APPROVED_PLAN_REV3.md`](APPROVED_PLAN_REV3.md); it **specializes** the block-identity portion that Rev 3 deferred to Spike S5 (§5.2 segment key grammar, `block:N` drift note).

**Evidence sources:** [`docs/spikes/S5-gutenberg-segment-identity.md`](../spikes/S5-gutenberg-segment-identity.md), [`docs/adr/0013-gutenberg-segment-identity.md`](../adr/0013-gutenberg-segment-identity.md), [`docs/spike-s5/IMPLEMENTATION_LOG.md`](../spike-s5/IMPLEMENTATION_LOG.md), spike reference code under `spike/s5/lib/Strategy/`.

---

## 1. Production architecture

### 1.1 Proposed components

| Component | Responsibility | Likely module |
|---|---|---|
| **Attribute contract** | `aimlBlockId` name, UUID v4 regex, segment key shape | `src/Block/Contract.php` |
| **Attribute registration** | Declare `aimlBlockId` on eligible block types (PHP + JS parity) | `src/Block/AttributeRegistrar.php`, `assets/block-editor.js` |
| **Block registry / eligibility** | Which block types and instances receive UUIDs | `src/Block/BlockRegistry.php` |
| **UUID generation** | RFC 4122 v4, cryptographically suitable randomness | `src/Block/UuidGenerator.php` |
| **UUID validation** | Format check, length cap, reject non-string | `src/Block/UuidValidator.php` |
| **Block tree walker** | Document-order traversal over `parse_blocks()` output | `src/Block/BlockTreeWalker.php` |
| **UUID injection + repair** | Assign missing UUIDs, first-wins duplicate repair, serialize only if changed | `src/Block/UuidInjector.php` |
| **Save-time persistence** | Hook orchestration, recursion guard, permission checks | `src/Block/SavePipeline.php` |
| **Block extraction** | Eligible leaf blocks → segment rows (`field_key=post_content`, `segment_key=b:…`) | `src/Translation/BlockExtractor.php` |
| **Segment-key builder** | `b:<uuid>:content` from block attrs | `src/Block/SegmentKey.php` |
| **Reconciliation** | Match rows by segment key; mark orphaned/stale; no fuzzy rematch | `Store::sync_source()` + block-aware extractor |
| **Render gate** | Suppress translation unless continuity provable | `src/Translation/BlockRenderGate.php` |
| **Block renderer** | Replace leaf innerHTML/text at render time (overlay model) | `src/Translation/BlockRenderer.php` |
| **Frontend sanitizer** | Strip `aimlBlockId` / `data-aiml-block-id` from public HTML where needed | `src/Block/FrontendSanitizer.php` |
| **Migration / backfill** | Batch inject UUIDs into existing `post_content` | `src/Migration/UuidBackfillCommand.php` |
| **Feature flags** | Independent toggles per capability | extend `src/Settings.php` |
| **Observability** | Structured logs + counters (no source/translated text) | `src/Observability/BlockIdentityLogger.php` |

Spike classes (`RealBlockWalker`, `UuidInjector`, `StrategyFRenderGate`, etc.) are **reference implementations** to port — not imported at runtime.

### 1.2 End-to-end data flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│ SAVE PATH (mutates post_content when injection enabled)                 │
├─────────────────────────────────────────────────────────────────────────┤
│ Editor / REST / WP-CLI / import                                       │
│   → wp_insert_post_data (recommended primary hook)                    │
│       → parse_blocks( post_content )                                    │
│       → validate existing aimlBlockId (format, length)                  │
│       → assign missing UUIDs on eligible leaves only                    │
│       → detect duplicate UUIDs (document order)                         │
│       → repair duplicates (first-wins; regenerate later occurrences)    │
│       → serialize_blocks() only if tree changed                         │
│       → return modified post_content to core save                       │
│   → save_post (existing, priority ≥ injection)                          │
│       → BlockExtractor::extract( post )                                 │
│       → Store::sync_source( segments )  // stale/orphan, no fuzzy match │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│ LOAD / RENDER PATH (read-only overlay — invariant I1–I3)                │
├─────────────────────────────────────────────────────────────────────────┤
│ the_content @ priority 1 (before do_blocks)                             │
│   → parse_blocks( post_content )                                        │
│   → for each eligible leaf with aimlBlockId:                            │
│       segment_key = b:<uuid>:content                                    │
│       row = Store::get( source_post, language, segment_key )            │
│       BlockRenderGate::resolve( row, block, repair_context )            │
│         → translation innerHTML OR source fallback                      │
│   → serialize_blocks() → HTML pipeline continues                      │
└─────────────────────────────────────────────────────────────────────────┘
```

### 1.3 Operations that mutate `post_content`

| Operation | Mutates content? | Plan |
|---|---|---|
| First UUID backfill / migration batch | **Yes** | `wp aiml blocks backfill` |
| Save-time injection (flag on) | **Yes**, when UUIDs added/repaired | `wp_insert_post_data` |
| Duplicate repair on save | **Yes**, when duplicates detected | same pipeline |
| Malformed UUID replacement | **Yes** | same pipeline |
| Block editor text edit | Yes (editor) — UUID preserved if registered | no plugin write |
| Translation render | **No** | overlay only |
| Reconciliation (`sync_source`) | **No** | DB rows only |
| Rollback (rendering off) | **No** | flags only; leave UUIDs in content |

---

## 2. Attribute registration

### 2.1 Recommendation: server-first, editor-mirrored

**Primary:** PHP `block_type_metadata` filter (evidence: spike mu-plugin used this successfully).

**Secondary:** Block editor script registering the same attribute on client for blocks that ship JS definitions — required so Gutenberg's serializer preserves attrs through edits, duplicate, and paste.

| Approach | Pros | Cons |
|---|---|---|
| PHP `block_type_metadata` only | Single source; covers dynamic blocks without JS | Editor may not show attr in UI (acceptable — attr is internal) |
| PHP + `@wordpress/blocks` filter in editor | Full edit/duplicate survival (Phase 3 proved) | Must keep PHP/JS attribute defs in sync |
| Per-block `block.json` patches | Explicit for third-party | Impractical at scale; many blocks lack local json |

**Recommendation:** **Global PHP registration** via filtered metadata for all registry-approved block types, plus a **minimal editor bootstrap** that registers `aimlBlockId: { type: 'string' }` for the same allowlist (generated from PHP export or shared JSON manifest).

### 2.2 Block categories

| Category | Registration | UUID injection | Notes |
|---|---|---|---|
| Core static leaves (paragraph, heading, …) | PHP + JS | Yes | Phase 3 noop-save stable |
| Core containers (group, columns) | Register attr | **No UUID on container** | Spike eligibility: leaves only |
| `core/block` (synced reference) | Optional register | **No** | Out of scope for post-local identity |
| Dynamic core (`latest-posts`, `query`, …) | Skip or register without inject | **No** | Saved innerHTML not authoritative |
| WooCommerce blocks | Per-block allowlist after audit | If allowlisted | `customer-account` leaks to frontend — sanitizer required |
| Rank Math / other plugins | Allowlist after audit | If allowlisted | Pre-existing validation quirks documented |
| `core/html`, `core/shortcode` | Allowlist with caution | If allowlisted | Serializer differs; test per block |

### 2.3 Unsupported / problematic blocks

- **Default:** register attribute but **exclude from injection/extraction** until explicitly allowlisted.
- **Stripping behavior:** unregistered → Gutenberg strips on edit (Phase 3); registered → survives edits.
- **Frontend exposure:** blocks using Interactivity API may emit `data-aiml-block-id` — see §12.

---

## 3. Eligible-block policy

**Recommendation:** adopt spike **eligible-leaf** policy as production default, with explicit exclusions.

| Block shape | Receives `aimlBlockId`? | Extracted as segment? | Rationale |
|---|---|---|---|
| Translatable leaf (paragraph, heading, button, list-item text, …) | Yes | Yes | Spike + browser evidence |
| Nested leaves inside containers | Yes | Yes | Each leaf own UUID |
| Container (group, columns, quote wrapper) | No | No | Spike: container innerHTML not eligible |
| Empty leaf (trim innerHTML '') | No | No | Matches `Extractor` empty skip |
| Dynamic block (`DYNAMIC_BLOCK_NAMES`) | No | No | innerHTML not render truth |
| `core/block` reference | No | No | Phase 3 pattern gate |
| Synced pattern entity (`wp_block` CPT) | Separate doc scope | Separate object | Not post-local |
| Detached / non-synced materialized copy | Yes | Yes | Post-local after materialization |
| `core/separator`, `core/spacer` | No | No | No meaningful text |
| `core/html`, `core/shortcode` | Allowlist decision | If allowlisted | Product decision |
| WooCommerce / plugin blocks | Allowlist only | If allowlisted | Third-party audit |

Initial dynamic list (from spike `StructuralPathWalker::DYNAMIC_BLOCK_NAMES`):

`core/latest-posts`, `core/block`, `core/query`, `core/post-title`, `core/navigation`, `core/template-part`

Production registry must be **extensible** (filter `aiml_block_dynamic_block_names`).

---

## 4. UUID scope and ownership

### 4.1 Semantic layers

| Layer | Rule |
|---|---|
| **UUID format** | Globally unique RFC 4122 v4 string (collision probability negligible) |
| **Semantic identity** | Document-local: `(source_type, source_id, segment_key)` |
| **Database uniqueness** | Existing `segment_identity (source_type, source_id, segment_hash, language_id)` — `segment_hash = sha1(field_key ␟ segment_key)` |
| **Translation ownership** | Row belongs to exactly one `(source_type, source_id, language_id)`; render gate verifies object match |

**Cross-post safety:** the same UUID string in two posts creates **two independent segment keys** scoped by `source_id`. No cross-post continuity. Render gate must verify `source_id` on row lookup (already implicit in `Store::get`).

### 4.2 Transfer scenarios

| Scenario | UUID in content | Translation rows | Policy |
|---|---|---|---|
| Same-post duplicate (registered attr) | Copied → repair regenerates later copy | Original row matches first occurrence; regenerated UUID → no row → source fallback | First-wins repair |
| Cross-post copy/paste | Attribute stripped (unregistered) or copied (registered) | Target post rows independent | Repair on target save; no row inheritance |
| Native post duplication | Not tested (no core feature) | N/A | If plugin duplicates: treat as new object, repair all UUIDs or regenerate all |
| XML import | UUID preserved (Phase 3) | Import as new `source_id` | Rows do not auto-migrate; backfill/re-translate |
| Revision restore | Byte snapshot restores UUIDs (inferred) | Rows unchanged until sync | `sync_source` marks stale if text differs |
| Pattern detach | Materialized blocks have no entity UUID | New post-local segments after inject | Inject on first save after detach |

---

## 5. Synced patterns

Phase 3 conclusions are **binding** for production:

| Entity | Tag with `aimlBlockId`? | Translate how? |
|---|---|---|
| `wp:block` reference in post | **No** | N/A — no materialized content in post |
| Synced pattern entity (`wp_block` CPT) | **Not in post save pipeline** | Optional future: treat `wp_block` as its own `source_type`/`source_id` |
| Central pattern edit | Propagates to all references live | Post rows unaffected |
| Non-synced pattern insertion | Materialized local blocks | Inject UUIDs on post save after insertion |
| Detached copy | Post-local blocks | Inject + extract normally |

**Production rule:** `BlockRegistry::is_eligible()` returns false for `core/block` regardless of registration.

---

## 6. Save-time integration

### 6.1 Recommended architecture: **pre-insert filter (primary) + REST guard**

| Hook | Role |
|---|---|
| `wp_insert_post_data` (priority 5–10) | **Primary** — mutate `post_content` before DB write; covers block editor, classic, most programmatic saves |
| `rest_pre_insert_{post_type}` | Validate/sanitize for REST-only edge cases if filter insufficient |
| `content_save_pre` | Legacy classic fallback if needed |

**Not recommended as primary:** post-save `wp_update_post` loop (recursion risk, extra revision).

**Autosaves / revisions:** apply injection on autosave content if flag enabled (Phase 3 proved UUID survives autosave REST). Skip injection on revision rows if they would multiply noise — **product decision** (see Open Decisions).

### 6.2 Save pipeline (required behavior)

1. Guard: feature flag, capability, `wp_is_post_revision`, recursion static.
2. Skip if post has Elementor body (`Extractor::body_status === elementor`) — unchanged M1 guard extended later in M6.
3. Skip if no blocks (`has_blocks` false).
4. `parse_blocks`.
5. Validate / normalize malformed UUIDs (replace with new v4, log `malformed_replaced`).
6. Assign UUIDs on eligible leaves missing attr.
7. Count duplicates → repair first-wins.
8. `serialize_blocks` — compare to input; set `post_content` only if changed.
9. Do **not** call `wp_update_post` again from within hook.

### 6.3 Entry points

| Entry | Handled by |
|---|---|
| Gutenberg save | `wp_insert_post_data` |
| REST `POST /wp/v2/posts` | same |
| Autosaves | same (if not excluded by flag) |
| WP-CLI `wp post update` | same |
| Imports | backfill command + save hook |
| WooCommerce products | only if `post_content` block body — product description path separate in M4 |

### 6.4 Failure handling

- Parse failure → leave content unchanged, log error, do not block save.
- Injection exception → leave content unchanged, log, optional admin notice for editors with `aiml_translate`.
- Permission: injection runs only when user can edit post; analysis-only mode can run without write.

---

## 7. Duplicate repair

**Policy:** `first_wins` (spike default, browser-verified). Document-order first eligible occurrence keeps UUID; later occurrences get new v4 UUIDs.

| Concern | Behavior |
|---|---|
| Traversal order | Depth-first pre-order (same as spike `UuidBlockWalker`) |
| Detection | Count UUID occurrences while walking |
| Regenerated blocks | Added to `regenerated_uuids` context for render gate |
| Translation state | Regenerated UUID → no row → `unknown_uuid` / source fallback — **never inherit** |
| Original block | Row unchanged if UUID + hash match |
| Idempotence | Second pass: 0 duplicates, 0 content change |
| Concurrency | Last save wins; repair runs on each save — maintains `rendered_false_positive == 0` |
| Tampering | Invalid format → replace; duplicate → repair |

---

## 8. Translation data model

### 8.1 Current schema (repository evidence)

From `src/Database/Schema.php` — **no new tables required** for Strategy F.

**`aiml_translations` key columns:**

- `segment_identity`: UNIQUE `(source_type, source_id, segment_hash, language_id)`
- `segment_hash`: `sha1(field_key . "\x1f" . segment_key)` (`Store::segment_hash`)
- `segment_key`: VARCHAR(191) — `b:<uuid>:content` fits (~48 chars)
- `field_key`: `post_content` for block segments
- `source_hash`: staleness detection (normalized text hash)
- `status`: includes `ignored` for orphans
- `is_stale`: separate freshness axis

### 8.2 Schema impact recommendation

| Change | Required? |
|---|---|
| New tables | **No** |
| New columns | **No** for MVP |
| Index changes | **No** — existing indexes sufficient |
| `segment_kind` value `block` | **Optional** — use `field` initially for simplicity (M1 pattern) or add `Store::KIND_BLOCK` constant |

**Optional M2 enhancement:** add `block_uuid CHAR(36)` column for diagnostics — **defer** unless query patterns require it; segment_key already embeds UUID.

### 8.3 Reconciliation (existing `Store::sync_source`)

Block extraction produces segment map keyed by `b:<uuid>:content`. `sync_source`:

- Row key missing in extract → `status=ignored`, `error_code=orphaned`
- Key present, hash differs → `is_stale=1`, update `source_text`/`source_hash`
- **No fuzzy matching, no path rematch, no position inference**

Render-time lookup uses `(source_type, source_id, language_id, segment_key)` — prevents cross-object joins even if UUID string collides across posts.

---

## 9. Migration and backfill

### 9.1 Discovery queries (volume estimates — run on target DB)

```sql
-- Posts with block content (candidates)
SELECT post_type, post_status, COUNT(*) AS cnt
FROM wp_posts
WHERE post_content LIKE '%<!-- wp:%'
  AND post_status IN ('publish','draft','private','pending')
GROUP BY post_type, post_status;

-- Posts without aimlBlockId yet
SELECT COUNT(*) FROM wp_posts
WHERE post_content LIKE '%<!-- wp:%'
  AND post_content NOT LIKE '%"aimlBlockId"%';

-- Approximate eligible block density (after deploy: WP-CLI)
-- wp aiml blocks analyze --dry-run --post_type=page
```

WP-CLI command `wp aiml blocks analyze` (M6 dry-run) reports: post count, eligible leaf count, estimated bytes/post (~55 B/block from spike).

### 9.2 Backfill design

| Property | Design |
|---|---|
| Discovery | Query posts with `has_blocks`; skip Elementor |
| Dry run | `--dry-run` logs stats, no writes |
| Batching | `--batch-size=100`, `--offset=` cursor |
| Resumability | Checkpoint option `aiml_backfill_cursor` or id-range args |
| Idempotence | Inject pipeline skips existing valid UUIDs |
| Locking | `GET_LOCK('aiml_uuid_backfill')` or option flag |
| Revisions | **Default:** update canonical post only; optional `--include-revisions` |
| Cache | `Store` invalidate per touched post |
| Rollback | Stop command; UUIDs remain harmless in content |

### 9.3 Impact types (not site-specific counts)

| Impact | Expectation |
|---|---|
| Serialized bytes | ~55 bytes × eligible leaves per post (spike measured) |
| Revision growth | One revision per backfilled save if hook fires; batch tool should use `$wp_db->update` + `wp_save_post_revision` policy explicitly |
| DB writes | One post row update per backfilled post + `sync_source` row touches on next edit |
| Processing time | O(n) blocks — spike ~35ms/1000 blocks inject |

---

## 10. Existing translation migration

M1 may have `post_content` field-level rows (`segment_key = post_content`). M2 block segmentation **changes key grammar**.

| Legacy row | Treatment |
|---|---|
| `segment_key = post_content` (field-level body) | **Retain as legacy** — do not render once block rendering enabled (whole-field overlay disabled for block posts) |
| No proof of block mapping | **Do not fuzzy rematch** |
| Positional `block:N` rows (if any experimental) | Mark `ignored` or leave orphaned — **no automatic migration** |
| Reviewed translations | Preserved in DB; human re-link only via re-translation workflow |

**Mandatory:** block render gate + feature flag ensures unproven rows never render (`rendered_false_positive == 0`).

---

## 11. Render gate

Port spike `StrategyFRenderGate` + `StrategyFSuppressionReason` to production.

| Check | Suppression reason | Action |
|---|---|---|
| Feature flag off | `feature_disabled` | Source |
| UUID missing on block | `missing_uuid` | Source |
| Malformed UUID | `malformed_uuid` | Source |
| Duplicate UUID in document (pre-repair context) | `duplicate_uuid` | Source |
| UUID was regenerated this save | `regenerated_uuid` | Source |
| No translation row | `unknown_uuid` | Source |
| Row status `ignored` | `orphaned_row` | Source |
| Block type mismatch row vs live | `block_type_mismatch` | Source |
| `source_hash` mismatch | `stale_hash` | Source |
| Empty translation | `empty_translation` | Source |
| Row `source_id` ≠ current post | `object_mismatch` | Source (add in production) |
| Language mismatch | implicit in lookup | Source |

**Invariant:** uncertainty → source fallback, never wrong translation.

---

## 12. Frontend metadata exposure

Phase 3: WooCommerce `customer-account` emits `data-aiml-block-id`.

| Mitigation | Apply when |
|---|---|
| `render_block` filter strips unknown data attributes | Global default for production |
| Block-specific denylist | Known Interactivity API blocks |
| Do not register attr on blocks that cannot be sanitized | Last resort |

Serialized `aimlBlockId` in block comment JSON **must remain** for identity; only **frontend HTML** is sanitized.

---

## 13. Concurrency

| Scenario | Behavior |
|---|---|
| Two editors, last-write-wins | WordPress core semantics; later save replaces content |
| Both duplicate same block | Duplicate UUID until save repair — gate suppresses false render |
| Post locks | Respect core lock UI; no custom merge |
| Stale session save after repair | Repair runs again on save |

**Goal:** `rendered_false_positive == 0` under adversarial save order — **not** full OT/CRDT editing.

---

## 14. Observability

Structured log event names (no source/translated text):

`uuid_generated`, `uuid_preserved`, `uuid_malformed_replaced`, `duplicate_detected`, `duplicate_repaired`, `post_content_mutated`, `backfill_post_ok`, `backfill_post_fail`, `render_suppressed`, `render_applied`, `migration_batch_complete`

Diagnostic fields: `post_id`, `post_type`, `block_name`, `uuid` (identifier only), `suppression_reason`, `bytes_added`, `batch_id`, `user_id`.

Metrics (if available): counters per suppression reason, injection latency histogram.

---

## 15. Feature flags and rollout

Extend `Settings` (or dedicated option keys):

| Flag | Purpose |
|---|---|
| `block_attr_registration_enabled` | Register aimlBlockId |
| `block_uuid_analysis_enabled` | Parse/report only |
| `block_uuid_injection_enabled` | Mutate post_content on save |
| `block_uuid_repair_enabled` | Duplicate repair (sub-flag of injection) |
| `block_extraction_enabled` | Block-level sync_source segments |
| `block_render_enabled` | Block overlay rendering |
| `block_migration_enabled` | WP-CLI backfill writes |
| `block_diagnostics_enabled` | Verbose logging |

### Rollout stages

| Stage | Flags | Entry | Success metric | Stop / rollback |
|---|---|---|---|---|
| 1 Deploy disabled | all off | Code merged | No user-visible change | N/A |
| 2 Observation | analysis on | Internal | Parse reports stable | Disable analysis |
| 3 Dry-run backfill | analysis + migration dry-run | Staging | Counts match manual audit | Fix parser |
| 4 Internal content | injection on, render off | Team posts | UUID coverage 100% eligible | Disable injection |
| 5 Cohort | + extraction | Selected post types | sync_source stable | Disable extraction |
| 6 Backfill prod | migration batch | Off-peak window | Checkpoint progress | Stop batch |
| 7 Render pilot | render on cohort | 1 language | 0 FP reports | Disable render flag |
| 8 General | all on | PO sign-off | ADR Accepted + metrics | Rollback §16 |

---

## 16. Rollback

| Layer | Rollback |
|---|---|
| Rendering | Flag off — immediate source-only display |
| Extraction | Flag off — field-level stale detection only |
| Injection | Flag off — no new mutations; existing UUIDs harmless |
| Repair | Flag off — duplicates possible if editors duplicate; gate still suppresses |
| Migration | Stop batch; no automatic UUID removal |
| Translation rows | Never auto-delete on rollback |
| Schema | No rollback needed (no schema change) |

**Prefer leaving UUID metadata in content** unless legal/compliance requires removal (then dedicated `wp aiml blocks strip-uuids` maintenance command — out of scope for initial rollout).

---

## 17. Security

| Threat | Mitigation |
|---|---|
| Malicious UUID in content | Validate format + length ≤ 36; replace if invalid |
| Oversized attr | Reject/replace strings > 36 chars |
| Cross-post UUID injection | Row scoped by `source_id`; gate checks object |
| REST exposure | Attribute in edit context only; sanitizer on render |
| Frontend leakage | §12 |
| Migration CLI | `manage_options` or dedicated cap; `--dry-run` default on prod |
| CSRF | Existing admin nonces; REST nonces |
| Logging PII | Never log post body text |

UUIDs are **identifiers**, not auth tokens.

---

## 18. Test strategy

### Unit
- `UuidGenerator`, `UuidValidator`, `SegmentKey`
- `BlockRegistry` eligibility matrix
- `UuidInjector` inject/repair/idempotence (port spike tests)
- `BlockRenderGate` truth table (port spike tests)
- Synced pattern exclusion

### Integration
- Save post → content gains UUIDs
- REST save round-trip
- Revision/autosave UUID preservation
- `sync_source` orphan/stale
- Duplicate save → repair → render gate
- Import XML
- WooCommerce block allowlist + sanitizer

### Browser (reuse spike harness patterns)
- Edit, duplicate, paste, transform, split/merge, patterns, concurrent-edit smoke

### Adversarial
- Duplicate render bypass attempts
- Stale hash with reviewed row
- Cross-post same UUID string
- Tampered UUID in raw content

**Exit invariant:** `rendered_false_positive == 0`

---

## 19. Implementation milestones

| Milestone | Scope | Modules | Depends | Tests | Acceptance | Effort | Rollback |
|---|---|---|---|---|---|---|---|
| **M1** Attribute contract + registration | Contract, AttributeRegistrar, editor script, flags stub | `Block/*` | — | Unit + browser noop save | Attr survives edit on paragraph | S | Disable registration flag |
| **M2** UUID inject + repair pipeline | UuidInjector, SavePipeline, BlockTreeWalker | `Block/*` | M1 | Unit + integration save | Inject idempotent; repair 22-case replay equivalent | M | Disable injection flag |
| **M3** Duplicate repair hardening | Logging, tamper handling | M2 | M2 | Adversarial unit | 0 duplicates after save | S | Disable repair flag |
| **M4** Block extraction | BlockExtractor, extend save_post sync | `Translation/*` | M2 | Integration sync_source | Orphan/stale without fuzzy match | M | Disable extraction flag |
| **M5** Render gate + block renderer | BlockRenderGate, BlockRenderer | `Translation/*` | M4 | Unit + integration + browser | 0 FP on spike replay corpus | L | Disable render flag |
| **M6** Migration / backfill CLI | `UuidBackfillCommand`, analyze | `Migration/*`, `Cli.php` | M2 | Integration dry-run | Batch idempotent | M | Stop CLI |
| **M7** Observability + flags UI | Settings, logger | `Settings`, `Observability` | M1 | Unit | Flags independent | S | All off |
| **M8** Integration + browser sign-off | Full matrix subset | — | M5 | Playwright CI subset | Phase 3 parity | M | — |
| **M9** Limited rollout | Cohort config | — | M7 | Production monitoring | Stage 5–7 metrics | S | Flags |
| **M10** General rollout | Documentation, ADR Accepted | — | PO sign-off | — | Production approved | S | §16 |

---

## 20. Open decisions register

| ID | Decision | Class | Recommendation |
|---|---|---|---|
| D-ADR | Promote ADR-0013 to Accepted | Architectural | After PO + architect review of this plan |
| D-ATTR | Attribute contract frozen | Architectural | `aimlBlockId`, RFC 4122 v4, key `b:<uuid>:content` — evidenced |
| D-SCOPE | UUID semantic scope | Architectural | Document-local via `(source_id, segment_key)` — evidenced |
| D-BLOCKS | Supported block allowlist | Product | Start core leaves from Phase 3 matrix; WC/RMath explicit allow |
| D-PATTERN | Synced pattern translation | Architectural | Exclude `core/block` refs; optional future `wp_block` object — evidenced |
| D-SAVE | Save hook architecture | Architectural | `wp_insert_post_data` primary — recommendation |
| D-DUP | Duplicate policy | Evidenced | First-wins — evidenced |
| D-LEGACY | Legacy `post_content` field rows | Product | Retain, do not render for block posts |
| D-FRONT | Frontend metadata policy | Product | Global `render_block` sanitizer + WC denylist |
| D-REV | UUID on autosaves/revisions | Operational | Inject on autosave; skip revision CPT rows by default |
| D-COHORT | Rollout cohort | Product | TBD with PO |
| D-ROLLBACK | Strip UUIDs on rollback | Operational | Prefer retain — recommendation |

---

## 21. Risk register

| Risk | L | I | Evidence | Mitigation | Owner | Blocking? |
|---|---|---|---|---|---|---|
| Third-party strips attr | M | H | Phase 3 | Allowlist + registration | Dev | No — suppress render |
| Transform drops UUID | H | M | Phase 3 | New UUID on inject; gate suppresses | Dev | No |
| Duplicate UUIDs | M | H | Phase 3 repair | First-wins on save | Dev | No |
| Cross-post copy | L | M | Phase 3 | Object-scoped rows | Dev | No |
| Synced pattern confusion | M | H | Phase 3 gate | Exclude `core/block` | Architect | **Yes** for pattern policy sign-off |
| Frontend leakage | M | M | WC block | Sanitizer §12 | Dev | No |
| Revision growth | M | M | Spike size est. | Batch policy, PO acceptance | Ops | No |
| Save recursion | L | H | Design | Pre-insert filter only | Dev | No |
| Concurrent saves | M | M | Phase 3 sim | Repair + gate | Dev | No |
| Migration failure mid-batch | M | M | — | Checkpoint + idempotence | Ops | No |
| Legacy row loss | L | M | M1 field keys | No fuzzy migration | PO | No |
| Rollback complexity | L | L | — | Flag-based | Ops | No |

---

## Documentation status (canonical)

| Item | State |
|---|---|
| Spike S5 | **Complete** |
| Selected strategy | **Strategy F** |
| Production planning | **Allowed** (this document) |
| Production implementation | **Not started** |
| Production readiness | **Not approved** |
| ADR-0013 | **Proposed** |

---

## References

- Spike report: [`docs/spikes/S5-gutenberg-segment-identity.md`](../spikes/S5-gutenberg-segment-identity.md)
- ADR: [`docs/adr/0013-gutenberg-segment-identity.md`](../adr/0013-gutenberg-segment-identity.md)
- Approved plan: [`APPROVED_PLAN_REV3.md`](APPROVED_PLAN_REV3.md) §5.2 (segment keys)
- Production code (M1 today): `src/Translation/Store.php`, `Extractor.php`, `Renderer.php`, `Plugin.php`
