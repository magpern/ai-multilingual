<?php
/**
 * Converts public ExtensionMetaDefinition to internal RegisteredMetaDefinition.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

use AIMultilingual\Surface\Meta\RegisteredMetaDefinition;
use AIMultilingual\Translation\Store;

/**
 * Internal bridge preserving TSC.2 invariants for public meta registrations.
 */
final class ExtensionMetaBridge {

	/**
	 * Converts a public meta definition to the internal catalog shape.
	 *
	 * @param ExtensionMetaDefinition $definition Public definition.
	 * @param bool                    $active     Cached activation result.
	 */
	public static function to_internal( ExtensionMetaDefinition $definition, bool $active ): RegisteredMetaDefinition {
		$activation = static fn (): bool => $active;

		return new RegisteredMetaDefinition(
			namespace: $definition->namespace,
			source_type: $definition->source_type,
			meta_key: $definition->meta_key,
			segment_key_mode: RegisteredMetaDefinition::MODE_NATIVE_M,
			label: $definition->label,
			admitted_subtypes: $definition->admitted_subtypes,
			extract_store_capable: true,
			provider_allowed: $definition->provider_allowed,
			overlay_capable: false,
			overlay_resolver_ownership: RegisteredMetaDefinition::OVERLAY_NONE,
			activation: $activation,
			value_type: RegisteredMetaDefinition::VALUE_SCALAR,
			text_format: self::normalize_format( $definition->text_format ),
		);
	}

	/**
	 * Normalizes declared text format to Store constants.
	 *
	 * @param string $format Declared text format.
	 */
	private static function normalize_format( string $format ): string {
		return 'html' === $format ? Store::FORMAT_HTML : Store::FORMAT_PLAIN;
	}
}
