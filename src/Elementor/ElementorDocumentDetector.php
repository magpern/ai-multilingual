<?php
/**
 * Detect Elementor-managed WordPress documents.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

/**
 * Deterministic Elementor document detection (read-only meta).
 */
final class ElementorDocumentDetector {

	/**
	 * Whether the post is an Elementor-managed builder document.
	 *
	 * @param int $post_id Post ID.
	 */
	public function is_elementor_document( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		return $this->is_elementor_payload(
			$this->raw_elementor_data( $post_id ),
			$this->edit_mode( $post_id )
		);
	}

	/**
	 * Pure payload check (unit-testable without WordPress meta).
	 *
	 * @param string $data Elementor data JSON or empty.
	 * @param string $mode Edit mode meta or empty.
	 */
	public function is_elementor_payload( string $data, string $mode = '' ): bool {
		if ( '' !== $data ) {
			return true;
		}

		return '' !== $mode;
	}

	/**
	 * Raw `_elementor_data` string or empty.
	 *
	 * @param int $post_id Post ID.
	 */
	public function raw_elementor_data( int $post_id ): string {
		$raw = get_post_meta( $post_id, Contract::META_DATA, true );
		return is_string( $raw ) ? $raw : '';
	}

	/**
	 * Edit mode meta or empty.
	 *
	 * @param int $post_id Post ID.
	 */
	public function edit_mode( int $post_id ): string {
		$mode = get_post_meta( $post_id, Contract::META_EDIT_MODE, true );
		return is_string( $mode ) ? $mode : '';
	}

	/**
	 * Decoded Elementor elements tree or null on failure.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>|null
	 */
	public function decode_elements( int $post_id ): ?array {
		return $this->decode_raw( $this->raw_elementor_data( $post_id ) );
	}

	/**
	 * Decode raw Elementor JSON (never mutates source).
	 *
	 * @param string $raw Raw JSON.
	 * @return array<int, array<string, mixed>>|null
	 */
	public function decode_raw( string $raw ): ?array {
		if ( '' === $raw ) {
			return null;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) && function_exists( 'wp_unslash' ) ) {
			$decoded = json_decode( wp_unslash( $raw ), true );
		}

		if ( ! is_array( $decoded ) ) {
			return null;
		}

		return $decoded;
	}
}
