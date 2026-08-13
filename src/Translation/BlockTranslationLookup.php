<?php
/**
 * Strategy F store-backed block translation lookup.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;

/**
 * Loads renderable block translations scoped to one source object.
 *
 * Store queries live here — not in adapters or {@see BlockRenderer}.
 */
final class BlockTranslationLookup {

	/**
	 * Provenance states eligible for frontend rendering.
	 *
	 * @var list<string>
	 */
	private const RENDERABLE_STATUSES = array(
		Store::STATUS_MACHINE_TRANSLATED,
		Store::STATUS_MANUALLY_EDITED,
		Store::STATUS_REVIEWED,
	);

	/**
	 * Builds the lookup service.
	 *
	 * @param Store $store Segment store.
	 */
	public function __construct(
		private Store $store,
	) {
	}

	/**
	 * Loads block translations for one post in one target language.
	 *
	 * Lookup identity is (source_type, source_id, language_id) plus segment_key.
	 * No global segment-key lookup is performed.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source object id.
	 * @param int    $language_id Target language id.
	 */
	public function for_post( string $source_type, int $source_id, int $language_id ): BlockTranslationLookupResult {
		if ( $source_id <= 0 || $language_id <= 0 ) {
			return new BlockTranslationLookupResult( false, failure_reason: 'invalid_scope' );
		}

		$rows = $this->store->load_object( $source_type, $source_id, $language_id );

		$translations  = array();
		$segment_count = 0;
		$translated    = 0;
		$rejected      = 0;

		foreach ( $rows as $segment_key => $row ) {
			++$segment_count;

			if ( ! is_string( $segment_key ) || ! SegmentKey::is_valid_format( $segment_key ) ) {
				++$rejected;
				continue;
			}

			$parsed = SegmentKey::parse( $segment_key );
			if ( null === $parsed || ! Contract::is_supported_field( $parsed['field'] ) ) {
				++$rejected;
				continue;
			}

			if ( Store::KIND_BLOCK !== (string) ( $row->segment_kind ?? '' ) ) {
				++$rejected;
				continue;
			}

			if ( (int) ( $row->source_id ?? 0 ) !== $source_id ) {
				++$rejected;
				continue;
			}

			if ( ! empty( $row->is_stale ) ) {
				++$rejected;
				continue;
			}

			$status = (string) ( $row->status ?? '' );
			if ( Store::STATUS_IGNORED === $status || ! in_array( $status, self::RENDERABLE_STATUSES, true ) ) {
				++$rejected;
				continue;
			}

			$value = (string) ( $row->translated_text ?? '' );
			if ( '' === trim( $value ) ) {
				++$rejected;
				continue;
			}

			if ( ! Store::is_publicly_overlay_eligible( $row ) ) {
				++$rejected;
				continue;
			}

			if ( array_key_exists( $segment_key, $translations ) ) {
				return new BlockTranslationLookupResult(
					false,
					segment_count: $segment_count,
					rejected_count: $rejected + 1,
					failure_reason: 'duplicate_segment_key'
				);
			}

			$translations[ $segment_key ] = $value;
			++$translated;
		}

		return new BlockTranslationLookupResult(
			true,
			$translations,
			$segment_count,
			$translated,
			$rejected
		);
	}
}
