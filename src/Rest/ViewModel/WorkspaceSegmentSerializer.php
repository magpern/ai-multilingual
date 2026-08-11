<?php
/**
 * Maps application segment DTOs to REST ViewModels.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rest\ViewModel;

/**
 * Serializer for workspace segment rows.
 */
final class WorkspaceSegmentSerializer {

	/**
	 * Builds the collaborator.
	 *
	 * @param array<string, mixed> $dto Application segment DTO.
	 */
	public function from_dto( array $dto ): WorkspaceSegmentViewModel {
		$review_submitted_by = $dto['review_submitted_by'] ?? null;
		$review_submitted_at = $dto['review_submitted_at'] ?? null;
		$reviewed_by         = $dto['reviewed_by'] ?? null;
		$reviewed_at         = $dto['reviewed_at'] ?? null;
		$rejected_by         = $dto['rejected_by'] ?? null;
		$rejected_at         = $dto['rejected_at'] ?? null;
		$published_by        = $dto['published_by'] ?? null;
		$published_at        = $dto['published_at'] ?? null;

		return new WorkspaceSegmentViewModel(
			(string) ( $dto['segment_key'] ?? '' ),
			(string) ( $dto['field_key'] ?? '' ),
			(string) ( $dto['block_name'] ?? '' ),
			(string) ( $dto['uuid'] ?? '' ),
			(int) ( $dto['segment_order'] ?? 0 ),
			(string) ( $dto['source_text'] ?? '' ),
			(string) ( $dto['source_hash'] ?? '' ),
			(string) ( $dto['translated_text'] ?? '' ),
			(string) ( $dto['status'] ?? '' ),
			(bool) ( $dto['is_stale'] ?? false ),
			(string) ( $dto['text_format'] ?? '' ),
			(bool) ( $dto['can_edit'] ?? false ),
			is_array( $dto['meta'] ?? null ) ? $dto['meta'] : array(),
			(string) ( $dto['review_status'] ?? 'not_submitted' ),
			(string) ( $dto['translation_hash'] ?? '' ),
			(string) ( $dto['submitted_translation_hash'] ?? '' ),
			null === $review_submitted_by ? null : (int) $review_submitted_by,
			null === $review_submitted_at ? null : (string) $review_submitted_at,
			null === $reviewed_by ? null : (int) $reviewed_by,
			null === $reviewed_at ? null : (string) $reviewed_at,
			(string) ( $dto['rejection_reason'] ?? '' ),
			null === $rejected_by ? null : (int) $rejected_by,
			null === $rejected_at ? null : (string) $rejected_at,
			(string) ( $dto['publish_status'] ?? 'unpublished' ),
			null === $published_at ? null : (string) $published_at,
			null === $published_by ? null : (int) $published_by
		);
	}

	/**
	 * Operation handler.
	 *
	 * @param array<int, array<string, mixed>> $dtos Application DTO list.
	 * @return array<int, array<string, mixed>>
	 */
	public function many_to_arrays( array $dtos ): array {
		$out = array();
		foreach ( $dtos as $dto ) {
			$out[] = $this->from_dto( $dto )->to_array();
		}

		return $out;
	}
}
