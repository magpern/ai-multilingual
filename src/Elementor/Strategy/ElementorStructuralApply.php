<?php
/**
 * Structural-safe overlay apply for Elementor HTML controls.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor\Strategy;

use AIMultilingual\Elementor\ElementorControlRegistry;
use AIMultilingual\Elementor\ElementorDiagnostics;
use AIMultilingual\Translation\Safety\StructuralAttributeGuard;

/**
 * Sanitize + structural guard for Elementor overlay values.
 */
final class ElementorStructuralApply {

	/**
	 * Apply sanitized translation with HTML structural guard when required.
	 *
	 * @param string                    $source_value  Canonical source string.
	 * @param string                    $translated    Translated text from Store.
	 * @param string                    $sanitize      Sanitization strategy.
	 * @param ElementorDiagnostics|null $diagnostics   Optional diagnostics.
	 */
	public static function apply(
		string $source_value,
		string $translated,
		string $sanitize,
		?ElementorDiagnostics $diagnostics = null
	): string {
		$candidate = ElementorSanitize::apply( $translated, $sanitize );

		if ( ElementorControlRegistry::SANITIZE_HTML !== $sanitize ) {
			return $candidate;
		}

		if ( StructuralAttributeGuard::preserves_structure( $source_value, $candidate ) ) {
			return $candidate;
		}

		$diagnostics?->inc( 'structural_rejected' );

		return $source_value;
	}
}
