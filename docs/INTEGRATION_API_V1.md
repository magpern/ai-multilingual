# Integration API v1

Public extension surface for visitor-facing third-party plugin integrations.

**ADR:** [0017-plugin-integration-framework-ownership-and-identity.md](adr/0017-plugin-integration-framework-ownership-and-identity.md)  
**Plan:** [A1_PLUGIN_INTEGRATION_FRAMEWORK_IMPLEMENTATION_PLAN.md](plans/A1_PLUGIN_INTEGRATION_FRAMEWORK_IMPLEMENTATION_PLAN.md)  
**Related:** [Extension API v1](EXTENSION_API_V1.md) — separate hook (`aiml_register_extensions`) for registered meta, block adapters, and resolver; Integration API v1 remains authoritative for `p:` integrations.

## Register

```php
add_action( 'aiml_register_integrations', static function ( \AIMultilingual\Integration\IntegrationRegistry $registry ): void {
	$registry->register( new MyIntegration( new \AIMultilingual\Integration\Identity\PluginIdentity() ) );
} );
```

Requirements:

- Code-owned typed `PluginIntegrationInterface` implementations only
- Immutable lowercase `integration_id`
- Integration API version `v1`
- No database / serialized callbacks

## Identity

Framework serializer builds keys:

```text
p:<integration_id>:<owner_type>:<owner_id>:<field>[:<nested>...]
```

Integrations must call `PluginIdentity::build()` — never concatenate arbitrary keys.

## Lifecycle

Compatibility states: `available`, `unavailable`, `compatible`, `unsupported_version`, `missing_required_hook`, `disabled`, `degraded`.

Overlays apply only when compatibility allows overlay. Store history is retained when integrations disable.

## Local failure

Failing units fall back to source; remaining output continues.

## Chrome-owned private CPT surfaces (M5-A / 1.7.0)

Optional companion interface for site-wide visitor chrome (announcement bars and peers):

```php
interface \AIMultilingual\Integration\DeclaresChromeOwnedSurfaces {
	/** @return list<\AIMultilingual\Integration\ChromeOwnedSurfaceDeclaration> */
	public function get_chrome_owned_surfaces(): array;
}
```

`ChromeOwnedSurfaceDeclaration` declares `post_type`, owner-type tokens, field allowlist, and extraction mode `integration_units_only`.

Lifecycle:

1. Register on `aiml_register_integrations` as today.
2. AIML collects declarations; **validates after CPT registration** (normally post-`init`).
3. Invalid declaration → disable **that** chrome-surface declaration, emit authorized diagnostic (`chrome_declaration_disabled`), continue all other declarations/integrations.
4. Activated surfaces appear in Workspace/Jobs for operators with `edit_post`; extract **declared `p:` fields only** (no natives/blocks/Elementor/meta).
5. Visitor resolve uses Extension `VisitorTranslationResolver` (host-independent) — **not** `register_output_hooks` / FrontendBridge.

AIML does **not** flip CPT `public` / REST / rewrite / archives / permalinks. No `aiml_admitted_post_types` filter.

See [ADR-0025](adr/0025-integration-owned-private-cpt-chrome-admission.md).

## Internal (not public API)

Store repositories, SegmentAssembler internals, diagnostics aggregator internals, Jobs tables.
