<?php
/**
 * Keyed WP meta reader for registered fields (TSC.2).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Surface\Meta;

use AIMultilingual\Translation\Store;

/**
 * Exact-key meta reads only. Never fetches all meta.
 */
final class RegisteredMetaReader {

	/**
	 * Read a scalar string meta value by exact key.
	 *
	 * @param string $source_type post|term.
	 * @param int    $source_id   Owner id.
	 * @param string $meta_key    Exact key.
	 * @return string Empty string when missing/non-scalar.
	 */
	public function read( string $source_type, int $source_id, string $meta_key ): string {
		if ( $source_id <= 0 || '' === $meta_key ) {
			return '';
		}
		if ( Store::SOURCE_POST === $source_type ) {
			if ( ! function_exists( 'get_post_meta' ) ) {
				return '';
			}
			$raw = get_post_meta( $source_id, $meta_key, true );
		} elseif ( Store::SOURCE_TERM === $source_type ) {
			if ( ! function_exists( 'get_term_meta' ) ) {
				return '';
			}
			$raw = get_term_meta( $source_id, $meta_key, true );
		} else {
			return '';
		}

		if ( ! is_string( $raw ) && ! is_numeric( $raw ) ) {
			return '';
		}
		return (string) $raw;
	}
}
