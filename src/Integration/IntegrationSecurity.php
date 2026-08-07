<?php
/**
 * Integration security helpers (Integration API v1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration;

/**
 * Bounded sanitization helpers for integration source/overlay plain text.
 *
 * Integrations may call these; they must not introduce untrusted callbacks.
 */
final class IntegrationSecurity {

	/**
	 * Sanitize a plain-text visitor-facing value for Store/overlay use.
	 *
	 * @param string $text Raw text.
	 */
	public static function sanitize_plain( string $text ): string {
		if ( function_exists( 'wp_check_invalid_utf8' ) ) {
			$text = wp_check_invalid_utf8( $text );
		}
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$text = wp_strip_all_tags( $text );
		}
		return trim( $text );
	}

	/**
	 * Whether a registration payload looks like forbidden serialized callback data.
	 *
	 * @param mixed $value Candidate registration data.
	 */
	public static function looks_like_serialized_callback( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}
		// Block PHP serialized objects / callables commonly used for DB-stored hooks.
		return 1 === preg_match( '/^([OCad]:\d+:|a:\d+:\{.*s:\d+:\"(s:\d+:\"|i:\d+;))/s', $value )
			|| false !== strpos( $value, 'O:8:"Closure"' );
	}
}
