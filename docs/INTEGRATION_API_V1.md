# Integration API v1

Public extension surface for visitor-facing third-party plugin integrations.

**ADR:** [0017-plugin-integration-framework-ownership-and-identity.md](../adr/0017-plugin-integration-framework-ownership-and-identity.md)  
**Plan:** [A1_PLUGIN_INTEGRATION_FRAMEWORK_IMPLEMENTATION_PLAN.md](A1_PLUGIN_INTEGRATION_FRAMEWORK_IMPLEMENTATION_PLAN.md)

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

## Internal (not public API)

Store repositories, SegmentAssembler internals, diagnostics aggregator internals, Jobs tables.
