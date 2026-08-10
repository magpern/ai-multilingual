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
		$surface    = (string) ( $extracted['surface'] ?? '' );

		$status = Store::STATUS_MISSING;
		$text   = '';
		$stale  = false;

		$review_status              = Store::REVIEW_NOT_SUBMITTED;
		$submitted_translation_hash = '';
		$review_submitted_by        = null;
		$review_submitted_at        = null;
		$reviewed_by                = null;
		$reviewed_at                = null;
		$rejection_reason           = '';
		$rejected_by                = null;
		$rejected_at                = null;
		$publish_status             = Store::PUBLISH_UNPUBLISHED;
		$published_at               = null;
		$published_by               = null;

		if ( null !== $row ) {
			$status = (string) ( $row->status ?? Store::STATUS_MISSING );
			$text   = (string) ( $row->translated_text ?? '' );
			$stale  = (bool) ( (int) ( $row->is_stale ?? 0 ) );

			$review_status              = (string) ( $row->review_status ?? Store::REVIEW_NOT_SUBMITTED );
			$submitted_translation_hash = (string) ( $row->submitted_translation_hash ?? '' );
			$review_submitted_by        = $row->review_submitted_by ?? null;
			$review_submitted_at        = $row->review_submitted_at ?? null;
			$reviewed_by                = $row->reviewed_by ?? null;
			$reviewed_at                = $row->reviewed_at ?? null;
			$rejection_reason           = (string) ( $row->rejection_reason ?? '' );
			$rejected_by                = $row->rejected_by ?? null;
			$rejected_at                = $row->rejected_at ?? null;
			$publish_status             = (string) ( $row->publish_status ?? Store::PUBLISH_UNPUBLISHED );
			$published_at               = $row->published_at ?? null;
			$published_by               = $row->published_by ?? null;
		}

		$can_edit = '' === $block_name || $this->block_registry->is_supported( $block_name );
		if ( 'elementor' === $surface || 'plugin_integration' === $surface ) {
			$can_edit = true;
		}

		$meta = array();
		if ( 'elementor' === $surface ) {
			$meta = array(
				'surface'     => 'elementor',
				'widget_type' => (string) ( $extracted['widget_type'] ?? '' ),
				'element_id'  => (string) ( $extracted['element_id'] ?? '' ),
				'control_key' => (string) ( $extracted['control_key'] ?? '' ),
			);
			if ( ! empty( $extracted['nested_item_id'] ) ) {
				$meta['nested_item_id'] = (string) $extracted['nested_item_id'];
			}
		} elseif ( 'plugin_integration' === $surface ) {
			$meta = array(
				'surface'         => 'plugin_integration',
				'integration_id'  => (string) ( $extracted['integration_id'] ?? '' ),
				'owner_type'      => (string) ( $extracted['owner_type'] ?? '' ),
				'owner_id'        => (string) ( $extracted['owner_id'] ?? '' ),
				'field_label'     => (string) ( $extracted['field_label'] ?? '' ),
				'parent_context'  => (string) ( $extracted['parent_context'] ?? '' ),
				'ownership_class' => (string) ( $extracted['ownership_class'] ?? '' ),
			);
		}

		return array(
			'segment_key'                => $segment_key,
			'field_key'                  => (string) ( $extracted['field_key'] ?? '' ),
			'block_name'                 => $block_name,
			'uuid'                       => (string) ( $extracted['uuid'] ?? '' ),
			'segment_order'              => (int) ( $extracted['segment_order'] ?? 0 ),
			'source_text'                => $source,
			'source_hash'                => $hash,
			'translated_text'            => $text,
			'status'                     => $status,
			'is_stale'                   => $stale,
			'text_format'                => $format,
			'can_edit'                   => $can_edit,
			'segment_kind'               => (string) ( $extracted['segment_kind'] ?? Store::KIND_FIELD ),
			'meta'                       => $meta,
			'review_status'              => $review_status,
			'submitted_translation_hash' => $submitted_translation_hash,
			'review_submitted_by'        => $review_submitted_by,
			'review_submitted_at'        => $review_submitted_at,
			'reviewed_by'                => $reviewed_by,
			'reviewed_at'                => $reviewed_at,
			'rejection_reason'           => $rejection_reason,
			'rejected_by'                => $rejected_by,
			'rejected_at'                => $rejected_at,
			'publish_status'             => $publish_status,
			'published_at'               => $published_at,
			'published_by'               => $published_by,
		);
	}
}
