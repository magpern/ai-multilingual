<?php
/**
 * Production Fluent Forms definition reader (read-only).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration\FluentForms;

/**
 * Loads form_fields via wpFluent() without writes.
 */
final class WpFluentFormDefinitionReader implements FluentFormDefinitionReader {

	/**
	 * Load decoded form_fields for a Fluent Forms form ID.
	 *
	 * @param int $form_id Fluent Forms form ID.
	 * @return array<string, mixed>|null
	 */
	public function get_decoded_fields( int $form_id ): ?array {
		if ( $form_id <= 0 || ! function_exists( 'wpFluent' ) ) {
			return null;
		}

		try {
			$row = wpFluent()->table( 'fluentform_forms' )->find( $form_id );
		} catch ( \RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			return null;
		}

		if ( ! is_object( $row ) || ! isset( $row->form_fields ) ) {
			return null;
		}

		$decoded = json_decode( (string) $row->form_fields, true );
		return is_array( $decoded ) ? $decoded : null;
	}
}
