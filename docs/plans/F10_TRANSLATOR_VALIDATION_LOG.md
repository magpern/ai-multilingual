# F10 Translator Workspace Validation Log — dev.biopentra.eu

Operational acceptance record for Strategy F milestone F10 (Translator Workspace MVP).

## Environment

| Item | Value |
|---|---|
| Host | `https://dev.biopentra.eu` (`169.58.7.116`) |
| Branch | `feature/f10-translator-workspace` |
| Commit | Recorded at closure push |
| Plugin | AI Multilingual `0.1.0` (`AIML_VERSION`) |
| Plugin mount | `/opt/biopentra/dev/ai-multilingual` → `wp-content/plugins/ai-multilingual` |
| WordPress | `7.0.2` |
| PHP | `8.3.32` (WordPress container) |
| WP-CLI user | `bp_manager` (ID `1`, `--user=1`) |
| Languages | `en` (default), `sv` (published) |
| Validation page | Post ID `6321`, slug `f10-translator-validation` |
| Segment key (block) | `b:f10a8400-e29b-41d4-a716-446655440001:content` |
| Strategy F flags | attr, uuid, extraction, frontend rendering = **on** |

## Quality gates (Tier 0)

| Gate | Command | Result |
|---|---|---|
| PHPUnit unit | `vendor/bin/phpunit -c phpunit.xml.dist` | **PASS** — 287 tests, 559 assertions |
| PHPUnit integration | `vendor/bin/phpunit -c phpunit-integration.xml.dist` | **PASS** — 284 tests, 4436 assertions |
| PHPCS | `vendor/bin/phpcs` | **PASS** |
| Jest (workspace) | `npm test` in `assets/translator-workspace/` | **PASS** — 19 tests |
| Production build | `npm run build` in `assets/translator-workspace/` | **PASS** |
| git diff --check | `git diff --check` | **PASS** |

## F10 browser smoke (Tier 2 — targeted)

**Suite:** `acceptance/f10-browser/tests/workspace-smoke.spec.ts`
**Count:** 4 tests
**Duration:** ~43s (Chromium, headless)
**Full F9 35-test suite:** **NOT RUN**

| Test | Result |
|---|---|
| Workspace load + post context + status summary | **PASS** |
| Manual edit and save | **PASS** |
| Preview opens public `/sv/` route | **PASS** |
| Bulk translate reports not configured | **PASS** |

## REST smoke (dev.biopentra.eu)

Executed via WP-CLI `rest_do_request` as user 1 against post `6321`, language `sv`:

| Step | HTTP | Evidence |
|---|---|---|
| GET `/aiml/v1/workspace/6321/segments?language=sv` | 200 | 2 segments; block segment `missing`, `can_edit=true` |
| POST save block segment | 200 | `translated_text=F10 validering` |
| GET `/aiml/v1/workspace/6321/preview-url?language=sv` | 200 | `url=https://dev.biopentra.eu/sv/f10-translator-validation/` |
| POST translate (null provider) | 200 | `status=failed`, `errors[0].code=aiml_ai_not_configured` |
| POST save with stale `source_hash` | 409 | `aiml_source_hash_mismatch` |
| POST `/segments/batch` | 200 | `status=completed` |

## HTTP preview + kill switch (AC-4)

| Step | Marker | Result |
|---|---|---|
| Rendering **on** | Swedish translation visible in HTML | **PASS** |
| Rendering **off** | Source only, translation absent | **PASS** |
| Rendering **re-enabled** | Translation restored | **PASS** |

## Acceptance criteria (§2)

| ID | Criterion | Evidence | Result |
|---|---|---|---|
| AC-1 | Translator loads/saves block segments | Integration + dev REST + F10 smoke | **PASS** |
| AC-2 | Segment keys `b:<uuid>:<field>` | `WorkspaceRestTest`, dev segment key | **PASS** |
| AC-3 | Stale on load; save clears stale | `WorkspaceStaleTest` | **PASS** |
| AC-4 | Preview production path; kill switch | `PreviewProductionPathTest`, dev HTTP | **PASS** |
| AC-5 | No cross-post leakage | `WorkspaceIsolationTest` | **PASS** |
| AC-6 | Thin controllers; logic in WorkspaceService | Unit + provider tests | **PASS** |
| AC-7 | ViewModels only over REST | `WorkspaceRestTest` | **PASS** |
| AC-8 | Optimistic locking HTTP 409 | `WorkspaceConflictTest`, dev REST | **PASS** |
| AC-9 | Segment order matches walker | `WorkspaceSegmentOrderTest` | **PASS** |
| AC-10 | M1 editor defers block posts | `EditorWorkspaceDeferralTest` | **PASS** |
| AC-11 | PHPUnit + PHPCS green | Quality gates above | **PASS** |
| AC-12 | Tier 1 Playwright optional | F10 smoke 4/4 (not F9 Tier 3) | **PASS** |
| AC-13 | This validation log committed | This file | **PASS** |

## Architecture audit

| Check | Result |
|---|---|
| No business logic in `WorkspaceController` | **PASS** |
| No raw Store rows over REST | **PASS** |
| `WorkspaceService` facade | **PASS** |
| `BatchOperationCoordinator` owns bulk loops | **PASS** |
| `NullAIProvider` only (no vendor AI) | **PASS** |
| ADR-0013 | **Proposed** (unchanged) |

## Operator sign-off

| Field | Value |
|---|---|
| Validator | Cursor agent (autonomous F10 closure) |
| Date | 2026-08-03 |
| Final result | **PASS** |

## Tag recommendation

Recommend `strategy-f-f10-translator-complete` on merge to `main` (operator action).
