<?php
/**
 * Reference custom block adapter (Extension API v1 black-box proof).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Fixtures\ReferenceExtension;

use AIMultilingual\Extension\Block\ExtensionBlockAdapter;

/**
 * Minimal custom block adapter for reference extension tests.
 */
final class ReferenceBlockAdapter implements ExtensionBlockAdapter {

	/**
	 * {@inheritDoc}
	 */
	public function get_block_names(): array {
		return array( ReferenceExtensionBootstrap::BLOCK_NAME );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_supported_fields(): array {
		return array( 'message' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_translatable_instance( array $block ): bool {
		return ReferenceExtensionBootstrap::BLOCK_NAME === (string) ( $block['blockName'] ?? '' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function extract_field( array $block, string $field_id ): ?string {
		if ( 'message' !== $field_id ) {
			return null;
		}
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$value = (string) ( $attrs['message'] ?? '' );
		return '' !== trim( $value ) ? $value : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function apply_field( array $block, string $field_id, string $translated_text ): array {
		if ( 'message' !== $field_id ) {
			return $block;
		}
		if ( ! is_array( $block['attrs'] ?? null ) ) {
			$block['attrs'] = array();
		}
		$block['attrs']['message'] = $translated_text;
		return $block;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_text_format( string $field_id ): string {
		return 'plain';
	}
}
