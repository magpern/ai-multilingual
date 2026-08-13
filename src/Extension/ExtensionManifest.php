<?php
/**
 * Root extension ownership manifest (Extension API v1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

/**
 * Immutable extension ownership facts declared once per extension plugin.
 */
final class ExtensionManifest {

	/**
	 * Declares extension identity and owned namespace tokens.
	 *
	 * @param string               $extension_id      Stable lowercase extension id.
	 * @param string               $version           Semver string.
	 * @param list<string>         $owned_namespaces  m: namespace tokens owned by this extension.
	 * @param array<string,string> $requires_plugins  Optional slug => min version (diagnostics only).
	 */
	public function __construct( // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- list<string> documents owned_namespaces shape.
		public readonly string $extension_id,
		public readonly string $version,
		public readonly array $owned_namespaces,
		public readonly array $requires_plugins = array(),
	) {
	}
}
