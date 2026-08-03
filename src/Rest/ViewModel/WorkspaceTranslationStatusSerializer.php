<?php
/**
 * Maps page status DTOs to REST ViewModels.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rest\ViewModel;

/**
 * Serializer for workspace page status footer.
 */
final class WorkspaceTranslationStatusSerializer {

	/**
	 * Builds the collaborator.
	 *
	 * @param array<string, mixed> $dto Application status DTO.
	 */
	public function from_dto( array $dto ): WorkspaceTranslationStatusViewModel {
		return new WorkspaceTranslationStatusViewModel(
			(int) ( $dto['post_id'] ?? 0 ),
			(string) ( $dto['post_title'] ?? '' ),
			(string) ( $dto['post_status'] ?? '' ),
			(string) ( $dto['post_type'] ?? '' ),
			(int) ( $dto['language_id'] ?? 0 ),
			(int) ( $dto['total_segments'] ?? 0 ),
			(int) ( $dto['missing_count'] ?? 0 ),
			(int) ( $dto['stale_count'] ?? 0 ),
			(int) ( $dto['translated_count'] ?? 0 ),
			(int) ( $dto['reviewed_count'] ?? 0 ),
			(string) ( $dto['overall_state'] ?? 'not_started' ),
			(bool) ( $dto['is_published'] ?? false ),
			(string) ( $dto['edit_link'] ?? '' )
		);
	}
}
