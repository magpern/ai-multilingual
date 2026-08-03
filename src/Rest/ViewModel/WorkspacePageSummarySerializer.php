<?php
/**
 * Maps page summary DTOs to REST ViewModels.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rest\ViewModel;

/**
 * Serializer for workspace post list rows.
 */
final class WorkspacePageSummarySerializer {

	/**
	 * Builds the collaborator.
	 *
	 * @param array<string, mixed> $dto Application page summary DTO.
	 */
	public function from_dto( array $dto ): WorkspacePageSummaryViewModel {
		return new WorkspacePageSummaryViewModel(
			(int) ( $dto['post_id'] ?? 0 ),
			(string) ( $dto['post_title'] ?? '' ),
			(string) ( $dto['post_type'] ?? '' ),
			(string) ( $dto['post_status'] ?? '' ),
			(string) ( $dto['modified_gmt'] ?? '' ),
			(int) ( $dto['language_id'] ?? 0 ),
			(int) ( $dto['total_segments'] ?? 0 ),
			(int) ( $dto['stale_count'] ?? 0 )
		);
	}

	/**
	 * Operation handler.
	 *
	 * @param array<int, array<string, mixed>> $dtos Application DTO list.
@return array<int, array<string, mixed>>
	 */
	public function many_to_arrays( array $dtos ): array {
		$out = array();
		foreach ( $dtos as $dto ) {
			$out[] = $this->from_dto( $dto )->to_array();
		}

		return $out;
	}
}
