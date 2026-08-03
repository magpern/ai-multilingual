<?php
/**
 * REST presentation contract for page-level workspace status.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rest\ViewModel;

/**
 * Workspace translation status ViewModel v1.
 */
final class WorkspaceTranslationStatusViewModel {

	/**
	 * Builds the collaborator.
	 *
	 * @param int    $post_id          Post id.
	 * @param string $post_title       Post title.
	 * @param string $post_status      Post status.
	 * @param string $post_type        Post type.
	 * @param int    $language_id      Language id.
	 * @param int    $total_segments   Total segments.
	 * @param int    $missing_count    Missing count.
	 * @param int    $stale_count      Stale count.
	 * @param int    $translated_count Translated count.
	 * @param int    $reviewed_count   Reviewed count.
	 * @param string $overall_state    Aggregate state.
	 * @param bool   $is_published     Whether post is published.
	 * @param string $edit_link        WP editor link.
	 */
	public function __construct(
		public readonly int $post_id,
		public readonly string $post_title,
		public readonly string $post_status,
		public readonly string $post_type,
		public readonly int $language_id,
		public readonly int $total_segments,
		public readonly int $missing_count,
		public readonly int $stale_count,
		public readonly int $translated_count,
		public readonly int $reviewed_count,
		public readonly string $overall_state,
		public readonly bool $is_published,
		public readonly string $edit_link
	) {
	}

	/**
	 * Serializes the ViewModel to REST v1 JSON.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'post_id'          => $this->post_id,
			'post_title'       => $this->post_title,
			'post_status'      => $this->post_status,
			'post_type'        => $this->post_type,
			'language_id'      => $this->language_id,
			'total_segments'   => $this->total_segments,
			'missing_count'    => $this->missing_count,
			'stale_count'      => $this->stale_count,
			'translated_count' => $this->translated_count,
			'reviewed_count'   => $this->reviewed_count,
			'overall_state'    => $this->overall_state,
			'is_published'     => $this->is_published,
			'edit_link'        => $this->edit_link,
		);
	}
}
