<?php
/**
 * Batch Store lookups for Elementor overlays.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

use AIMultilingual\Translation\Store;

/**
 * Resolves translated control values from the Store for one document+language.
 */
final class ElementorOverlayResolver {

	/**
	 * Builds the resolver.
	 *
	 * @param Store                     $store        Translation store.
	 * @param ElementorDiagnostics|null $diagnostics Optional diagnostics.
	 */
	public function __construct(
		private Store $store,
		private ?ElementorDiagnostics $diagnostics = null
	) {}

	/**
	 * Map segment_key => translated text for renderable non-empty overlays.
	 *
	 * @param int   $post_id     Owner post.
	 * @param int   $language_id Language.
	 * @param array $units       Translation units.
	 * @return array<string, string>
	 */
	public function resolve( int $post_id, int $language_id, array $units ): array {
		if ( $language_id <= 0 || array() === $units ) {
			return array();
		}

		$loaded = $this->store->load_object( Store::SOURCE_POST, $post_id, $language_id );
		$out    = array();

		foreach ( $units as $unit ) {
			if ( ! $unit instanceof ElementorTranslationUnit ) {
				$this->diagnostics?->inc( 'source_fallback' );
				continue;
			}

			$row = $loaded[ $unit->segment_key ] ?? null;
			if ( null === $row ) {
				$this->diagnostics?->inc( 'store_miss' );
				continue;
			}

			$this->diagnostics?->inc( 'store_hit' );

			if ( ! empty( $row->is_stale ) ) {
				$this->diagnostics?->inc( 'stale_translation' );
			}

			$status = (string) ( $row->status ?? '' );
			if ( ! in_array( $status, Store::RENDERABLE_STATUSES, true ) ) {
				$this->diagnostics?->inc( 'source_fallback' );
				continue;
			}

			$text = (string) ( $row->translated_text ?? '' );
			if ( '' === trim( $text ) ) {
				$this->diagnostics?->inc( 'source_fallback' );
				continue;
			}

			$out[ $unit->segment_key ] = $text;
		}

		return $out;
	}
}
