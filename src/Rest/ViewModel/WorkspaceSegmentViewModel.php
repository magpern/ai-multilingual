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
	 * @param string               $review_status   Review Workflow status (ADR-0015).
	 * @param string               $translation_hash Persisted target hash (optimistic concurrency).
	 * @param string               $submitted_translation_hash Hash captured at last submit.
	 * @param int|null             $review_submitted_by         Submitting user id.
	 * @param string|null          $review_submitted_at         Submission timestamp.
	 * @param int|null             $reviewed_by                 Approving reviewer id.
	 * @param string|null          $reviewed_at                 Approval timestamp.
	 * @param string               $rejection_reason            Active rejection reason.
	 * @param int|null             $rejected_by                 Rejecting reviewer id.
	 * @param string|null          $rejected_at                 Rejection timestamp.
	 * @param string               $publish_status              TI.7 publication status.
	 * @param string|null          $published_at                Publication timestamp.
	 * @param int|null             $published_by                Publishing actor id (0 = system).
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
		public readonly array $meta = array(),
		public readonly string $review_status = 'not_submitted',
		public readonly string $translation_hash = '',
		public readonly string $submitted_translation_hash = '',
		public readonly ?int $review_submitted_by = null,
		public readonly ?string $review_submitted_at = null,
		public readonly ?int $reviewed_by = null,
		public readonly ?string $reviewed_at = null,
		public readonly string $rejection_reason = '',
		public readonly ?int $rejected_by = null,
		public readonly ?string $rejected_at = null,
		public readonly string $publish_status = 'unpublished',
		public readonly ?string $published_at = null,
		public readonly ?int $published_by = null
	) {
	}

	/**
	 * Serializes the ViewModel to REST v1 JSON.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'segment_key'                => $this->segment_key,
			'field_key'                  => $this->field_key,
			'block_name'                 => $this->block_name,
			'uuid'                       => $this->uuid,
			'segment_order'              => $this->segment_order,
			'source_text'                => $this->source_text,
			'source_hash'                => $this->source_hash,
			'translated_text'            => $this->translated_text,
			'status'                     => $this->status,
			'is_stale'                   => $this->is_stale,
			'text_format'                => $this->text_format,
			'can_edit'                   => $this->can_edit,
			'meta'                       => $this->meta,
			'review_status'              => $this->review_status,
			'translation_hash'           => $this->translation_hash,
			'submitted_translation_hash' => $this->submitted_translation_hash,
			'review_submitted_by'        => $this->review_submitted_by,
			'review_submitted_at'        => $this->review_submitted_at,
			'reviewed_by'                => $this->reviewed_by,
			'reviewed_at'                => $this->reviewed_at,
			'rejection_reason'           => $this->rejection_reason,
			'rejected_by'                => $this->rejected_by,
			'rejected_at'                => $this->rejected_at,
			'publish_status'             => $this->publish_status,
			'published_at'               => $this->published_at,
			'published_by'               => $this->published_by,
		);
	}
}
