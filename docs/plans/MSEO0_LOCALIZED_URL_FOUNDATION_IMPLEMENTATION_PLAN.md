# MSEO.0 — Localized URL Foundation — Implementation Plan

**Status:** **Architecture Frozen** — authoritative specification for MSEO.0 implementation
**Milestone:** MSEO.0 — Localized URL Foundation (inert)
**Parent:** [MSEO_PARENT_IMPLEMENTATION_PLAN.md](MSEO_PARENT_IMPLEMENTATION_PLAN.md) (**Architecture Frozen**)
**ADR:** [0023-localized-url-overlay-architecture.md](../adr/0023-localized-url-overlay-architecture.md) (**Accepted** at planning freeze)
**External review:** **FREEZE** (A1–A16 + F1–F14)
**STATE:** B · **TARGET 8** (this milestone)
**Planning branch:** `docs/mseo-parent-plan-freeze` (merged before implementation)
**Implementation branch:** `feature/mseo0-localized-url-foundation` (create when authorized)
**Baseline:** `main` @ `2c5bb0fd47a8fbb8e57d51c6ee85bbc1647a8386` (v1.4.0; `Migrator::TARGET` **7**)
**Depends on:** v1.4.0; ADR-0023 Accepted; MSEO parent frozen

**This document is the authoritative implementation specification for MSEO.0.** MSEO.0 is **inert**: zero change to v1.4.0 frontend URL/SEO behavior.

---

## 1. Repository architecture audit (integration points)

| Subsystem | Path | MSEO.0 touch | MSEO.2+ touch |
|---|---|---|---|
| Router | `src/Routing/Router.php` | **None** (no hook changes) | Inbound pipeline, EffectiveUrl delegation |
| LanguageResolver | `src/Language/LanguageResolver.php` | None | None |
| PreviewService | `src/Workspace/PreviewService.php` | None (source-slug forever) | None |
| LanguageRelationshipService | `src/Seo/LanguageRelationshipService.php` | None | EffectiveUrl per language |
| DocumentSeoHead | `src/Seo/DocumentSeoHead.php` | None | Canonical via SB11 |
| RankMathSitemapOverlay | `src/Integration/RankMath/RankMathSitemapOverlay.php` | None | Alternate URLs |
| Switcher | `src/Frontend/Switcher.php` | None | SB11 |
| Store | `src/Translation/Store.php` | Read `slug_origin` column after migration | Slug segment R/W |
| PublicationService | `src/Translation/Publication/PublicationService.php` | None | Route publication composes eligibility |
| Settings | `src/Settings.php` | Add state fields default `off` | Admin UI MSEO.2 |
| Migrator / Schema | `src/Database/*` | **TARGET 8 step** | — |
| Plugin bootstrap | `src/Plugin.php` | Wire services for DI/tests only if needed; **no frontend hooks** | Router/SEO wiring |
| PluginGuard | `tests/integration/PluginGuardTest.php` | TARGET 8 allowlist + MSEO deferred guards | Positive MSEO guards |
| Migration tests | `tests/integration/*SchemaTest.php` | New `MseoSchemaTest.php` | — |
| A.SEOa deferred | `tests/integration/AseoaDeferredSlugGuardTest.php` | Keep green until MSEO.2 replaces | — |

**No duplicate policy engines:** MSEO.0 does **not** implement `ObjectLanguagePublicEligibility` or `PublicationPolicy` replacements — scaffold interfaces only where needed for MSEO.1.

---

## 2. MSEO.0 objective

Land **inert** localized-URL infrastructure:

1. ADR-0023 on `main` (planning commit — this freeze wave)
2. TARGET 8 schema (tables + `slug_origin` column)
3. Path hash + PathCanonicalizer
4. Repository primitives (routes, history, frontier)
5. EffectiveUrlService **read-only passthrough** (always returns canonical source path)
6. Settings defaults (`localized_urls_state=off`) — **no admin UI exposure**
7. Tests + PluginGuard foundations

**Zero** public URL, routing, redirect, canonical, hreflang, sitemap, or switcher behavior change.

---

## 3. MSEO.0 explicit exclusions

MSEO.0 MUST NOT:

- expose localized URL enable UI in Settings
- set `localized_urls_state` to anything other than `off` in normal operation
- register Router inbound localized-path substitution or redirects
- register `wp:5` canonical redirect handler
- modify `Router::filter_home_url()` behavior for localization
- modify `LanguageRelationshipService`, `DocumentSeoHead`, `RankMathSitemapOverlay`, or `Switcher`
- mutate `post_name` or term `slug`
- register rewrite rules or call `flush_rewrite_rules`
- auto-generate or publish routes
- call translation providers for slugs
- bump plugin header version (remains **1.4.0** until release milestone)
- tag or deploy

---

## 4. TARGET 8 schema audit

Validated against [`Schema.php`](../src/Database/Schema.php) and [`Migrator.php`](../src/Database/Migrator.php) conventions:

| Check | Verdict |
|---|---|
| Table prefix via `Schema::table()` | **Pass** — no hardcoded `wp_` |
| Explicit SQL, not dbDelta | **Pass** — ADR-0003 |
| `ENGINE=InnoDB ROW_FORMAT=DYNAMIC` + `charset_collate()` | **Pass** |
| `BINARY(32)` SHA-256 | **Pass** — MariaDB/MySQL InnoDB (dev VPS MariaDB) |
| `VARCHAR(2048)` paths | **Pass** — under InnoDB index limits with hash-first lookup |
| Unique indexes on hash columns | **Pass** — uniqueness on `(language_id, *_path_hash)` |
| `source_type VARCHAR(16)` | Align with Store `VARCHAR(20)` — use **VARCHAR(20)** in DDL to match Store |
| Timestamps `DATETIME NOT NULL` | **Pass** — match existing tables |
| Idempotent `CREATE TABLE IF NOT EXISTS` | **Pass** |
| Column add `slug_origin` via `Schema::column_exists` guard | **Pass** — mirror step 5/7 pattern |
| Interrupted recovery | **Pass** — version written after step 8 completes |
| Fresh install | Step 8 runs in same `migrate()` pass after step 1 |
| Upgrade from TARGET 7 | Step 8 only; empty new tables; `slug_origin` default `''` |
| Uninstall | ADR-0004 — tables retained unless `remove_data_on_uninstall`; add new tables to `Schema::all_tables()` drop list |
| PluginGuard `$wpdb` allowlist | Add new repository paths in MSEO.0 WP6 |

**Schema adjustment from parent freeze:** use `source_type VARCHAR(20)` to match `aiml_translations.source_type`.

### DDL constants (`Schema.php`)

```php
public const SLUG_ROUTES         = 'aiml_slug_routes';
public const ROUTE_HISTORY       = 'aiml_route_history';
public const SLUG_REINDEX_FRONTIER = 'aiml_slug_reindex_frontier';
```

Add factory methods: `slug_routes()`, `route_history()`, `slug_reindex_frontier()`, `create_slug_routes()`, `create_route_history()`, `create_slug_reindex_frontier()`.

### Migrator step 8

```php
8 => array( $this, 'step_8_mseo_localized_url_foundation' ),
```

Bump `Migrator::TARGET` to **8**. Method:

1. `$wpdb->query( Schema::create_slug_routes() )`
2. `$wpdb->query( Schema::create_route_history() )`
3. `$wpdb->query( Schema::create_slug_reindex_frontier() )`
4. Add `slug_origin` column to translations if missing (additive ALTER)

No data backfill in MSEO.0.

---

## 5. New classes (MSEO.0)

Namespace: `AIMultilingual\Routing\`

| Class | File | MSEO.0 responsibility |
|---|---|---|
| `PathHash` | `src/Routing/PathHash.php` | SHA-256 `BINARY(32)` from normalized path; pure |
| `PathCanonicalizer` | `src/Routing/PathCanonicalizer.php` | Normalize paths; fail closed; pure where possible |
| `EffectiveUrlService` | `src/Routing/EffectiveUrlService.php` | **Inert:** returns source path unchanged; reads settings state; no DB on hot path unless injected repos unused |
| `SlugRouteRepository` | `src/Routing/SlugRouteRepository.php` | CRUD primitives; `$wpdb` confined here |
| `RouteHistoryRepository` | `src/Routing/RouteHistoryRepository.php` | History insert/list/purge primitives |
| `ReindexFrontierRepository` | `src/Routing/ReindexFrontierRepository.php` | Frontier checkpoint persistence |

**MSEO.0 EffectiveUrlService contract:**

```php
// Pseudocode — inert behavior
public function unprefixed_source_path_for_request(): string; // passthrough
public function unprefixed_effective_path( string $source_path, int $language_id ): string {
    return $source_path; // MSEO.0: never localizes
}
```

No Router registration in MSEO.0.

---

## 6. Settings (MSEO.0)

Add to [`Settings::defaults()`](../src/Settings.php):

```php
'localized_urls_state'                  => 'off',
'localized_urls_activation_checkpoint'  => null,
'localized_urls_activation_error'       => '',
```

Add sanitization in `Settings::sanitize()`:

- `localized_urls_state` ∈ `{ off, activating, on, failed }` — coerce invalid to `off`
- checkpoint: nullable string
- error: string

Add accessor methods (no SettingsPage UI):

- `localized_urls_state(): string`
- `is_localized_url_generation_enabled(): bool` → `state === 'on'`

**Do not** add SettingsPage field in MSEO.0.

---

## 7. Work packages

### WP0 — Preflight / baseline

| | |
|---|---|
| **Objective** | Confirm clean baseline before code |
| **Files** | None |
| **Validation** | `git status`; `main`/`origin/main` @ `2c5bb0fd`; `Migrator::TARGET === 7`; version 1.4.0 |
| **Stop** | Any drift from frozen baseline without documentation |

### WP1 — TARGET 8 migration / schema

| | |
|---|---|
| **Objective** | Land step 8 DDL + TARGET bump |
| **Files** | `src/Database/Schema.php`, `src/Database/Migrator.php` |
| **Changes** | Constants, `create_*` DDL, `all_tables()` order, `step_8_*`, `TARGET = 8` |
| **Invariants** | Idempotent; forward-only; no data mutation |
| **Tests** | `tests/integration/MseoSchemaTest.php` |
| **Validation** | `composer test -- --filter MseoSchemaTest` |
| **Stop** | TARGET 8 applied; fresh + upgrade paths green |

### WP2 — Route / history / frontier repositories

| | |
|---|---|
| **Objective** | Persistence primitives with hash verify |
| **Files** | `src/Routing/SlugRouteRepository.php`, `RouteHistoryRepository.php`, `ReindexFrontierRepository.php` |
| **Changes** | Insert/upsert/find by hash with full-path verify; fail closed on hash mismatch |
| **Invariants** | `$wpdb` + `prepare()`; table names from Schema only |
| **Tests** | `tests/integration/MseoRepositoryTest.php` |
| **Validation** | `composer test -- --filter MseoRepositoryTest` |
| **Stop** | CRUD + uniqueness constraints proven |

### WP3 — PathCanonicalizer + PathHash

| | |
|---|---|
| **Objective** | Pure path normalization + SHA-256 |
| **Files** | `src/Routing/PathCanonicalizer.php`, `PathHash.php` |
| **Changes** | Leading slash, duplicate collapse, encoding rules, malformed fail closed |
| **Tests** | `tests/unit/Routing/PathCanonicalizerTest.php`, `PathHashTest.php` |
| **Validation** | `composer test -- --filter PathCanonicalizerTest` |
| **Stop** | Unit coverage for malformed inputs + WP trailing-slash helper integration |

### WP4 — EffectiveUrlService inert foundation

| | |
|---|---|
| **Objective** | Scaffold authority with passthrough behavior |
| **Files** | `src/Routing/EffectiveUrlService.php` |
| **Changes** | Constructor accepts Settings + repos; generation always disabled in MSEO.0 |
| **Invariants** | No hooks registered; no Router changes |
| **Tests** | `tests/unit/Routing/EffectiveUrlServiceTest.php` |
| **Validation** | Passthrough tests green |
| **Stop** | Service instantiable; returns source paths only |

### WP5 — Settings state defaults OFF

| | |
|---|---|
| **Objective** | Persist activation state machine defaults |
| **Files** | `src/Settings.php`, `tests/unit/SettingsTest.php` (extend if present) |
| **Changes** | Defaults + sanitize + accessors |
| **Invariants** | Default `off`; no admin UI |
| **Tests** | Settings sanitize tests |
| **Stop** | Fresh install reads `localized_urls_state=off` |

### WP6 — PluginGuard / deferred guards

| | |
|---|---|
| **Objective** | Structural invariants for MSEO.0 |
| **Files** | `tests/integration/PluginGuardTest.php`, `tests/integration/Mseo0DeferredGuardTest.php` (new) |
| **Changes** | Allowlist new repositories in `$wpdb` test; assert TARGET 8; assert no Router inbound MSEO hooks; assert EffectiveUrl not wired to `home_url` yet; assert no `SlugRouteActivationJob` class |
| **Invariants** | Existing PluginGuard green |
| **Validation** | `composer test -- --filter PluginGuardTest` and `Mseo0DeferredGuardTest` |
| **Stop** | Guards document inert boundary |

### WP7 — Unit tests

| | |
|---|---|
| **Objective** | Complete unit coverage for pure components |
| **Files** | WP3–WP5 unit tests |
| **Validation** | `composer test -- tests/unit/Routing/` |

### WP8 — Integration / migration tests

| | |
|---|---|
| **Objective** | Schema upgrade, idempotence, recovery |
| **Files** | `tests/integration/MseoSchemaTest.php`, `MseoRepositoryTest.php` |
| **Cases** | TARGET 7→8; fresh TARGET 8; double `migrate()` idempotent; `slug_origin` column exists; three tables exist; unique `object_language` enforced |
| **Validation** | `composer test -- --filter MseoSchema` |

### WP9 — Documentation

| | |
|---|---|
| **Objective** | Cross-link freeze docs |
| **Files** | `docs/HOOKS.md` (stub note: MSEO hooks deferred to MSEO.2), `docs/ROADMAP.md`, `docs/PRODUCT_PRIORITIES.md` |
| **Changes** | Pointer to MSEO parent; ADR-0023 link |
| **Stop** | Docs consistent with inert MSEO.0 |

### WP10 — Final validation

| | |
|---|---|
| **Objective** | Full regression |
| **Commands** | `composer test`; `composer phpcs`; verify `Migrator::TARGET === 8` after migrate in test bootstrap only — **production deploy still ships code that migrates 7→8 on activate** |
| **Invariants** | All existing tests green; AseoaDeferredSlugGuardTest still expects deferred slug routing |
| **Stop** | MSEO.0 acceptance checklist (§8) complete |

---

## 8. MSEO.0 acceptance criteria (M0AC1–M0AC20, count: 20)

- **M0AC1:** `Migrator::TARGET === 8` after implementation merge
- **M0AC2:** TARGET 7 → 8 upgrade creates all three tables
- **M0AC3:** Fresh install reaches TARGET 8 in one migrate pass
- **M0AC4:** Double `migrate()` is idempotent
- **M0AC5:** `aiml_translations.slug_origin` exists; default `''`
- **M0AC6:** `UNIQUE object_language` on `aiml_slug_routes` enforced
- **M0AC7:** Path hashes are `BINARY(32)` SHA-256
- **M0AC8:** Repository hash lookup verifies full path string; mismatch fails closed
- **M0AC9:** PathCanonicalizer rejects malformed encoding
- **M0AC10:** `localized_urls_state` defaults to `off`
- **M0AC11:** No SettingsPage localized URL control rendered
- **M0AC12:** No Router inbound path substitution registered
- **M0AC13:** No `home_url` localization beyond existing prefix behavior
- **M0AC14:** No rewrite rules added
- **M0AC15:** No `post_name` / term slug mutation
- **M0AC16:** SB11 / canonical / hreflang / sitemap tests unchanged (regression green)
- **M0AC17:** No provider calls from Routing namespace (PluginGuard)
- **M0AC18:** `AseoaDeferredSlugGuardTest` still PASS (deferred until MSEO.2)
- **M0AC19:** `Schema::all_tables()` includes new tables in drop-safe order
- **M0AC20:** Full `composer test` green

---

## 9. Repository-specific adaptations

| Topic | Adaptation |
|---|---|
| `source_type` width | Use `VARCHAR(20)` in MSEO tables to match Store |
| `$wpdb` allowlist | Add three repository files to PluginGuard allowed list |
| Hash algorithm split | Store SHA-1 unchanged; MSEO paths SHA-256 per ADR-0023 |
| Settings pattern | State machine in `aiml_settings` array, not separate option (except optional job queue in MSEO.2) |
| Test harness | Follow `TranslationMemorySchemaTest` / `ReviewSchemaTest` migration test patterns |
| Plugin.php wiring | MSEO.0 may construct EffectiveUrlService in container for tests but **must not** register frontend hooks |
| A.SEOa guards | Keep deferred guard tests until MSEO.2 replaces with positive tests |

---

## 10. Architecture contradictions

**None identified.** Frozen design aligns with ADR-0001, ADR-0002, ADR-0003, existing Store/Migrator/Settings patterns, and SB11/Rank Math ownership.

---

## 11. Exact next step after MSEO.0

1. Merge MSEO.0 implementation to `main`
2. Begin **MSEO.1** planning freeze (candidate/active + Workspace slug field) — still inert publicly
3. Do **not** expose enable UI until **MSEO.2** acceptance

**Implementation branch:** `feature/mseo0-localized-url-foundation`

**MSEO.0 PLAN READY FOR IMPLEMENTATION** upon merge of this specification to `main`.
