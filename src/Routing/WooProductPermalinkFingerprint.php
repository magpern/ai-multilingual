<?php
/**
 * Route-semantic Woo product permalink fingerprint (MSEO.4 A4).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Routing;

/**
 * Hashes only configuration that alters product permalink identity.
 */
final class WooProductPermalinkFingerprint {

	public const SCHEMA_VERSION = 1;

	/**
	 * Builds the normalized payload used for hashing.
	 *
	 * @return array<string, mixed>
	 */
	public function payload(): array {
		$product_base = '';
		$placeholder  = false;

		if ( function_exists( 'wc_get_permalink_structure' ) ) {
			$structure = wc_get_permalink_structure();
			if ( is_array( $structure ) && isset( $structure['product_base'] ) ) {
				$product_base = (string) $structure['product_base'];
			} elseif ( is_object( $structure ) && isset( $structure->product_base ) ) {
				$product_base = (string) $structure->product_base;
			}
		}
		if ( '' === $product_base ) {
			foreach ( array( 'woocommerce_permalinks', 'woocommerce_permalink_structure' ) as $option ) {
				$permalinks = get_option( $option, array() );
				if ( is_array( $permalinks ) && isset( $permalinks['product_base'] ) ) {
					$product_base = (string) $permalinks['product_base'];
					if ( '' !== $product_base ) {
						break;
					}
				}
			}
		}

		$product_base = '/' . trim( strtolower( $product_base ), '/' );
		$placeholder  = str_contains( $product_base, '%product_cat%' );

		$wp_structure = (string) get_option( 'permalink_structure', '' );

		return array(
			'schema_version'               => self::SCHEMA_VERSION,
			'product_permalink_structure'  => $product_base,
			'product_category_placeholder' => $placeholder,
			'relevant_wp_permalink_shape'  => $wp_structure,
		);
	}

	/**
	 * Stable hash of the normalized payload.
	 */
	public function hash(): string {
		$json = wp_json_encode( $this->payload() );

		return hash( 'sha256', is_string( $json ) ? $json : '' );
	}
}
