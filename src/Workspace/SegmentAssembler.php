<?php
/**
 * Extract, sync, merge, and order workspace segments.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace;

use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use WP_Post;

/**
 * Owns extract → sync → load → merge → order → source_hash assembly.
 */
final class SegmentAssembler {

	/**
	 * Injected dependency.
	 *
	 * @var Extractor
	 */
	private Extractor $extractor;

	/**
	 * Injected dependency.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Injected dependency.
	 *
	 * @var BlockRegistry
	 */
	private BlockRegistry $block_registry;

	/**
	 * Builds the collaborator.
	 *
	 * @param Extractor     $extractor      Source extractor.
	 * @param Store         $store          Segment store.
	 * @param BlockRegistry $block_registry Block allowlist policy.
	 */
	public function __construct( Extractor $extractor, Store $store, BlockRegistry $block_registry ) {
		$this->extractor      = $extractor;
		$this->store          = $store;
		$this->block_registry = $block_registry;
	}

	/**
	 * Loads merged segment DTOs for one post and language.
	 *
	 * @param WP_Post $post        Canonical post.
	 * @param int     $language_id Target language id.
	 * @return array<int, array<string, mixed>>
	 */
	public function assemble_for_post( WP_Post $post, int $language_id ): array {
		$extracted = $this->extractor->extract( $post );

		$this->store->sync_source(
			Store::SOURCE_POST,
			(int) $post->ID,
			(string) $post->post_type,
			$extracted
		);

		$stored = $this->store->load_object( Store::SOURCE_POST, (int) $post->ID, $language_id );

		$dtos = array();
		foreach ( $extracted as $segment_key => $segment ) {
			$dtos[] = $this->merge_segment( $segment_key, $segment, $stored[ $segment_key ] ?? null );
		}

		usort(
			$dtos,
			static function ( array $left, array $right ): int {
				$order = ( (int) ( $left['segment_order'] ?? 0 ) ) <=> ( (int) ( $right['segment_order'] ?? 0 ) );
				if ( 0 !== $order ) {
					return $order;
				}

				return strcmp( (string) ( $left['segment_key'] ?? '' ), (string) ( $right['segment_key'] ?? '' ) );
			}
		);

		return $dtos;
	}

	/**
	 * Returns one merged segment DTO after sync, or null when absent from extraction.
	 *
	 * @param WP_Post $post        Canonical post.
	 * @param int     $language_id Target language id.
	 * @param string  $segment_key Segment key.
	 * @return array<string, mixed>|null
	 */
	public function assemble_one( WP_Post $post, int $language_id, string $segment_key ): ?array {
		foreach ( $this->assemble_for_post( $post, $language_id ) as $dto ) {
			if ( (string) ( $dto['segment_key'] ?? '' ) === $segment_key ) {
				return $dto;
			}
		}

		return null;
	}

	/**
	 * Operation handler.
	 *
	 * @param string               $segment_key Segment key.
	 * @param array<string, mixed> $extracted   Extracted segment.
	 * @param object|null          $row         Stored row when present.
	 * @return array<string, mixed>
	 */
	private function merge_segment( string $segment_key, array $extracted, ?object $row ): array {
		$block_name = (string) ( $extracted['block_name'] ?? '' );
		$source     = (string) ( $extracted['source_text'] ?? '' );
		$format     = (string) ( $extracted['text_format'] ?? Store::FORMAT_PLAIN );
		$hash       = (string) ( $extracted['source_hash'] ?? Store::source_hash( $source, $format ) );

		$status = Store::STATUS_MISSING;
		$text   = '';
		$stale  = false;

		if ( null !== $row ) {
			$status = (string) ( $row->status ?? Store::STATUS_MISSING );
			$text   = (string) ( $row->translated_text ?? '' );
			$stale  = (bool) ( (int) ( $row->is_stale ?? 0 ) );
		}

		return array(
			'segment_key'     => $segment_key,
			'field_key'       => (string) ( $extracted['field_key'] ?? '' ),
			'block_name'      => $block_name,
			'uuid'            => (string) ( $extracted['uuid'] ?? '' ),
			'segment_order'   => (int) ( $extracted['segment_order'] ?? 0 ),
			'source_text'     => $source,
			'source_hash'     => $hash,
			'translated_text' => $text,
			'status'          => $status,
			'is_stale'        => $stale,
			'text_format'     => $format,
			'can_edit'        => '' === $block_name || $this->block_registry->is_supported( $block_name ),
			'segment_kind'    => (string) ( $extracted['segment_kind'] ?? Store::KIND_FIELD ),
			'meta'            => array(),
		);
	}
}
