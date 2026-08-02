# F9 — Browser Acceptance Plan

**Status:** Canonical browser acceptance plan — F9 harness implemented on `feature/f9-browser-acceptance`; Tier 2 stabilization in progress @ `46c3934`  
**Depends on:** F1–F8 merged to `main` @ `795cb1f`; tag `strategy-f-f8-operations-complete`  
**ADR-0013:** Proposed — F9 informs acceptance; does not auto-promote ADR  
**Canonical doc:** This file. Master plan cross-ref: [STRATEGY_F_PRODUCTION_IMPLEMENTATION.md](STRATEGY_F_PRODUCTION_IMPLEMENTATION.md) §18, §19.  
**Operational baseline:** [F8_CLI_VALIDATION_LOG.md](F8_CLI_VALIDATION_LOG.md) (PASS @ `55ee542`)  
**Spike evidence:** [S5-gutenberg-segment-identity.md](../spikes/S5-gutenberg-segment-identity.md), `spike/s5/browser-validation/`, `spike/s5/corpus/browser-validation/`

---

## Evidence taxonomy (read first)

F9 is **not** Spike S5 repeated. It validates the **production plugin** (`src/`) already merged into `main`.

| Layer | What it proved | What it did **not** prove |
|---|---|---|
| **Spike S5 (Phase 2–3)** | Gutenberg behavior of `aimlBlockId`; registered vs unregistered attribute survival; duplicate repair on browser fixtures; pattern/synced-pattern gates; 59 replay fixtures @ `rendered_false_positive == 0`; concurrent-edit adversarial simulation | Production `AttributeRegistrar`, `SavePipeline`, `BlockFrontendRenderer`, production flags, production Store sync on save, language-prefix routing on live site |
| **F8 (WP6 CLI + HTTP smoke)** | `wp aiml block status`, migration dry-run/live idempotence, kill switch HTTP toggle, settings audit hooks, flags default off | Full Gutenberg editor matrix, multi-browser matrix, translation workflow in editor, ADR human checklist |
| **F9 (this plan)** | Production implementation behaves correctly in real Gutenberg + frontend on `dev.biopentra.eu` for the **adapter allowlist** and **supported post types** | New architecture, new block adapters, Elementor-primary content, production rollout |

**Reuse spike assets** (`spike/s5/browser-validation/helpers/editor.ts`, fixture HTML, corpus replay patterns) as **reference harnesses** — F9 runs against production registration in [`src/Block/AttributeRegistrar.php`](../../src/Block/AttributeRegistrar.php) and [`assets/block-editor.js`](../../assets/block-editor.js), not the spike-only mu-plugin.

---

## 1. Purpose and scope

### 1.1 Purpose

F9 is an **acceptance milestone only**. Its purpose is to demonstrate that Strategy F production code (F1–F8):

1. Preserves `aimlBlockId` through real Gutenberg editing when production attribute registration is enabled.
2. Injects, repairs, extracts, and reconciles block segments on canonical saves without false-positive frontend rendering.
3. Renders reviewed block translations on the public site only when all gates pass.
4. Respects feature-flag dependency rules, kill switches, and rollback semantics documented in F8.
5. Supplies evidence sufficient for ADR-0013 human approval checklist review (ADR may remain Proposed until explicitly accepted).

### 1.2 In scope

| Area | F9 validates |
|---|---|
| Gutenberg block editor | Post-local blocks on adapter allowlist |
| Canonical save pipeline | UUID inject/repair, extraction, `sync_source` |
| Frontend overlay | Gated `the_content` rendering for allowlisted blocks |
| Language routing | URL-prefix language switching (`/sv/…`) |
| WP-CLI migration | Idempotent backfill on acceptance cohort (already CLI-validated in F8; browser cohort re-confirms) |
| Feature flags | Four production flags + dependency UI |
| Observability | Structured events during browser sessions (no body text in logs) |

### 1.3 Out of scope for F9 execution

See §23. F9 does **not** implement new production features, new adapters, schema changes, or production rollout.

### 1.4 Target environment

Primary acceptance host: **`https://dev.biopentra.eu`** (bind-mounted plugin at `/opt/biopentra/dev/ai-multilingual`).

F9 artifacts are recorded in `docs/plans/F9_BROWSER_VALIDATION_LOG.md` (created during F9 execution — not part of this plan).

---

## 2. Acceptance criteria

F9 **PASS** requires **all** of the following:

| ID | Criterion | Evidence type |
|---|---|---|
| AC-1 | `rendered_false_positive == 0` on all F9 browser-derived replay fixtures | Automated replay + manual review |
| AC-2 | UUID persistence matrix (§9) **PASS** for every **Required** cell on adapter allowlist blocks | Browser export + `post_content` analysis |
| AC-3 | Frontend rendering shows translated allowlisted block text in non-default language; kill switch restores source within one request | HTTP + optional Playwright |
| AC-4 | Translation rows remain scoped to `(source_id, segment_key)` — no cross-post leakage | Store inspection + negative test |
| AC-5 | Language prefix routing serves correct language without cookie dependency | HTTP matrix |
| AC-6 | Migration idempotent on acceptance cohort pages | WP-CLI JSON artifacts |
| AC-7 | Invalid flag combinations rejected; valid chain persists; kill switch verified in browser admin | Admin UI + hook capture |
| AC-8 | Failure scenarios (§15) contain correctly — source fallback, no wrong translation | Browser + HTTP |
| AC-9 | PHPUnit + PHPCS green on F9 branch at acceptance commit | CI / local Docker gates |
| AC-10 | F9 validation log records PASS with commit hash, browser matrix, and operator sign-off | `F9_BROWSER_VALIDATION_LOG.md` |
| AC-11 | No blocking regressions vs F8 CLI acceptance | Regression checklist §19 |

**Hard stop:** Any observed wrong-language render on a supported block → **FAIL** until root-caused and re-validated.

---

## 3. Browser environments

F9 browser matrix extends spike harness (Chromium-only in S5) to production-relevant coverage.

### 3.1 Engines (required)

| Engine | Playwright project | Minimum version | Role |
|---|---|---|---|
| **Chromium** | `chromium` | Playwright **1.51.0** (pin; match spike) | Primary — full matrix |
| **Firefox** | `firefox` | Playwright **1.51.0** | Secondary — Tier 1 operations on proof blocks |
| **WebKit** | `webkit` | Playwright **1.51.0** | Secondary — Tier 1 operations on proof blocks |

Spike reference: `spike/s5/browser-validation/playwright.config.ts` (Chromium default). F9 harness adds `projects` for Firefox/WebKit without changing production code.

### 3.2 Viewports (required)

| Profile | Width × height | Tests |
|---|---|---|
| **Desktop** | 1280 × 720 | Full UUID matrix on proof blocks |
| **Mobile** | 390 × 844 (iPhone 12 class) | Tier 1 subset: create, edit, save, duplicate, undo, frontend spot-check |

Mobile validates editor-canvas iframe interaction under narrow layout (spike harness uses `iframe[name="editor-canvas"]` — must remain stable).

### 3.3 Authentication and networking

- **Auth:** WP-CLI generated cookie jar (spike pattern: `write-auth-cookies-json.sh`) — avoids Cloudflare login form in headless Docker.
- **Base URL:** `WP_BASE_URL=https://dev.biopentra.eu`
- **Runner:** Docker `mcr.microsoft.com/playwright:v1.51.0-jammy` (same as S5) on dev VPS or CI with egress to dev site only.
- **No production URLs.**

---

## 4. Supported WordPress versions

| Version | Support level | Notes |
|---|---|---|
| **7.0.x** | **Primary acceptance** | dev.biopentra.eu current (`7.0.2` at F8 validation) |
| **6.7.x – 6.8.x** | Best-effort smoke | Only if dev stack downgrades for regression; not blocking for F9 PASS on 7.0.2 |
| **< 6.5** | Unsupported | Plugin header `Requires at least: 6.5` |

F9 PASS is recorded against the **exact** WordPress version on the acceptance host at execution time.

---

## 5. Supported PHP versions

| Version | Support level | Notes |
|---|---|---|
| **8.3.x** | **Primary acceptance** | dev WordPress container (`8.3.32` at F8 validation) |
| **8.1 – 8.2** | Supported by plugin (`Requires PHP: 8.1`) | PHPUnit platform pin `8.1.99`; no separate browser run required unless host changes |
| **8.4.x** | Informational | Spike S5 host PHP 8.4 — not required for F9 if dev remains 8.3 |

Browser acceptance runs on the **dev VPS PHP version**, not every supported minor.

---

## 6. Supported block types (core)

Production adapter allowlist ([`BlockRegistry::SUPPORTED_BLOCKS`](../../src/Block/BlockRegistry.php)):

| Block | Adapter | F9 tier | Spike S5 evidence |
|---|---|---|---|
| `core/paragraph` | ParagraphAdapter | **Required — full matrix** | Extensive |
| `core/heading` | HeadingAdapter | **Required — full matrix** | Extensive |
| `core/button` | ButtonAdapter | **Required — full matrix** | Tested (buttons) |

**Field:** `content` only → segment key `b:<uuid>:content`.

### 6.1 Core blocks tested in spike but **not** in production allowlist

Spike S5 exercised many core blocks (list, image, group, columns, quote, table, cover, etc.) for **attribute preservation physics** only. F9 does **not** require full matrix on these unless an adapter exists in `src/`. They remain **informational regression spot-checks** (optional): confirm no UUID injection on unsupported blocks and gate denies render.

### 6.2 Dynamic / excluded core blocks

Production [`BlockRegistry::DYNAMIC_BLOCK_NAMES`](../../src/Block/BlockRegistry.php): `core/block` (synced ref), `core/latest-posts`, `core/query`, etc. — **must not** receive UUID injection; F9 confirms suppression.

---

## 7. Supported third-party blocks

| Block / plugin | Production adapter | F9 requirement | Spike S5 finding |
|---|---|---|---|
| **WooCommerce** `woocommerce/customer-account` | **None** | **Out of scope** — confirm no false render if present on page | UUID survives no-op; strips on edit; **leaked `data-aiml-block-id` on frontend** when tagged — production must not inject without adapter + sanitizer proof |
| **Rank Math** TOC/FAQ blocks | **None** | **Out of scope** — same suppression check | Same stripping behavior as core |
| **Elementor** body content | **None** (gate: `elementor_body`) | **Document only** — pages with Elementor primary body skip block pipeline | Not characterized in spike |

**F9 rule:** Third-party blocks without a production adapter must **never** receive translated overlay. Validation = negative tests on pages mixing proof blocks + third-party blocks.

No new third-party adapters in F9.

---

## 8. Supported editors

| Editor | Support | F9 validation |
|---|---|---|
| **Block editor (Gutenberg)** | **Primary** | Full UUID matrix §9 |
| **Classic editor** | Unsupported for block identity | Confirm no block pipeline on classic posts (non-block content gate) |
| **Site Editor (FSE templates)** | Out of scope | Not in `RenderGateContext::SUPPORTED_POST_TYPES` |
| **Elementor editor** | Out of scope | Elementor-authored pages excluded by extractor/gate |
| **Widgets / Customizer** | Out of scope | — |

All browser tests use **draft or published pages** edited in the block editor (`post`, `page` only).

---

## 9. UUID persistence matrix

Each cell records acceptance for **production registration enabled** (`block_attr_registration_enabled=true`) on adapter allowlist blocks.

**Legend:**

| Symbol | Meaning |
|---|---|
| **S5** | Proven in Spike S5 with registered attribute (spike mu-plugin or registration test) |
| **F8** | Proven in F8 CLI/HTTP (operational, not full editor matrix) |
| **F9-R** | **Required** — must pass in F9 on production plugin |
| **F9-O** | Optional / informational |
| **N/A** | Not applicable (pattern entity, synced ref, unsupported block) |
| **EXP** | Expected production behavior per architecture (document, spot-check) |

### 9.1 Matrix

| Operation | UUID expectation (registered, post-local allowlist block) | S5 | F8 | F9 |
|---|---|---|---|---|
| **Create** (insert new paragraph/heading/button) | Inject on first canonical save | EXP | — | **F9-R** |
| **Edit** (text change) | UUID **preserved**; translation may go stale | S5 | — | **F9-R** |
| **Duplicate** | Duplicate copies UUID → repair on save (first-wins) | S5 | — | **F9-R** |
| **Copy** | Source UUID preserved; pasted block gets new UUID on save | S5 | — | **F9-R** |
| **Paste** | Pasted block injects new UUID; no cross-post row transfer | S5 | — | **F9-R** |
| **Cut + paste** | Same as copy/paste | S5 | EXP | **F9-R** |
| **Undo** | Restores prior UUID state | S5 | — | **F9-R** |
| **Redo** | Mirrors undo stack | S5 | — | **F9-R** |
| **Transform** (e.g. paragraph → heading) | Destination block type must remain allowlisted; UUID policy per adapter validation | S5 | — | **F9-R** (allowlisted transforms only) |
| **Move** (drag within document) | UUID **preserved** | S5 | — | **F9-R** |
| **Drag** (reorder) | UUID **preserved** | S5 | — | **F9-R** |
| **Group** (wrap in Group — container) | Container not tagged; inner allowlist blocks preserve UUID | S5 | — | **F9-O** (inner blocks **F9-R**) |
| **Ungroup** | Inner allowlist blocks preserve UUID | S5 | — | **F9-O** |
| **Reusable / synced patterns** | Pattern entity **not tagged**; `core/block` ref **N/A** | S5 | — | **F9-R** (negative: no UUID in ref post) |
| **Synced patterns** | Live ref — no local content to tag | S5 | — | **F9-R** (confirm out-of-scope) |
| **Revisions** (manual) | Snapshot preserves UUID | S5 | — | **F9-R** |
| **Autosave** | UUID preserved; **no** `sync_source` on autosave | S5 | — | **F9-R** |
| **Restore revision** | Restored content UUID matches snapshot | S5 (inferred) | — | **F9-R** |
| **REST read/write** | UUID round-trips | S5 | — | **F9-O** |
| **Export/import (WXR)** | UUID survives | S5 | — | **F9-O** |
| **Preview** | Same as published render path | S5 (inferred) | — | **F9-O** |
| **Delete + undo** | UUID preserved | S5 | — | **F9-R** |
| **No-op save** (×3 cycles) | Byte-stable | S5 | — | **F9-R** |

### 9.2 Verification method (F9)

For each **F9-R** row:

1. WP-CLI seed draft page with fixture or editor create.
2. Enable flags: registration + injection (+ extraction for reconcile tests).
3. Playwright perform operation inside editor canvas iframe.
4. Canonical save; export `post_content` via WP-CLI.
5. Assert `aimlBlockId` with [`UuidValidator`](../../src/Block/UuidValidator.php) + document-order uniqueness.
6. Run production replay: inject → extract → reconcile → render gate (PHPUnit integration pattern or F9 replay script referencing production classes).
7. Assert `rendered_false_positive == 0`.

Corpus output: `docs/plans/F9_BROWSER_VALIDATION_LOG.md` + archived JSON under `docs/plans/f9-artifacts/` (created at execution).

---

## 10. Frontend rendering validation

Production path: [`BlockRenderGate`](../../src/Translation/BlockRenderGate.php) → [`BlockTranslationLookup`](../../src/Translation/BlockTranslationLookup.php) → [`BlockFrontendRenderer`](../../src/Translation/BlockFrontendRenderer.php).

| Test | Setup | Expected | Prior evidence |
|---|---|---|---|
| FR-1 | All four flags on; reviewed block translation exists | Public `/sv/{slug}/` shows translated allowlist block text | F8 HTTP PASS (single paragraph) |
| FR-2 | `block_frontend_rendering_enabled=false` | Source language content on next request | F8 kill switch PASS |
| FR-3 | Re-enable frontend flag | Translation restored without re-migration | F8 PASS |
| FR-4 | Missing translation row | Source fallback | PHPUnit integration |
| FR-5 | Stale translation (`is_stale=1`) | Source fallback | PHPUnit integration |
| FR-6 | Sanitizer rejection | Source fallback; `block_translation_rejected` event | PHPUnit integration |
| FR-7 | Unsupported block on same page | Allowlisted blocks render; unsupported source only | **F9-R** |
| FR-8 | Admin / editor / REST / preview requests | Gate deny — no overlay | PHPUnit + **F9-O** preview |
| FR-9 | Elementor-body page | Gate deny entire body | **F9-O** spot-check |
| FR-10 | `elapsed_ms` present in render complete events | Non-negative integer in hook payload | F4 implemented |

F9 extends F8 single-paragraph check to **all three allowlist block types** on one acceptance page each.

---

## 11. Translation validation

| Test | Expected | F9 |
|---|---|---|
| TR-1 | Saving reviewed translation in Store binds `segment_key=b:<uuid>:content` | **F9-R** |
| TR-2 | Text edit changes source hash → row marked stale; frontend suppresses until re-translated | **F9-R** |
| TR-3 | Re-translate after edit → overlay updates | **F9-R** |
| TR-4 | Duplicate repair regenerates UUID on loser copy → old translation not applied to regenerated block | **F9-R** |
| TR-5 | Cross-post same UUID string does not attach foreign row (`source_id` scope) | **F9-R** |
| TR-6 | Empty / rejected translation never renders empty string | **F9-R** |
| TR-7 | HTML sanitizer strips leaked attrs from translated output | **F9-O** |

**Note (F8 observation):** Migration may report `extraction_synced=true` while block Store rows need save-path confirmation — F9 must verify `sync_source` on canonical editor save produces expected `segment_kind=block` rows for allowlist blocks.

---

## 12. Language switching validation

Router: [`src/Routing/Router.php`](../../src/Routing/Router.php) — URL prefix is sole authority (no cookie in Milestone 1).

| Test | URL pattern | Expected |
|---|---|---|
| LS-1 | `/en/{page}/` or unprefixed default | Default language content |
| LS-2 | `/sv/{page}/` | Swedish overlay when flags + translation allow |
| LS-3 | Language switcher links (if present) | Navigate to prefixed URL; no `Set-Cookie` for language |
| LS-4 | Wrong prefix + missing translation | Source fallback |
| LS-5 | Hide-current switcher setting | UI respects [`Settings`](../../src/Settings.php) |

All tests on published acceptance pages with stable slugs.

---

## 13. Migration validation

F8 validated CLI migration on post `4638`. F9 re-confirms on **browser-edited cohort**:

| Step | Command / action | Expected |
|---|---|---|
| MG-1 | `wp aiml block migrate --post-id=<id> --dry-run --format=json --user=1` | No writes; accurate prediction |
| MG-2 | Live migrate once | UUID in content; audit JSON |
| MG-3 | Live migrate twice | `already_compliant` |
| MG-4 | Browser edit after migration → save | Inject/repair/extract path; no duplicate keys |
| MG-5 | `wp aiml block status --user=1` | `dependency_valid=true`; compliant count increases |

Migration remains **CLI-only** — no admin migrate button ([`SettingsPage`](../../src/Admin/SettingsPage.php) displays commands only).

---

## 14. Feature flag validation

Four production flags ([`Settings::defaults()`](../../src/Settings.php) — all default **false**).

| Test | Surface | Expected |
|---|---|---|
| FF-1 | Settings UI | Four checkboxes in dependency order; reserved flags hidden |
| FF-2 | Valid enable sequence | registration → injection → extraction → (staging only) frontend |
| FF-3 | Invalid combo | Admin notice + single `flag_combo_rejected` |
| FF-4 | `aiml_settings_flag_changed` | One event per changed flag on valid save |
| FF-5 | Frontend enable confirmation | Modal/script present ([`StrategyFSettingsTest`](../../tests/integration/StrategyFSettingsTest.php)) |
| FF-6 | Post-validation restore | All flags returned to false on dev |

F8 validated FF-3/FF-4 via WP-CLI eval; F9 repeats **admin UI** path in browser.

---

## 15. Failure scenarios

| Scenario | Detection | Required containment | F9 |
|---|---|---|---|
| Wrong translation rendered | Visual / DOM assertion | **FAIL** — stop | **F9-R** |
| UUID stripped with registration on | Export missing attr | **FAIL** — stop | **F9-R** |
| Duplicate UUID in document | Health scan / analyzer | Repair on save; no false render | **F9-R** |
| Store lookup failure | `block_translation_lookup_failed` | Source fallback | **F9-O** |
| Gate deny | Source content | No Store write on render path | **F9-R** |
| Unsupported block | Source only for that block | **F9-R** |
| Malformed Gutenberg after edit | Block recovery notice | Document; no silent corruption | **F9-O** |
| Invalid flag combo in prod | Settings sanitize | Normalize + audit | F8 PASS; F9 admin confirm |

---

## 16. Recovery scenarios

| Scenario | Recovery action | Expected after recovery |
|---|---|---|
| Bad translation published | Disable `block_frontend_rendering_enabled` | Source visible immediately |
| Runaway extraction | Disable `block_extraction_enabled` | No new sync; stale accumulates |
| UUID inject runaway | Disable `block_uuid_injection_enabled` | Existing UUIDs remain; no new inject |
| Editor stripped registration | **Do not** disable registration post-rollout | F9 documents warning only — compatibility lock |
| Corrupted flag state | `wp option get aiml_settings --format=json` + UI fix | `dependency_valid=true` on status |
| Lost editor session | Re-open draft | UUID stable per autosave matrix |

Rollback reference: F8 plan §7; validated kill switch in F8.

---

## 17. Concurrency scenarios

Spike S5 adversarial two-tab duplicate save proven **last-write-wins** + repair → `rendered_false_positive == 0`.

| Scenario | F9 requirement |
|---|---|
| CC-1 | Two sessions edit same post; second save wins | **F9-O** — replay spike scenario on production plugin |
| CC-2 | After concurrent duplicate UUID, canonical save repairs | **F9-R** |
| CC-3 | No wrong translation after repair | **F9-R** |

Strategy F does not fix WordPress lost-update semantics — document as known limitation (§22).

---

## 18. Performance acceptance

Budget source: F8 plan §10; spike scale benchmarks (Strategy F ~9.7× vs baseline walker).

| Operation | Budget (p95) | F9 method |
|---|---|---|
| Frontend render added latency | ≤ 50 ms on proof page (3 blocks) | Playwright `performance` API or Server-Timing if available |
| Editor save with inject + extract | ≤ 2 s on 20-block acceptance page | Playwright save timing |
| Browser suite total | ≤ 45 min Chromium full matrix | CI wall clock |
| Health CLI scan (sample 100) | ≤ 5 s | WP-CLI (F8: 54 ms–1.5 s) |

Failure of performance budgets is **non-blocking warning** unless p95 render > 200 ms (investigate before production).

---

## 19. Regression checklist

Run before F9 sign-off on acceptance commit:

- [ ] PHPUnit unit — 0 failures
- [ ] PHPUnit integration — 0 failures
- [ ] PHPCS — 0 errors, 0 warnings
- [ ] `wp aiml block status --user=1` — exit 0
- [ ] All four flags default false in fresh `Settings::defaults()` and dev option after teardown
- [ ] ADR-0013 status still **Proposed**
- [ ] No new DB schema version
- [ ] F8 validation log still present and PASS
- [ ] Spike corpus replay (optional): `StrategyFBrowserReplayTest` / integration equivalents — 0 FP
- [ ] No `aimlBlockId` in public HTML outside block comments (except documented third-party leak paths — must remain suppressed by gate)

---

## 20. Production acceptance checklist

Human operator sign-off (can parallel ADR-0013 checklist):

- [ ] Browser matrix §3 executed on dev.biopentra.eu
- [ ] UUID matrix §9 all **F9-R** cells PASS
- [ ] Frontend §10 FR-1..FR-7 PASS
- [ ] Translation §11 TR-1..TR-5 PASS
- [ ] Language §12 PASS
- [ ] Migration §13 PASS on cohort
- [ ] Flags §14 PASS in admin UI
- [ ] F9 validation log committed
- [ ] Stakeholder review of known limitations §22
- [ ] Rollback §21 verified in browser session
- [ ] Decision recorded: promote ADR-0013 toward Accepted **or** list remaining gaps

F9 PASS does **not** auto-set ADR to Accepted — see §24.

---

## 21. Rollback verification

Execute during F9 browser session:

| Step | Action | Verify |
|---|---|---|
| RB-1 | Enable full chain including frontend | Translation visible `/sv/…` |
| RB-2 | Disable `block_frontend_rendering_enabled` in admin | Source on next HTTP request |
| RB-3 | `wp aiml block status --user=1` | `dependency_valid=true` |
| RB-4 | Re-enable frontend | Translation restored |
| RB-5 | Confirm no `post_content` mutation during rollback | DB hash unchanged |

Matches F8 HTTP validation — F9 confirms via **admin UI** toggles.

---

## 22. Known limitations

| Limitation | Source | F9 handling |
|---|---|---|
| Adapter allowlist = 3 core blocks only | Production code | Document; do not expand in F9 |
| Post types: `post`, `page` only | `RenderGateContext` | No CPT tests |
| Synced patterns / `core/block` refs | ADR-0013 driver 5; S5 §13 | Negative tests only |
| Elementor-primary production content | ADR open question | Out of scope |
| Cross-site paste | S5 untested | Out of scope |
| WordPress last-write-wins concurrency | S5 §17 | Document |
| Request-scoped metrics | F8 WP4 | CLI status won't show HTTP render counts |
| WooCommerce dynamic block attr leak | S5 third-party | Must remain non-renderable without adapter |
| Field-level legacy rows coexist | Master plan §legacy | No fuzzy rematch |
| `duplicate_uuid` misnamed log key | F8 §3.6 | Cosmetic; not F9 scope |

---

## 23. Out-of-scope items

F9 explicitly excludes:

- New block adapters or expanded allowlist
- ADR-0013 promotion to Accepted (human gate)
- Production rollout / cohort flags (F10)
- Persistent metrics / dashboards (F10)
- Elementor editor or Elementor body translation
- Site Editor templates / template parts
- New REST endpoints or Settings API redesign
- Automatic migration from admin UI
- Cross-site / multisite matrix
- Rank Math / WooCommerce adapter proof
- Schema migrations
- Spike re-implementation under `spike/s5/lib` — reference only

---

## 25. Browser test tier policy

F9 browser acceptance uses **tiered Playwright execution**. The full 35-test matrix is expensive (~2 hours on dev.biopentra.eu) and must **not** run after every harness or feature edit.

| Tier | Scope | When to run |
|---|---|---|
| **Tier 1 — smoke** | Small critical subset (admin flags FF-1, one FR-1 case, one UUID matrix cell) | During normal development |
| **Tier 2 — feature-targeted** | Only tests related to the behavior under change | After a harness fix or localized feature change |
| **Tier 3 — full acceptance** | Complete 35-test matrix via `acceptance/f9-browser/tools/run-f9-acceptance.sh` | Only after all targeted failures pass; before F9 merge; before release candidate; after changes to shared editor/browser orchestration |

**Policy:** The full Playwright suite must not be run after every implementation pass. CI must not run the full suite on every commit.

**Targeted examples (Tier 2):**

```bash
cd acceptance/f9-browser
npx playwright test tests/uuid-matrix.spec.ts -g "undo-redo" --project=chromium-desktop
npx playwright test tests/uuid-matrix.spec.ts -g "group-ungroup" --project=chromium-desktop
npx playwright test tests/tier1-cross-browser.spec.ts -g "duplicate" --project=firefox-desktop
```

Require three consecutive targeted passes per failing test before Tier 3.

---

F9 milestone closes when **all** are true:

| Gate | Requirement |
|---|---|
| G1 | This plan's acceptance criteria §2 satisfied |
| G2 | `docs/plans/F9_BROWSER_VALIDATION_LOG.md` committed with **PASS**, commit hash, browser matrix results |
| G3 | Quality gates §19 green on merge commit |
| G4 | Operator sign-off §20 complete |
| G5 | Known limitations §22 reviewed — no unresolved **blocking** items |
| G6 | Tag `strategy-f-f9-browser-acceptance-complete` created on merge commit (execution step — not part of planning) |
| G7 | ADR-0013 human checklist items mapped to evidence — remaining gaps explicitly listed |

**After F9 PASS:** F10 limited rollout planning may begin. Frontend rendering enablement in any shared environment still requires explicit operator action per F8 §1.5.

---

## Work packages (execution outline)

| WP | Deliverable | Notes |
|---|---|---|
| WP0 | F9 harness bootstrap | **Complete** |
| WP1 | UUID matrix execution | **Complete** — 3 harness failures remain @ `46c3934` |
| WP2 | Frontend + language HTTP/browser | §10, §12 |
| WP3 | Translation + save-path Store proof | §11 TR-7; addresses F8 observation |
| WP4 | Admin flag UI browser tests | §14 |
| WP5 | Migration cohort re-validation | §13 |
| WP6 | Failure/concurrency spot checks | §15–§17 |
| WP7 | `F9_BROWSER_VALIDATION_LOG.md` + artifacts | §24 G2 |
| WP8 | Merge + tag (separate checkpoint workflow) | Mirror F8 checkpoint pattern |

---

## Related documents

| Document | Path |
|---|---|
| Master implementation plan | [STRATEGY_F_PRODUCTION_IMPLEMENTATION.md](STRATEGY_F_PRODUCTION_IMPLEMENTATION.md) |
| F8 operations plan | [STRATEGY_F_F8_OPERATIONS_AND_OBSERVABILITY.md](STRATEGY_F_F8_OPERATIONS_AND_OBSERVABILITY.md) |
| F8 CLI validation (PASS) | [F8_CLI_VALIDATION_LOG.md](F8_CLI_VALIDATION_LOG.md) |
| ADR-0013 | [0013-gutenberg-segment-identity.md](../adr/0013-gutenberg-segment-identity.md) |
| Spike S5 report | [S5-gutenberg-segment-identity.md](../spikes/S5-gutenberg-segment-identity.md) |
| Spike Playwright harness | `spike/s5/browser-validation/` |
| Spike browser corpus | `spike/s5/corpus/browser-validation/` |

**Production code under test (read-only during F9 planning):** `src/Block/AttributeRegistrar.php`, `src/Block/SavePipeline.php`, `src/Translation/BlockFrontendRenderer.php`, `src/Admin/SettingsPage.php`, `assets/block-editor.js`.
