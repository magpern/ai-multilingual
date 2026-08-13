# TSC.6 Implementation Baseline

**Branch:** `feature/tsc6-public-extension-seo-stabilization`
**Status:** Implementation **IN PROGRESS**

## Freeze anchors

| Field | Value |
|---|---|
| Starting main SHA | `df40c00e81395fef857db40748f5e75380b51899` |
| Frozen plan | [TSC6_PUBLIC_EXTENSION_SEO_STABILIZATION_IMPLEMENTATION_PLAN.md](TSC6_PUBLIC_EXTENSION_SEO_STABILIZATION_IMPLEMENTATION_PLAN.md) @ `df40c00e81395fef857db40748f5e75380b51899` |
| ADR-0022 | [0022-public-extension-boundary-and-registration-lifecycle.md](../adr/0022-public-extension-boundary-and-registration-lifecycle.md) — **Accepted** |
| Plugin version | **1.3.0** |
| `Migrator::TARGET` | **7** |
| Schema | **STATE A** — no migration |

## Matrices

| Matrix | Range |
|---|---|
| PX | PX1–PX31 |
| AC | AC1–AC37 |
| WP | TSC6.0–TSC6.7 |

## Committed public API (Extension API v1)

- `aiml_register_extensions` hook
- `ExtensionRegistrar`, `ExtensionManifest`, `RegisteredExtension`
- `ExtensionMetaDefinition`
- `ExtensionBlockAdapter` (narrow public contract)
- `SourceSegmentReference`, `LanguageReference`, `ResolvedTranslation`
- `VisitorTranslationResolver`
- `aiml_mark_source_dirty()`
- WP-CLI: `wp aiml extensions list`, `wp aiml extensions status <extension_id>`

## Retained (unchanged)

- Integration API v1 (`aiml_register_integrations`)
- Internal registries (`Store`, `RegisteredMetaRegistry`, `AdapterRegistry`, `SurfaceRegistry`, etc.)

## Deferred / unsupported (no implementation)

- Public Elementor registration (PX10)
- CPT/taxonomy slug-only admission filters (PX26)
- Yoast adapter (PX25)
- Site Health diagnostics UI
- Generic overlay registration
- Public writes / Store access
- Durable registration table

## STOP conditions

- Schema / TARGET bump without authorization
- Public Store row exposure
- Unsafe CPT admission filter
- Public Elementor widget API
- Forced migration of internal integrations through ExtensionRegistrar
- Release/tag/deploy in this milestone task

## Exact next step

Implement TSC6.0–TSC6.7 on this branch per the frozen plan and ADR-0022.
