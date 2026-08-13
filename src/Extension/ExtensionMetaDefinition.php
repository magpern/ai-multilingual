<?php
/**
 * Public registered-meta definition (Extension API v1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

/**
 * Minimal declarative meta facts for third-party extensions.
 */
final class ExtensionMetaDefinition {

	/**
	 * @param string               $namespace         Vendor-owned m: namespace (must be in manifest owned_namespaces).
	 * @param string               $source_type       post|term.
	 * @param string               $meta_key          Exact WordPress meta key.
	 * @param string               $label             OTL label.
	 * @param string               $text_format       plain|html.
	 * @param list<string>|null    $admitted_subtypes Optional post type / taxonomy refine list.
	 * @param bool                 $provider_allowed  Default false.
	 * @param callable():bool|null $activation          Optional activation predicate.
	 */
	public function __construct(
		public readonly string $namespace,
		public readonly string $source_type,
		public readonly string $meta_key,
		public readonly string $label,
		public readonly string $text_format = 'plain',
		public readonly ?array $admitted_subtypes = null,
		public readonly bool $provider_allowed = false,
		public readonly mixed $activation = null,
	) {
	}
}
