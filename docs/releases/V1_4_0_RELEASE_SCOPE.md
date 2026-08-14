# AI Multilingual v1.4.0 — Release Scope Audit

**Status:** **IN PREPARATION**
**Date:** 2026-08-14
**Preparation branch:** `release/v1.4.0-preparation`
**Baseline main HEAD:** `505b818117bd830611f004ffe4bd16ac275d5286`
**Previous intentional release:** `v1.3.0` @ `c88ba30681439d9e7113a20d7ebc03c942dd240d`
**Schema:** Migrator `TARGET = 7` (**unchanged** — no migration in this release)
**Decision:** **RELEASE VERSION DECISION: 1.4.0**

## Version decision rationale

| Option | Verdict |
|---|---|
| Patch `1.3.x` | **Rejected** — understates TSC.0–TSC.6 and formal Extension API v1 |
| Minor **1.4.0** | **Selected** — additive public Extension API v1; complete Translation Surface Coverage program; no intentional breaking public contract; TARGET remains 7 |
| Major `2.0.0` | **Rejected** — no backwards-incompatible public contract or forced destructive upgrade |

**“TSC PROGRAM COMPLETE” means** the frozen TSC.0–TSC.6 ladder is implemented and closed on `main`. It does **not** mean every Deferred/Partial/Unsupported surface is shipped, nor perfect linguistic quality, nor automatic translation of all WordPress content.

## A. Product capabilities shipped since v1.3.0

### Translation Surface Coverage (TSC.0–TSC.6) — Complete

| Milestone | Shipped |
|---|---|
| **TSC.0** | Internal surface capability foundation; `SurfaceRegistry` / `SurfaceCapability`; request-local invalidation coordination; admitted surface ownership |
| **TSC.1** | First-class taxonomy terms; native term identity; lazy hosted adoption; term edit/review/publication; visitor term overlays; Rank Math term coexistence |
| **TSC.2** | Registered meta translation surfaces; exact-key catalog; provider admission; Rank Math catalog ownership; post/term registered meta lifecycle |
| **TSC.3** | WooCommerce extended translation surfaces; global attribute labels; single-writer authority; shop-host rehome; variation safety; Woo email stale improvements |
| **TSC.4** | Gutenberg coverage expansion; broader supported block field rendering; structural-attribute safety; block-field authority hardening; stale granularity |
| **TSC.5** | Elementor coverage expansion; authoritative `after_save` invalidation; shared structural safety; editor/preview context isolation; cache/language isolation; eight supported widget families hardened |
| **TSC.6** | Public Extension API v1; public exact-key meta registration; public custom block adapter contract; `VisitorTranslationResolver`; `aiml_mark_source_dirty()`; WP-CLI extension diagnostics; Rank Math SEO regression stabilization; ADR-0022 |

### Already in v1.3.0 (still claimed; not re-shipped as new)

- **TIQ Complete (TQ.0–TI.7)** — intelligence, QA, assessment, Jobs, controlled publication foundation
- **OTL Complete (OTL.0–OTL.6)** — operator lifecycle presentation and bounded orchestration
- **Integration API v1** — unchanged hook and `p:` integration contract

## B. Public API additions (v1.4.0)

| Symbol | Type |
|---|---|
| `aiml_register_extensions` | Action hook |
| `ExtensionRegistrar` | Class |
| `ExtensionManifest` | DTO |
| `RegisteredExtension` | Handle |
| `ExtensionMetaDefinition` | DTO |
| `ExtensionBlockAdapter` | Interface |
| `SourceSegmentReference` | Immutable DTO |
| `LanguageReference` | Immutable DTO |
| `ResolvedTranslation` | Immutable DTO |
| `VisitorTranslationResolver` | Class |
| `aiml_mark_source_dirty()` | Global helper |
| `wp aiml extensions list` | WP-CLI |
| `wp aiml extensions status <extension_id>` | WP-CLI |

Documented in `docs/EXTENSION_API_V1.md`, cross-linked from `docs/INTEGRATION_API_V1.md` and `docs/HOOKS.md`. ADR-0022 Accepted.

## C. Schema / persistence

| Item | Status |
|---|---|
| `Migrator::TARGET` | **7** (unchanged since v1.2.0) |
| New migration in v1.4.0 | **None** |
| Extension registration state | **Not** persisted (code-driven per request) |

## D. CI / tooling / docs

- TSC planning/implementation/closure docs (TSC.0–TSC.6)
- `docs/EXTENSION_API_V1.md`
- Black-box reference extension fixture (tests only; excluded from ZIP)
- Local browser acceptance partly documented as non-CI where applicable

## E. Must NOT claim as shipped

| Item | Disposition |
|---|---|
| Public Elementor widget registration API | **Deferred** |
| Public CPT/taxonomy admission filters | **Deferred** |
| Yoast adapter | **Deferred** |
| Site Health extension diagnostics UI | **Deferred** |
| Generic overlay registration API | **Unsupported** |
| Translated slugs / SE11 | **Deferred** |
| Some Elementor templates / Theme Builder / forms / third-party widgets | **Deferred** |
| Some Gutenberg reusable / FSE / dynamic blocks | **Deferred** |
| Live Playwright as CI gate | **Unsupported** (local only) |
| External CDN cache purge | **Operator/infrastructure scope** |
| Automatic translation of all content | **Not implied** |
| Integration API v2 | **Not shipped** |
| Deployment to production sites | **Not part of this release task** |

## Upgrade implications (v1.3.0 → v1.4.0)

1. Install `ai-multilingual-1.4.0.zip` over previous plugin directory.
2. Activate / visit wp-admin so `maybe_migrate()` runs (no-op at TARGET 7).
3. Confirm `aiml_db_version` remains **7**.
4. Confirm publication defaults unchanged: gate OFF, mode `manual`.
5. Confirm Gutenberg/Elementor feature flags remain **OFF** by default unless already enabled by operator.
6. Extension API registration is additive and code-driven; no automatic enrollment of arbitrary meta/widgets.
7. Existing translation/review/publication rows remain valid; no republish/unpublish sweep.

## Public contracts

| Contract | Status |
|---|---|
| Integration API v1 | Unchanged |
| Extension API v1 | **New in v1.4.0** |
| Schema TARGET | **7** |
| PublicationPolicy / PublicationService | Unchanged ownership |
| Assessment R1.0 | Unchanged ownership |

## Authoritative version sources for 1.4.0

| Source | Value |
|---|---|
| Plugin header `Version:` | 1.4.0 |
| `AIML_VERSION` | 1.4.0 |
| `readme.txt` Stable tag | 1.4.0 |
| CHANGELOG / release notes | 1.4.0 |
| Package name | `ai-multilingual-1.4.0.zip` |

Historical refs intentionally retained: prior changelog entries, `@since`, baseline-v1.1.0 pack name, v1.3.0 release docs, milestone plan SHAs.

## Deployment

This release preparation does **not** deploy to production. Tag-triggered GitHub Release builds and attaches the audited ZIP; site installation remains a separate operator action.
