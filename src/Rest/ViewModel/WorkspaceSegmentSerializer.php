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
			is_array( $dto['meta'] ?? null ) ? $dto['meta'] : array()
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
