<?php
/**
 * Strategy F translated block content sanitizer.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

/**
 * Applies the approved HTML allowlist to translated field content.
 *
 * Uses the same {@see wp_kses_post()} policy as the admin editor for HTML
 * fields. Unsafe markup is rejected rather than partially rendered.
 */
final class BlockTranslationSanitizer {

	/**
	 * Sanitizes one translated content value.
	 *
	 * @param string $value Raw translated content from storage.
	 */
	public function sanitize( string $value ): ?string {
		if ( '' === trim( $value ) ) {
			return null;
		}

		if ( ! function_exists( 'wp_kses_post' ) ) {
			return null;
		}

		$sanitized = wp_kses_post( $value );

		if ( '' === trim( $sanitized ) || $sanitized !== $value ) {
			return null;
		}

		return $sanitized;
	}

	/**
	 * Sanitizes a translation map, dropping rejected entries.
	 *
	 * @param array<string, string>                           $translations Segment key to raw content.
	 * @param callable(string, array<string,mixed>):void|null $on_reject Optional reject callback.
	 * @return array<string, string>
	 */
	public function sanitize_map( array $translations, ?callable $on_reject = null ): array {
		$clean = array();

		foreach ( $translations as $segment_key => $value ) {
			$sanitized = $this->sanitize( (string) $value );

			if ( null === $sanitized ) {
				if ( null !== $on_reject ) {
					$on_reject(
						'block_translation_rejected',
						array(
							'segment_key' => $segment_key,
							'reason'      => 'sanitization_failed',
						)
					);
				}
				continue;
			}

			$clean[ $segment_key ] = $sanitized;
		}

		return $clean;
	}
}
