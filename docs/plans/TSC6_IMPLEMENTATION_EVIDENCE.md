# TSC.6 Implementation Evidence

**Status:** **COMPLETE** on `main` @ `059c957b8eed0604082e3a899a6e2d2f94e8819a`  
**Branch:** `feature/tsc6-public-extension-seo-stabilization` (merged)  
**Feature HEAD (reviewed):** `310c29dc2`  
**Merge SHA:** `059c957b8eed0604082e3a899a6e2d2f94e8819a`  
**Fresh main CI:** PASS — https://github.com/magpern/ai-multilingual/actions/runs/31744508888  
**Closure SHA:** (this commit on `main`)  
**Frozen plan:** [TSC6_PUBLIC_EXTENSION_SEO_STABILIZATION_IMPLEMENTATION_PLAN.md](TSC6_PUBLIC_EXTENSION_SEO_STABILIZATION_IMPLEMENTATION_PLAN.md) @ `df40c00e81395fef857db40748f5e75380b51899`  
**ADR-0022:** [0022-public-extension-boundary-and-registration-lifecycle.md](../adr/0022-public-extension-boundary-and-registration-lifecycle.md) — **Accepted**  
**PR:** https://github.com/magpern/ai-multilingual/pull/32

## Work packages

| WP | Result | Evidence |
|---|---|---|
| TSC6.0 | COMPLETE | Contract audit in `ExtensionRegistrar`, `ExtensionMetaBridge`, `VisitorTranslationResolver`; ADR-0022 consistency; no public/internal name collisions |
| TSC6.1 | COMPLETE | `ExtensionRegistrar`, `ExtensionManifest`, `RegisteredExtension`, `ExtensionMetaDefinition`, seal phase, `ExtensionMetaBridge` |
| TSC6.2 | COMPLETE | `ExtensionBlockAdapter`, `ExtensionBlockAdapterBridge`; reference fixture block adapter |
| TSC6.3 | COMPLETE | `SourceSegmentReference`, `LanguageReference`, `ResolvedTranslation`, `VisitorTranslationResolver`, `aiml_mark_source_dirty()` |
| TSC6.4 | COMPLETE | `ExtensionCli` — `wp aiml extensions list/status` |
| TSC6.5 | COMPLETE | `Tsc6RankMathRegressionTest`; `docs/HOOKS.md` SEO completeness |
| TSC6.6 | COMPLETE | `docs/EXTENSION_API_V1.md`; `docs/INTEGRATION_API_V1.md` cross-link; `docs/HOOKS.md` |
| TSC6.7 | COMPLETE | `PluginGuardTest::assert_tsc6_invariants()`; this document |

## Implementation commits

| SHA | Summary |
|---|---|
| `54f661ec0` | docs(tsc6): record implementation baseline before production work |
| `ff236f664` | feat(tsc6): implement public Extension API v1 boundary |
| `a28a15892` | fix(tsc6): PHPCS docs, PluginGuard catch, resolver integration tests |
| `9f6abd29f` | chore: remove accidental composer.phar from repository |
| `bf60e6f90` | fix(tsc6): CI hook literal, resolver test property, perf meta keys |
| `95bc1695f` | fix(tsc6): integration test fixes for resolver and PluginGuard |

## Production changes

| Area | Files |
|---|---|
| Root registration | `src/Extension/ExtensionRegistrar.php`, `ExtensionRegistry.php`, `ExtensionRecord.php`, `RegisteredExtension.php`, `ExtensionManifest.php`, `Contract.php` |
| Public meta | `ExtensionMetaDefinition.php`, `PendingExtensionMeta.php`, `ExtensionMetaBridge.php` |
| Public block | `Block/ExtensionBlockAdapter.php`, `Block/ExtensionBlockAdapterBridge.php` |
| Resolver / invalidation | `VisitorTranslationResolver.php`, `SourceSegmentReference.php`, `LanguageReference.php`, `ResolvedTranslation.php`, `ExtensionServices.php`, `functions.php` |
| Diagnostics | `ExtensionDiagnostics.php`, `Cli/ExtensionCli.php` |
| Bootstrap | `src/Plugin.php` — `aiml_register_extensions`, seal, `ExtensionServices::bind` |
| Docs | `docs/EXTENSION_API_V1.md`, `docs/INTEGRATION_API_V1.md`, `docs/HOOKS.md` |
| Tests | `tests/unit/Extension/*`, `tests/integration/Extension/*`, `tests/Fixtures/ReferenceExtension/*` |

## Public API inventory (Extension API v1)

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

**Not public:** `Store`, `RegisteredMetaRegistry`, `AdapterRegistry`, `SurfaceRegistry`, internal bridges/registries.

## PX1–PX31

| ID | Result |
|---|---|
| PX1–PX9 | Supported — implemented |
| PX10 | **Deferred** — no public Elementor API |
| PX11 | Unsupported — no generic overlay API |
| PX12–PX24 | Supported — resolver, invalidation, provider deny, Rank Math regression |
| PX25 | **Deferred** — Yoast not implemented |
| PX26 | **Deferred** — CPT/taxonomy admission not implemented |
| PX27–PX31 | Supported — docs, black-box fixture, PluginGuard, performance, closure docs |

## AC1–AC37

All AC items satisfied by production code + tests listed below. Deferred items (AC36 Site Health, Yoast, Elementor public, CPT admission) verified absent.

| AC cluster | Tests / guards |
|---|---|
| AC1–AC6 meta/block | `ExtensionRegistrarTest`, `Tsc6PublicExtensionTest`, reference fixture |
| AC7–AC11 resolver | `Tsc6VisitorResolverTest`, `Tsc6PublicExtensionTest` |
| AC12–AC13 invalidation | `Tsc6PublicExtensionTest` |
| AC14–AC20 registration/failure | `ExtensionRegistrarTest`, `ExtensionRegistrar` Tier A/B, docs Tier C |
| AC21–AC22 security | PluginGuard, provider default false in `ExtensionMetaDefinition` |
| AC23–AC25 SEO | `Tsc6RankMathRegressionTest` |
| AC26–AC28 compatibility | Existing integration tests green; `assert_tsc6_invariants` |
| AC29 black-box | `tests/Fixtures/ReferenceExtension/` |
| AC30 CLI | `ExtensionCli` + unit coverage via registrar diagnostics |
| AC31 docs | `EXTENSION_API_V1.md` |
| AC32–AC36 scope | PluginGuard — no REST/admin/Site Health |
| AC34–AC35 | `Migrator::TARGET = 7`; full CI regression green |
| AC37 | Closure docs (post-merge) |

## Validation (feature branch CI @ `95bc1695f`)

| Gate | Result | Run |
|---|---|---|
| PHPCS | PASS | https://github.com/magpern/ai-multilingual/actions/runs/31744182104 |
| Unit | PASS | same |
| Integration | PASS | same (779 tests) |
| Quality corpus | PASS | same |
| Build/ZIP | PASS | same |
| PluginGuard | PASS (incl. `assert_tsc6_invariants`) | integration job |
| Rank Math regression | PASS | `Tsc6RankMathRegressionTest` |
| Black-box reference extension | PASS | `Tsc6PublicExtensionTest` |
| Performance characterization | PASS | `Tsc6PerformanceRegressionTest` (25 ext / 100 defs) |

## Independent implementation review

**Verdict:** `TSC.6 IMPLEMENTATION REVIEW: PASS`

Falsification checklist (34 points) — no blocking defects:

1. Resolver requires complete source identity (`source_type`, `source_id`, `segment_key`) — PASS  
2. Language code isolation (no DB language ID in public contract) — PASS  
3. TI.7 via `Store::is_publicly_overlay_eligible()` — PASS  
4. No public Store row exposure — PASS  
5. Root extension ownership via manifest + handle — PASS  
6. Namespace theft rejected — PASS  
7. Registry seal after hook — PASS  
8. Late registration rejected — PASS  
9. Tier A: no partial registration on validation failure — PASS  
10. Activation evaluated at seal, cached via closure — PASS  
11. INACTIVE retain (CASE B) via inactive bridge flag — PASS  
12. Uninstall limitation documented in `EXTENSION_API_V1.md` — PASS  
13–15. Failure tiers A/B/C documented and tested — PASS  
16. Public block contract does not expose `TranslatableBlockAdapter` — PASS  
17. HTML via internal structural guard in bridge — PASS  
18. Core block collision rejected — PASS  
19. No public translation write API — PASS  
20. `provider_allowed` default false — PASS  
21. Invalidation marks dirty only (coordinator) — PASS  
22–23. No unsafe CPT/taxonomy or Elementor public filters — PASS  
24–26. Yoast/Site Health not shipped; Rank Math unchanged — PASS  
27–28. Integration API v1 unchanged; no forced migration — PASS  
29. PluginGuard TSC.6 invariants — PASS  
30. Semver/docs complete — PASS  
31. Performance characterization bounded — PASS  
32–34. STATE A, TARGET 7, schema unchanged — PASS  

**Review defects / fixes during loop:**

| Defect | Fix |
|---|---|
| PHPCS dynamic hook constant | Literal `aiml_register_extensions` in `Plugin.php` |
| Unit resolver test wrong base class | Moved to integration suite |
| Performance test meta key collision | Unique keys per definition |
| Black-box resolver used isolated test context | Bind plugin `LanguageContext` via resolver reflection |
| PluginGuard broad-catch false positive | Tier B allowlist for `ExtensionRegistrar.php` |

## Release boundary

- Version **1.3.0** unchanged  
- TARGET **7** unchanged  
- Existing **v1.3.0** tag unchanged  
- No GitHub Release / deploy  
- **Recommend v1.4.0** as next release (adds Extension API v1; completes TSC program)

## Deferred / unsupported backlog (unchanged)

- Public Elementor registration (PX10)  
- CPT/taxonomy public admission filters (PX26)  
- Yoast adapter (PX25)  
- Site Health diagnostics UI  
- Generic overlay registration  
- Translated slugs / SE11  
