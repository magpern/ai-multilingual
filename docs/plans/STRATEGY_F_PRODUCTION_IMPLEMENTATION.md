# Strategy F — Production Implementation Plan

**Status:** Planning + production implementation in progress (F1–F7 merged to `main`; F8 complete on `feature/f8-operations` @ `55ee542`).
**Spike evidence baseline:** `42237bd` (S5 complete; merged to `main` via PR #1).
**Production-plan baseline:** `ea5af19` (initial plan; amended by subsequent docs commits on `spike/s5`).
**Selected model:** Strategy F — `aimlBlockId` attribute, segment key grammar `b:<uuid>:<field>`.
**ADR-0013:** Proposed — not Accepted.
**Production implementation:** F1–F7 merged (`strategy-f-phase1-f1-f7`); F8 operational controls complete on `feature/f8-operations` (see [F8_CLI_VALIDATION_LOG.md](plans/F8_CLI_VALIDATION_LOG.md)).
**Production readiness:** Not approved (F12/F13 rollout + ADR acceptance pending). F9 closed by engineering acceptance.

This plan translates spike evidence into implementable Strategy F work packages (F1–F11). It does **not** supersede [`APPROVED_PLAN_REV3.md`](APPROVED_PLAN_REV3.md); it **specializes** the block-identity portion Rev 3 deferred to Spike S5 (§5.2 segment key grammar, `block:N` drift note).

**Evidence sources:** [`docs/spikes/S5-gutenberg-segment-identity.md`](../spikes/S5-gutenberg-segment-identity.md), [`docs/adr/0013-gutenberg-segment-identity.md`](../adr/0013-gutenberg-segment-identity.md), [`docs/spike-s5/IMPLEMENTATION_LOG.md`](../spike-s5/IMPLEMENTATION_LOG.md), spike reference code under `spike/s5/lib/Strategy/`.

---

## 1. Production architecture

### 1.1 Proposed components

| Component | Responsibility | Likely module |
|---|---|---|
| **Attribute contract** | `aimlBlockId` name, UUID v4 regex, segment key grammar | `src/Block/Contract.php` |
| **Attribute registration** | Declare `aimlBlockId` on adapter-approved block types (PHP + JS parity) | `src/Block/AttributeRegistrar.php`, `assets/block-editor.js` |
| **Block adapter registry** | Maps block names → `TranslatableBlockAdapter` implementations | `src/Block/AdapterRegistry.php` |
| **TranslatableBlockAdapter** | Per-block (or per-family) extract/apply/sanitize contract | `src/Block/Adapter/*.php` |
| **UUID generation / validation** | RFC 4122 v4; format and length checks | `src/Block/UuidGenerator.php`, `UuidValidator.php` |
| **Block tree walker** | Document-order traversal over `parse_blocks()` output | `src/Block/BlockTreeWalker.php` |
| **UUID injection + repair** | Assign missing UUIDs, first-wins duplicate repair, serialize only if changed | `src/Block/UuidInjector.php` |
| **Save-time persistence** | Hook orchestration, autosave/revision guards, recursion guard | `src/Block/SavePipeline.php` |
| **Block extraction** | Adapters → segment rows (`field_key=post_content`, `segment_key=b:…`) | `src/Translation/BlockExtractor.php` |
| **Segment-key builder** | `b:<uuid>:<field>` from block attrs + adapter field id | `src/Block/SegmentKey.php` |
| **Reconciliation** | Match rows by segment key; mark orphaned/stale; **no fuzzy rematch** | `Store::sync_source()` + block-aware extractor |
| **Render gate** | Suppress translation unless continuity provable | `src/Translation/BlockRenderGate.php` |
| **Block renderer (proof then general)** | Adapter-driven field overlay at render time | `src/Translation/BlockRenderer.php` |
| **Frontend sanitizer** | Block-specific / structured-boundary stripping of leaked attrs | `src/Block/FrontendSanitizer.php` |
| **Migration / backfill** | Batch inject UUIDs into canonical posts only | `src/Migration/UuidBackfillCommand.php` |
| **Feature flags** | Independent toggles with **dependency rules** (§15) | extend `src/Settings.php` |
| **Observability** | Structured logs + counters (no source/translated text) | `src/Observability/BlockIdentityLogger.php` |

Spike classes (`RealBlockWalker`, `UuidInjector`, `StrategyFRenderGate`, etc.) are **reference implementations** to port — not imported at runtime.

### 1.2 TranslatableBlockAdapter (conceptual contract)

Production must **not** assume every eligible block has exactly one translatable `innerHTML` field mapped to `b:<uuid>:content`. Instead, each supported block type (or family) implements an adapter.

**Illustrative interface (documentation only — not production code):**

```
TranslatableBlockAdapter
├── get_block_names(): string[]
├── is_translatable_instance( block ): bool
├── extract_fields( block ): TranslatableField[]
│     └── TranslatableField { field_id, source_text, text_format }
├── apply_translation( block, field_id, translated_text ): block
├── validate_block_structure( block ): ValidationResult
├── get_segment_key( uuid, field_id ): string   // b:<uuid>:<field_id>
└── get_frontend_sanitization_requirements(): SanitizationSpec
```

**Initial rollout:** adapters may expose only `field_id = content`. Future fields (`caption`, `label`, `title`, …) use the same grammar without schema change:

| Segment key example | Block / field |
|---|---|
| `b:<uuid>:content` | paragraph innerHTML, heading content, button label text |
| `b:<uuid>:caption` | (future) image/cover caption |
| `b:<uuid>:label` | (future) multi-attribute blocks |

**Unsupported adapter → source fallback always.** No row renders when adapter missing, field unsupported, or `validate_block_structure()` fails.

### 1.3 End-to-end data flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│ CANONICAL POST SAVE (not autosave, not revision CPT)                    │
├─────────────────────────────────────────────────────────────────────────┤
│ Editor / REST / WP-CLI / import → canonical post                        │
│   → wp_insert_post_data (primary hook)                                  │
│       → parse_blocks( post_content )                                    │
│       → per adapter: validate UUIDs, inject missing, repair duplicates  │
│       → serialize_blocks() only if changed                              │
│   → save_post (priority ≥ injection, canonical only)                    │
│       → BlockExtractor via adapters → segment map                       │
│       → Store::sync_source()  // orphan/stale; NO fuzzy match           │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│ AUTOSAVE (see §6.4)                                                     │
├─────────────────────────────────────────────────────────────────────────┤
│ Preserve existing UUIDs; optional inject-missing (flag)                 │
│ NEVER run sync_source against autosave object                           │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│ RENDER PATH (read-only overlay — invariant I1–I3)                       │
├─────────────────────────────────────────────────────────────────────────┤
│ the_content @ priority 1 (before do_blocks)                             │
│   → parse_blocks → for each block with adapter + allowlisted render:     │
│       for each extracted field:                                         │
│         segment_key = b:<uuid>:<field>                                    │
│         BlockRenderGate::resolve( row scoped to source_id, … )          │
│         adapter.apply_translation OR source fallback                      │
│   → serialize_blocks → core HTML pipeline                               │
└─────────────────────────────────────────────────────────────────────────┘
```

### 1.4 Operations that mutate `post_content`

| Operation | Mutates content? | Plan |
|---|---|---|
| Backfill / migration (canonical posts) | **Yes** | F7 CLI; revisions excluded by default |
| Save-time injection (canonical) | **Yes** when UUIDs added/repaired | F2 pipeline |
| Autosave (optional inject-missing) | **Maybe** | F2; never reconciles rows |
| Revision CPT rows | **No** injection/reconcile | snapshot only |
| Translation render | **No** | overlay only |
| Rollback of render/extract/inject | **No** | flags; UUIDs + registration retained |

---

## 2. Attribute registration

### 2.1 Recommendation: server-first, editor-mirrored

**Primary:** PHP `block_type_metadata` filter (Phase 3 spike evidence).
**Secondary:** Editor script mirroring the same attribute definition for blocks with client-side definitions.

Registration applies to **adapter-approved block names**, not “every block in the universe.”

### 2.2 Registration lifecycle (compatibility requirement)

| Phase | Registration flag | Rule |
|---|---|---|
| **Pre-rollout** (no production `aimlBlockId` in DB) | May be off for dev/staging tests | Safe — Gutenberg strips unregistered attrs on edit (Phase 3) |
| **After first production UUID** in any canonical `post_content` | **Compatibility requirement** | Registration **must remain enabled** even if rendering, extraction, injection, or repair are rolled back |
| **Production kill switch?** | **No** — not documented as a normal post-rollout rollback lever | Disabling registration after UUIDs exist causes silent attr stripping on edit → identity loss |

The `block_attr_registration_enabled` flag exists for **development and pre-rollout** only. After UUID rollout begins, operational runbooks treat registration as **always-on** unless executing a documented emergency content strip (out of scope for normal rollback).

### 2.3 Block categories (registration vs adapter)

| Category | Register attr? | Adapter / inject? | Render? |
|---|---|---|---|
| F1 proof blocks: `core/paragraph`, `core/heading`, `core/button` | Yes | Yes (F4+) | After F5 proof only |
| Other core static leaves | Yes when allowlisted | When adapter exists | Allowlist-driven |
| Containers | Optional register | No UUID on container | No |
| `core/block` (synced ref) | No | No | No |
| Dynamic blocks | No (default) | No | No — source fallback |
| WooCommerce / third-party | Per audit | Only with dedicated adapter + proof | Only after adapter proof |

---

## 3. Eligible-block policy

Eligibility is **two-layer**:

1. **Tree policy** (from spike): leaf vs container vs dynamic vs empty — determines UUID injection candidacy.
2. **Adapter policy**: block must have a registered `TranslatableBlockAdapter` to extract, reconcile, or render.

| Block shape | UUID inject? | Extract/reconcile? | Render? |
|---|---|---|---|
| Adapter-supported leaf (initial: paragraph, heading, button) | Yes | Yes | After F5 proof + F6 allowlist |
| Leaf without adapter | Maybe inject if tree-eligible | **No** | **No** — source fallback |
| Container | No | No | No |
| Empty leaf | No | No | No |
| Dynamic block | No | No | No |
| `core/block` reference | No | No | No |
| WC / plugin blocks | Only with adapter proof | Only with adapter | Only with adapter proof |

Initial **adapter allowlist** (F4–F6): `core/paragraph`, `core/heading`, `core/button` only. Expand only after adapter + renderer proof per block family.

---

## 4. UUID scope and ownership

### 4.1 Four distinct layers (do not conflate)

| Layer | Definition | Cross-post behavior |
|---|---|---|
| **1. UUID format uniqueness** | RFC 4122 v4 string; globally unique in practice | Same string may appear in multiple posts |
| **2. Document-local semantic identity** | `(source_type, source_id, segment_key)` where `segment_key = b:<uuid>:<field>` | Independent per post |
| **3. Translation-row ownership** | Row keyed by `(source_type, source_id, segment_hash, language_id)` | Rows **never** auto-transfer with UUID strings |
| **4. Translation continuity** | Whether a row's translation applies to live content | Requires same object + segment key + valid gate checks; **never** inferred from UUID string alone |

### 4.2 Definitive cross-post policy

**Default (binding for production planning):**

- UUID strings **may be preserved** during cross-post copy, XML import, or whole-post duplication plugins.
- Identity ownership remains **document-local** — scoped by `source_id`.
- Translation rows are scoped by `(source_type, source_id, language_id, segment_key)`.
- **Translation continuity is never transferred across objects automatically.**
- A copied post with unique UUID strings does **not** require regenerating every UUID.
- **Same-post** duplicate UUIDs **must** be repaired (first-wins).
- Translation rows must **never** be copied merely because UUID strings match.

**Optional product policy (not a safety requirement):** a duplication plugin *may* choose to regenerate all UUIDs for editorial clarity — document as optional, not mandatory.

### 4.3 Transfer scenarios

| Scenario | UUID in target content | Translation rows | Policy |
|---|---|---|---|
| Same-post duplicate (registered attr) | Copied → **repair** first-wins | Original row → first block; regenerated UUID → no row | Gate suppresses false render |
| Cross-post copy/paste / import | May preserve UUID strings | **None auto-copied**; target starts with no matching rows unless explicitly imported | Source fallback until translated |
| Whole-post duplication plugin | May preserve UUIDs | **Do not copy** `aiml_translations` rows by UUID match | New `source_id` scope |
| XML import (Phase 3) | Preserved in content | New post IDs; rows not auto-linked | Backfill + re-translate |
| Revision restore | Snapshot restores UUIDs | Reconcile on **canonical** object after restore (§6.4) | Normal stale/orphan rules |

### 4.4 Adversarial tests (required)

- Same UUID string in two different posts — each renders only its own rows
- Copied post without copied translation rows — source fallback only
- Copied translation rows with wrong `source_id` — gate `object_mismatch` suppresses
- Import preserving UUID strings — no cross-object row attachment
- Same-post duplication vs cross-post duplication — repair applies only same-post

---

## 5. Synced patterns

Unchanged from spike Phase 3 (binding):

| Entity | UUID / adapter? | Translate how? |
|---|---|---|
| `wp:block` reference in post | **No** | N/A |
| Synced pattern entity (`wp_block` CPT) | Out of post save pipeline | Optional future separate object |
| Non-synced materialized copy | Yes — post-local adapters | Normal F pipeline |
| Detached copy | Yes — post-local | Inject on first canonical save |

---

## 6. Save-time integration

### 6.1 Recommended architecture

**Primary:** `wp_insert_post_data` for canonical content mutation.
**Guard:** detect autosave and revision objects and branch (§6.4).

### 6.2 Canonical save pipeline

1. Guard: flags, capability, **not** autosave/revision CPT, recursion static.
2. Skip Elementor bodies; skip non-block content.
3. `parse_blocks` → adapter-aware validate/inject/repair.
4. `serialize_blocks` only if changed.
5. After DB write: `save_post` on **canonical** post → extract → `sync_source`.

### 6.3 Entry points

| Entry | Injection | Reconciliation |
|---|---|---|
| Gutenberg canonical save | Yes (F2) | Yes (F4) |
| REST canonical save | Yes | Yes |
| REST autosave endpoint | Preserve; optional inject-missing | **Never** |
| WP-CLI canonical update | Yes | Yes |
| Import / backfill | Yes (canonical) | On next canonical save |
| Revision CPT | **No** | **No** |

### 6.4 Autosave and revision semantics (resolved)

| Path | UUID handling | Reconciliation |
|---|---|---|
| **Canonical post save** | Validate, inject, repair, persist | **Yes** — `sync_source` on canonical object |
| **Autosave** (`wp_is_post_autosave`, REST `/autosaves`) | **Preserve** existing UUIDs; optionally inject missing UUIDs if `block_uuid_autosave_inject_enabled` (editor recovery) | **Never** — autosave is not a translation source |
| **Revision creation** (`wp_is_post_revision`) | Store WordPress snapshot as-is; **no** inject/repair/extract | **Never** |
| **Revision restore** | User action restores content → flows through **canonical save path** on the parent post | **Yes** — after restore, validate/inject/repair on canonical object, then `sync_source`; stale/orphan rules apply normally |
| **Backfill** | Canonical published/draft posts only by default | Deferred until next canonical save unless `--reconcile` explicitly added later |

**REST autosave:** same rules as core autosave — preserve UUIDs; optional inject-missing; no `sync_source`.

**Tests required:** canonical save, autosave preserve, autosave inject-missing optional, revision create skips pipeline, revision restore reconciles canonical parent, backfill skips revision posts.

---

## 7. Duplicate repair

**Policy:** first-wins (spike-evidenced). Applies to **same-post** duplicate UUIDs only.

Cross-post UUID reuse is **not** a duplicate-repair case — objects are scoped independently.

Regenerated UUIDs → render gate `regenerated_uuid` / `unknown_uuid` → source fallback. **No fuzzy matching.**

---

## 8. Translation data model

### 8.1 Current schema (repository evidence)

From `src/Database/Schema.php` — **no schema change required** for Strategy F MVP.

- `segment_key` VARCHAR(191) — supports `b:<uuid>:<field>` (~50 chars for content field)
- `segment_identity` UNIQUE `(source_type, source_id, segment_hash, language_id)`
- `segment_hash = sha1(field_key . "\x1f" . segment_key)`

Multiple fields per block (future) → multiple rows with same UUID, different `<field>` suffix. No collision if field ids differ.

### 8.2 Schema impact

| Change | Required? |
|---|---|
| New tables | **No** |
| New columns | **No** for MVP |
| Index changes | **No** |
| Optional diagnostic column | **Defer** — not proven necessary |

### 8.3 Reconciliation

Adapter extraction produces map keyed by `b:<uuid>:<field>`. `sync_source`:

- Missing key → `status=ignored`, `error_code=orphaned`
- Key present, hash differs → `is_stale=1`
- **No fuzzy matching, no path rematch, no UUID-only row lookup across objects**

---

## 9. Migration and backfill

- **Canonical posts only** by default (not revision/autosave CPT rows).
- Discovery SQL documented in prior revision (unchanged).
- Idempotent inject; checkpoint cursor; dry-run first.
- After backfill: registration compatibility requirement applies (§2.2).

---

## 10. Existing translation migration

Legacy `segment_key = post_content` field-level rows: retain, do not render for block posts once F6 enabled. **No fuzzy rematch.** Unproven continuity → source fallback mandatory.

---

## 11. Render gate

Port spike gate + add adapter/field checks.

| Check | Suppression reason |
|---|---|
| Feature flag off / unsafe flag combo | `feature_disabled` |
| No adapter for block | `unsupported_block` |
| Unsupported field | `unsupported_field` |
| Adapter structure validation failed | `invalid_block_structure` |
| UUID missing / malformed / duplicate / regenerated | `missing_uuid`, etc. |
| No row / orphaned / object mismatch | `unknown_uuid`, `orphaned_row`, `object_mismatch` |
| Block type mismatch | `block_type_mismatch` |
| Stale hash | `stale_hash` |
| Empty translation | `empty_translation` |

**Invariant:** uncertainty → source fallback; `rendered_false_positive == 0`.

**No row may render when:** adapter unsupported, field unsupported, structure invalid, wrong object scope, stale hash, duplicated/malformed UUID, or unsafe flag combination (§15).

---

## 12. Frontend metadata exposure

Phase 3: WooCommerce `customer-account` may leak `data-aiml-block-id`.

**Rules:**

- **Do not** use broad regex replacement over arbitrary HTML strings.
- Sanitize at **trusted structured boundaries**: `render_block` filter with block-name-specific logic, adapter-declared `SanitizationSpec`, or DOM-aware removal of known leakage patterns for proven blocks.
- Serialized block-comment JSON **retains** `aimlBlockId`; only public HTML output is sanitized.
- Unsupported blocks: source fallback; sanitizer still runs if block renders at all.

---

## 13. Concurrency

Last-write-wins (WordPress core). Repair on each canonical save maintains gate safety. Does not solve collaborative editing.

---

## 14. Observability

Structured events (no body text): `uuid_created`, `uuid_preserved`, `uuid_duplicate_detected`, `uuid_duplicate_repaired`, `adapter_missing`, `block_render_gate_denied`, `block_frontend_render_complete`, `block_migration_post_complete`, `flag_combo_rejected`, etc. Full inventory: [STRATEGY_F_F8_OPERATIONS_AND_OBSERVABILITY.md](STRATEGY_F_F8_OPERATIONS_AND_OBSERVABILITY.md) §3.

---

## 15. Feature flags, dependencies, and rollout

### 15.1 Flags

| Flag | Purpose | Post-rollout kill switch? |
|---|---|---|
| `block_attr_registration_enabled` | Register `aimlBlockId` | **No** (after UUIDs exist) — dev/pre-rollout only |
| `block_uuid_analysis_enabled` | Parse/report only | Yes |
| `block_uuid_injection_enabled` | Mutate canonical content | Yes (step 3 rollback) |
| `block_uuid_repair_enabled` | Duplicate repair | Yes (subordinate to injection) |
| `block_uuid_autosave_inject_enabled` | Inject missing on autosave | Yes |
| `block_extraction_enabled` | `sync_source` segments | Yes (step 2 rollback) |
| `block_frontend_rendering_enabled` | Overlay rendering (F6) | Yes (step 1 rollback) |
| `block_renderer_proof_mode` | F5 narrow proof logging | Dev/staging |
| `block_migration_enabled` | Backfill writes | Yes (step 4 rollback) |
| `block_diagnostics_enabled` | Verbose logs | Yes |

### 15.2 Valid dependencies

| Flag | Requires |
|---|---|
| `block_uuid_injection_enabled` | registration **on** |
| `block_uuid_repair_enabled` | injection **on** |
| `block_extraction_enabled` | injection **on**, registration **on** |
| `block_frontend_rendering_enabled` | extraction **on**, injection **on**, registration **on** |

### 15.3 Prohibited combinations (must fail closed)

| Combination | Why unsafe |
|---|---|
| Render **on**, extraction **off** | Rows not updated; stale/wrong render risk |
| Render **on**, registration **off** (post-rollout) | Attr stripping → silent identity drift |
| Extraction **on**, injection **off** (post-rollout) | Segment keys drift from content |
| Render **on** without adapter allowlist entry | Unsupported block render path |

Runtime must reject prohibited combos (settings save + runtime guard).

### 15.4 Rollback order (production)

1. **Disable block rendering** (`block_frontend_rendering_enabled`)
2. **Disable block extraction/reconciliation** (`block_extraction_enabled`)
3. **Disable UUID injection and duplicate repair** (`block_uuid_injection_enabled`, repair sub-flag)
4. **Stop migration/backfill** (`block_migration_enabled`)
5. **Retain attribute registration** (compatibility requirement — do not disable post-rollout)
6. **Leave existing UUID metadata in `post_content`**

### 15.5 Rollout stages

| Stage | Flags | Notes |
|---|---|---|
| 1 Deploy | all off | Code present |
| 2 Observation | analysis on | No mutation |
| 3 Dry-run backfill | analysis + migration dry-run | Staging |
| 4 Registration + inject internal | registration on, injection on, render off | Compatibility clock starts |
| 5 Extraction cohort | + extraction | sync_source |
| 6 F5 renderer proof | proof mode on staging | **Gate before F6** |
| 7 Render pilot | render on allowlist | paragraph/heading/button |
| 8 General rollout | expand adapters deliberately | PO sign-off |

---

## 16. Rollback (summary)

See §15.4 for ordered rollback. Translation rows never auto-deleted. Schema unchanged. UUID strip commands out of scope for normal ops.

---

## 17. Security

UUIDs are identifiers, not secrets. Validate format/length. Object-scope rows on render. No body text in logs. Migration CLI capability-gated.

---

## 18. Test strategy

### Unit
- Segment key grammar `b:<uuid>:<field>`
- Adapters (extract/apply/validate) for paragraph, heading, button
- Inject/repair/idempotence
- Render gate including adapter/field/structure suppressions
- Flag dependency validator

### Integration
- Canonical save vs autosave vs revision paths (§6.4)
- Cross-post UUID preservation without row transfer
- Import with preserved UUIDs
- Revision restore → reconcile canonical parent

### Renderer proof (F5 — gate before F6)
Narrow scope: `core/paragraph`, `core/heading`, `core/button` only.

Must validate:
- `parse_blocks` / `serialize_blocks` round-trip
- Nested placement; `innerContent` null placeholders; multi-fragment innerContent
- Block attributes consistent with markup
- No editor invalid-block warning after save
- Frontend output correct; escaped content safe
- Source fallback unchanged when gate suppresses
- Translated content does not leak to adjacent blocks
- `rendered_false_positive == 0`

Dynamic blocks and WooCommerce: **no generic renderer** until block-specific adapter proof exists.

### Browser
- Reuse spike harness patterns for proof blocks; expand after F6

### Adversarial
- Same UUID two posts; wrong-source_id row; same-post vs cross-post duplicate; tampered UUID; adapter bypass attempts

---

## 19. Implementation milestones (F1–F13)

Strategy F milestones use **F-prefix** to avoid collision with project Milestone 1/2/3.

| Milestone | Scope | Depends | Acceptance / rollback |
|---|---|---|---|
| **F1** Attribute contract + registration | Contract, AttributeRegistrar, editor mirror, flag stub | — | Attr survives edit on proof blocks; registration lifecycle documented | Pre-rollout: flag off OK |
| **F2** UUID persistence pipeline | UuidInjector, SavePipeline, canonical vs autosave branching | F1 | Idempotent inject; autosave rules §6.4 | Disable injection flag |
| **F3** Duplicate repair hardening | Logging, tamper paths | F2 | 0 same-post duplicates after save | Disable repair sub-flag |
| **F4** Block adapters + extraction | `TranslatableBlockAdapter`, paragraph/heading/button, BlockExtractor, sync | F2 | Extract `b:<uuid>:content` only; no fuzzy match | Disable extraction |
| **F5** Renderer proof | Narrow BlockRenderer proof for 3 blocks; **no general render flag** | F4 | **Formal gate:** all proof criteria §18 pass; else F6 blocked | Proof mode off |
| **F6** Render gate + allowlisted rendering | BlockRenderGate, render flag for proof allowlist only | **F5 accepted** | 0 FP; unsupported → source | Disable render (rollback step 1) |
| **F7** Migration and backfill | analyze + backfill CLI; canonical only | F2 | Idempotent batch; registration compat | Stop migration (step 4) |
| **F8** Observability + feature controls | See [STRATEGY_F_F8_OPERATIONS_AND_OBSERVABILITY.md](STRATEGY_F_F8_OPERATIONS_AND_OBSERVABILITY.md) — Settings UI, `wp aiml block status`, metrics aggregator, runbooks | F1–F7 | Prohibited combos rejected; health check green | Disable diagnostics; render kill switch §15.4 step 1 |
| **F9** Integration + browser sign-off | See [STRATEGY_F_F9_BROWSER_ACCEPTANCE.md](STRATEGY_F_F9_BROWSER_ACCEPTANCE.md) — **Closed: engineering acceptance** @ `91785cd`; [F9_BROWSER_VALIDATION_LOG.md](F9_BROWSER_VALIDATION_LOG.md) | F6, F8 | `rendered_false_positive == 0`; no known product defect in supported scope; formal 35/35 Tier 3 waived (TID-1) | — |
| **F10** Translator Workspace MVP | See [STRATEGY_F_F10_TRANSLATOR_WORKSPACE.md](STRATEGY_F_F10_TRANSLATOR_WORKSPACE.md) — **Complete**; [F10_TRANSLATOR_VALIDATION_LOG.md](F10_TRANSLATOR_VALIDATION_LOG.md) PASS | F9 | AC-1–AC-13 in F10 plan | — |
| **F11** Translation Memory & AI Assistance | See [STRATEGY_F_F11_TRANSLATION_MEMORY_AND_AI_ASSISTANCE.md](STRATEGY_F_F11_TRANSLATION_MEMORY_AND_AI_ASSISTANCE.md) — TM, provider-neutral AI, QA (planned; not started) | F10 | AC in F11 plan | — |
| **F12** Limited rollout | Cohort flags, stage metrics (former master-plan F10) | F8, F11 | Stage metrics | §15.4 rollback |
| **F13** General rollout + ADR acceptance | Expand adapters; PO sign-off (former master-plan F11) | F12 + ADR checklist | Production approved | §15.4 |

**Renderer architecture acceptance:** F6 must not begin until F5 proof is explicitly accepted and recorded.

---

## 20. Open decisions register

| ID | Decision | Class | Status |
|---|---|---|---|
| D-ADR | Promote ADR-0013 | Architectural | Pending human checklist (ADR) |
| D-ATTR | Contract: `aimlBlockId`, v4, `b:<uuid>:<field>` | Architectural | Evidenced — pending approval |
| D-REG-LIFE | Registration compatibility after UUID rollout | Architectural | **Recommendation in §2.2** — pending approval |
| D-ADAPTER | Initial adapter allowlist (paragraph, heading, button) | Product | Recommendation — pending approval |
| D-PATTERN | Exclude `core/block` refs | Architectural | Evidenced — pending approval |
| D-SAVE | Canonical vs autosave vs revision (§6.4) | Architectural | **Resolved in plan** — pending approval |
| D-CROSSPOST | Cross-post UUID policy (§4.2) | Architectural | **Resolved in plan** — pending approval |
| D-RENDER-PROOF | F5 gate before F6 | Architectural | **Resolved in plan** — pending proof execution |
| D-FRONT | Structured sanitizer policy (§12) | Product | Pending approval |
| D-COHORT | Rollout cohort | Product | TBD |
| D-ROLLBACK | Ordered rollback §15.4 | Operational | Pending approval |

---

## 21. Risk register

| Risk | L | I | Evidence | Mitigation | Blocking? |
|---|---|---|---|---|---|
| Registration disabled post-rollout | L | **H** | Phase 3 strip-on-edit | Compatibility requirement §2.2; rollback order | **Yes** — ops sign-off |
| Unsupported block rendered | M | H | — | Adapter allowlist; gate | No |
| Generic renderer assumption | H | H | — | F5 proof gate | **Yes** — before F6 |
| Cross-post row attachment | L | H | — | Object-scoped rows §4.2 | No |
| Same-post duplicate UUID | M | H | Phase 3 | First-wins repair | No |
| Frontend regex sanitizer | M | M | WC leak | Structured sanitizer §12 | No |
| Unsafe flag combination | M | H | — | Dependency validator §15.3 | No |
| Synced pattern confusion | M | H | Phase 3 | Exclude `core/block` | Pattern sign-off |
| Autosave reconcile drift | M | M | — | Never sync autosave §6.4 | No |
| Third-party attr strip | M | H | Phase 3 | Registration + allowlist | No |

---

## Documentation status (canonical)

| Item | State |
|---|---|
| Spike S5 | **Complete** (evidence baseline `42237bd`) |
| Selected strategy | **Strategy F** |
| Production planning | **Allowed** (this document) |
| Production implementation | **F1–F9 merged** on `main`; **F10 complete** — merge pending |
| Production readiness | **Not approved** (F12/F13 rollout + ADR acceptance pending) |
| Next milestone | **F11** Translation Memory & AI Assistance (planned; not started) |
| ADR-0013 | **Proposed** |

---

## References

- Spike report: [`docs/spikes/S5-gutenberg-segment-identity.md`](../spikes/S5-gutenberg-segment-identity.md)
- ADR: [`docs/adr/0013-gutenberg-segment-identity.md`](../adr/0013-gutenberg-segment-identity.md)
- F8 operations: [STRATEGY_F_F8_OPERATIONS_AND_OBSERVABILITY.md](STRATEGY_F_F8_OPERATIONS_AND_OBSERVABILITY.md)
- F9 browser acceptance: [STRATEGY_F_F9_BROWSER_ACCEPTANCE.md](STRATEGY_F_F9_BROWSER_ACCEPTANCE.md) — **Closed: engineering acceptance**
- F9 validation log: [F9_BROWSER_VALIDATION_LOG.md](plans/F9_BROWSER_VALIDATION_LOG.md)
- F11 Translation Memory & AI: [STRATEGY_F_F11_TRANSLATION_MEMORY_AND_AI_ASSISTANCE.md](plans/STRATEGY_F_F11_TRANSLATION_MEMORY_AND_AI_ASSISTANCE.md)
- F10 validation log: [F10_TRANSLATOR_VALIDATION_LOG.md](plans/F10_TRANSLATOR_VALIDATION_LOG.md)
- F8 live validation: [F8_CLI_VALIDATION_LOG.md](plans/F8_CLI_VALIDATION_LOG.md)
- Approved plan: [`APPROVED_PLAN_REV3.md`](APPROVED_PLAN_REV3.md) §5.2
- Production code today (Milestone 1): `src/Translation/Store.php`, `Extractor.php`, `Renderer.php`, `Plugin.php`
