# Glossary MVP Validation Log — dev.biopentra.eu

Operational acceptance record for post-v1 **Glossary MVP** (ADR-0014 platform lexicon).

## Environment

| Item | Value |
|---|---|
| Host | `https://dev.biopentra.eu` |
| Branch | `feature/glossary-mvp` |
| Commit | *129f7f36e0d879c8911a728bc7e8cd4c754c80dd* |
| Plugin | AI Multilingual `0.1.0` (`AIML_VERSION`) |
| Plugin mount | `/opt/biopentra/dev/ai-multilingual` → `wp-content/plugins/ai-multilingual` |
| WordPress | `7.0.2` |
| PHP | `8.3.32` (WordPress container) |
| WP-CLI user | `bp_manager` (ID `1`, `--user=1`) |
| Languages | `en` (default), `sv` (published) |
| ADR-0014 | **Accepted** (2026-08-05) |

## Entry gate (G0)

| Gate | Required state | Result |
|---|---|---|
| ADR-0014 Accepted | Status Accepted + PO residual risks | **PASS** — `ea2e45fce` |
| Frozen plan | `GLOSSARY_MVP_IMPLEMENTATION_PLAN.md` | **PASS** |
| No redesign / no scope change | Architecture locks held | **PASS** |

## Work packages

| WP | Commit | Result |
|---|---|---|
| G0 ADR accept | `ea2e45fce` docs(glossary): accept ADR-0014 and open implementation gate | **PASS** |
| G1 Schema v4 | `0bd4acbb6` feat(glossary): add glossary schema and migration | **PASS** |
| G2 Domain | `00f33eccf` feat(glossary): add glossary repository and matching service | **PASS** |
| G3 Suggestions | `5d5c8b978` feat(glossary): add exact glossary suggestion provider | **PASS** |
| G4 AI fragment | `fc38f0384` feat(glossary): wire bounded glossary_fragment into AI batch | **PASS** |
| G5 QA warning | `85846ee53` feat(glossary): add glossary_term_missing QA warning | **PASS** |
| G6 REST/admin | `4db2a9719` feat(glossary): add glossary REST, capability, and admin UI | **PASS** |
| G7 Closure | *(this commit)* test(glossary): complete Glossary MVP validation | **PASS** |

## Quality gates (Tier 0)

| Gate | Command | Result |
|---|---|---|
| PHPUnit unit | `vendor/bin/phpunit -c phpunit.xml.dist` | **PASS** — 389 tests, 876 assertions (2 skipped NFC without intl in unit image) |
| PHPUnit integration | `vendor/bin/phpunit -c phpunit-integration.xml.dist` | **PASS** — 338 tests, 7661 assertions (2 skipped glossary writes without intl in aiml-test-runner) |
| PHPCS | `vendor/bin/phpcs --standard=phpcs.xml.dist` | **PASS** — 0 errors (2 pre-existing warnings unrelated) |
| TypeScript / Jest / webpack | translator-workspace build | **N/A** — Glossary admin is vanilla JS assets (no TS bundle change) |
| git diff --check | `git diff --check` | **PASS** |
| F9 35-suite | `run-f9-acceptance.sh` | **NOT RUN** (explicit policy) |

## Schema / migration

| Check | Result |
|---|---|
| Migrator `TARGET = 4` | **PASS** — live `schema=4` |
| Table `aiml_glossary` | **PASS** — integration `GlossarySchemaTest` + activation |
| Option `aiml_glossary_version` | **PASS** — created; bumps on mutation |
| Uninstall drops table + option | **PASS** — covered by schema/uninstall paths |

## Normalization / matching

| Check | Result |
|---|---|
| NFC + case/whitespace fold | **PASS** — unit (skipped when no intl) |
| Exact-segment vs embedded | **PASS** — matcher + suggestion ranking tests |
| Longest-wins overlap | **PASS** |
| No match inside larger word | **PASS** |

## Exact vs embedded glossary behavior

| Behavior | Result |
|---|---|
| Exact-segment → `NormalizedSuggestion` tier 5 | **PASS** |
| Embedded → never full-segment suggestion | **PASS** |
| Embedded → fragment + QA only | **PASS** |

## Ranking

| Tier | Provider | Result |
|---|---|---|
| 1–4 | TM (unchanged) | **PASS** |
| 5 | Glossary exact | **PASS** |
| 6 | Fuzzy TM (was 5) | **PASS** |
| 7 | AI (was 6) | **PASS** |

## AI fragment bounds

| Bound | Result |
|---|---|
| Max 40 terms | **PASS** — unit truncation |
| Max 4000 chars | **PASS** — builder logic |
| Marker `# glossary_truncated` | **PASS** |
| OpenAI consumes string only | **PASS** — no glossary I/O in provider |

## QA

| Check | Result |
|---|---|
| Issue code `glossary_term_missing` | **PASS** |
| Severity warning (never blocks) | **PASS** |
| Language-pair context required | **PASS** |

## REST / admin UI

| Check | Result |
|---|---|
| Routes under `aiml/v1/glossary*` | **PASS** — live + integration |
| Header `X-AIML-Glossary-Api-Version: 1` | **PASS** |
| ViewModels only (no `GlossaryTermMatch`) | **PASS** |
| Admin page `#aiml-glossary-admin-root` | **PASS** — Playwright smoke |

## Capability / audit / diagnostics

| Check | Result |
|---|---|
| Cap `aiml_manage_glossary` on Administrator | **PASS** |
| Editor (`aiml_translate` only) → 403 list | **PASS** |
| Audit omits source/target term text | **PASS** |
| Diagnostics low-cardinality counters | **PASS** — live REST |

## Targeted browser smoke

| Check | Result |
|---|---|
| `GET /wp-admin/admin.php?page=aiml-glossary` | **PASS** — HTTP 200, title Glossary, root mount present, lexicon meta rendered |
| F9 matrix | **NOT RUN** |

## Live WP-CLI REST smoke

| Check | Result |
|---|---|
| Create term (intl available) | **PASS** — HTTP 201 |
| Delete term | **PASS** — HTTP 200 |
| List / diagnostics | **PASS** |

## Rendered false-positive count

| Check | Result |
|---|---|
| Glossary code touches render/UUID/Store write path | **None** — additive only |
| FP render regression attributed to Glossary | **0** |

## Compatibility

| Check | Result |
|---|---|
| F11 DTO field names | **Unchanged** |
| Ranking tier amendment | **Documented + tested** (intentional) |
| Workspace REST | **Additive only** |

## Documentation

| Doc | Result |
|---|---|
| ADR-0014 Accepted | **PASS** |
| Plan status → implemented / validation PASS | **PASS** (this closure) |
| `docs/HOOKS.md` glossary REST | **PASS** |
| Post-v1 roadmap Glossary status | **PASS** (this closure) |

## Known limitations

- Unicode whole-word matching MVP limits (no stemming/morphology/fuzzy glossary)
- Apostrophe curly vs straight not folded
- Glossary version stamp does not auto-invalidate TM or rendered content
- Warning-only QA may permit terminology deviations
- Integration CRUD write tests skip when `ext-intl` absent in aiml-test-runner image (WordPress runtime has intl)

## Merge readiness

**Ready for review / merge** on `feature/glossary-mvp`. Do **not** merge or tag until Product Owner reviews this completion report.

## Recommended tag

`glossary-mvp-complete` (after merge to `main`)

## Exact next step

Review the Glossary MVP completion report, then merge and tag the milestone.
