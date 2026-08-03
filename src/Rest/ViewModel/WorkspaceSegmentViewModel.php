<?php
/**
 * REST presentation contract for one workspace segment row.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rest\ViewModel;

/**
 * Workspace segment ViewModel v1.
 */
final class WorkspaceSegmentViewModel {

	/**
	 * Builds the collaborator.
	 *
	 * @param string               $segment_key     Segment key.
	 * @param string               $field_key       Field key.
	 * @param string               $block_name      Block type name.
	 * @param string               $uuid            Block UUID when applicable.
	 * @param int                  $segment_order   Walker order.
	 * @param string               $source_text     Canonical source text.
	 * @param string               $source_hash     Optimistic lock token.
	 * @param string               $translated_text Target text.
	 * @param string               $status          Workflow status.
	 * @param bool                 $is_stale        Source drift flag.
	 * @param string               $text_format     Text format.
	 * @param bool                 $can_edit        Whether editing is allowed.
	 * @param array<string, mixed> $meta            Reserved extension bag.
	 */
	public function __construct(
		public readonly string $segment_key,
		public readonly string $field_key,
		public readonly string $block_name,
		public readonly string $uuid,
		public readonly int $segment_order,
		public readonly string $source_text,
		public readonly string $source_hash,
		public readonly string $translated_text,
		public readonly string $status,
		public readonly bool $is_stale,
		public readonly string $text_format,
		public readonly bool $can_edit,
		public readonly array $meta = array()
	) {
	}

	/**
	 * Serializes the ViewModel to REST v1 JSON.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'segment_key'     => $this->segment_key,
			'field_key'       => $this->field_key,
			'block_name'      => $this->block_name,
			'uuid'            => $this->uuid,
			'segment_order'   => $this->segment_order,
			'source_text'     => $this->source_text,
			'source_hash'     => $this->source_hash,
			'translated_text' => $this->translated_text,
			'status'          => $this->status,
			'is_stale'        => $this->is_stale,
			'text_format'     => $this->text_format,
			'can_edit'        => $this->can_edit,
			'meta'            => $this->meta,
		);
	}
}
