<?php
/**
 * REST presentation contract for workspace post list entries.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rest\ViewModel;

/**
 * Workspace page summary ViewModel v1.
 */
final class WorkspacePageSummaryViewModel {

	/**
	 * Builds the collaborator.
	 *
	 * @param int    $post_id        Post id.
	 * @param string $post_title     Post title.
	 * @param string $post_type      Post type.
	 * @param string $post_status    Post status.
	 * @param string $modified_gmt   Modified timestamp.
	 * @param int    $language_id    Summary language id.
	 * @param int    $total_segments Segment count.
	 * @param int    $stale_count    Stale segment count.
	 */
	public function __construct(
		public readonly int $post_id,
		public readonly string $post_title,
		public readonly string $post_type,
		public readonly string $post_status,
		public readonly string $modified_gmt,
		public readonly int $language_id,
		public readonly int $total_segments,
		public readonly int $stale_count
	) {
	}

	/**
	 * Serializes the ViewModel to REST v1 JSON.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'post_id'        => $this->post_id,
			'post_title'     => $this->post_title,
			'post_type'      => $this->post_type,
			'post_status'    => $this->post_status,
			'modified_gmt'   => $this->modified_gmt,
			'language_id'    => $this->language_id,
			'total_segments' => $this->total_segments,
			'stale_count'    => $this->stale_count,
		);
	}
}
