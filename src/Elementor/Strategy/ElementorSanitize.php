<?php
/**
 * Shared overlay sanitization for Elementor strategies.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor\Strategy;

use AIMultilingual\Elementor\ElementorControlRegistry;

/**
 * Deterministic sanitization helpers.
 */
final class ElementorSanitize {

	/**
	 * Sanitize overlay text for a control.
	 *
	 * @param string $text     Translated text.
	 * @param string $strategy Sanitization strategy.
	 */
	public static function apply( string $text, string $strategy ): string {
		if ( ElementorControlRegistry::SANITIZE_HTML === $strategy ) {
			return function_exists( 'wp_kses_post' ) ? wp_kses_post( $text ) : $text;
		}

		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $text ) : trim( $text );
	}
}
