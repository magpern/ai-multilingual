<?php
/**
 * Strategy F single-post block migration outcome.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Immutable result of migrating one canonical post.
 */
final class BlockMigrationResult {

	public const STATUS_COMPLETE = 'complete';
	public const STATUS_SKIPPED  = 'skipped';
	public const STATUS_FAILED   = 'failed';
	public const STATUS_DRY_RUN  = 'dry_run';

	/**
	 * Builds a migration result.
	 *
	 * @param int                  $post_id                   Post id.
	 * @param string               $post_type                 Post type.
	 * @param string               $status                    Outcome status.
	 * @param bool                 $dry_run                   Whether this was a dry run.
	 * @param string               $skip_reason               Skip reason when skipped.
	 * @param bool                 $content_changed           Whether post_content would change or changed.
	 * @param string               $original_hash             Hash of content read at start.
	 * @param string               $migrated_hash             Hash of content after identity pass.
	 * @param int                  $created_count             UUIDs created.
	 * @param int                  $malformed_replaced_count  Invalid UUIDs replaced.
	 * @param int                  $duplicate_repaired_count  Duplicate UUIDs repaired.
	 * @param int                  $segment_count             Block segments extracted or expected.
	 * @param bool                 $extraction_synced         Whether reconciliation ran.
	 * @param string               $failure_reason            Failure reason when failed.
	 * @param int                  $elapsed_ms                Wall time in milliseconds.
	 * @param array<string, mixed> $audit                     Rollback metadata (no body text).
	 */
	public function __construct(
		public readonly int $post_id,
		public readonly string $post_type,
		public readonly string $status,
		public readonly bool $dry_run = false,
		public readonly string $skip_reason = '',
		public readonly bool $content_changed = false,
		public readonly string $original_hash = '',
		public readonly string $migrated_hash = '',
		public readonly int $created_count = 0,
		public readonly int $malformed_replaced_count = 0,
		public readonly int $duplicate_repaired_count = 0,
		public readonly int $segment_count = 0,
		public readonly bool $extraction_synced = false,
		public readonly string $failure_reason = '',
		public readonly int $elapsed_ms = 0,
		public readonly array $audit = array(),
	) {
	}

	/**
	 * Converts the result to a CLI-safe array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'post_id'                  => $this->post_id,
			'post_type'                => $this->post_type,
			'status'                   => $this->status,
			'dry_run'                  => $this->dry_run,
			'skip_reason'              => $this->skip_reason,
			'content_changed'          => $this->content_changed,
			'original_hash'            => $this->original_hash,
			'migrated_hash'            => $this->migrated_hash,
			'created_count'            => $this->created_count,
			'malformed_replaced_count' => $this->malformed_replaced_count,
			'duplicate_repaired_count' => $this->duplicate_repaired_count,
			'segment_count'            => $this->segment_count,
			'extraction_synced'        => $this->extraction_synced,
			'failure_reason'           => $this->failure_reason,
			'elapsed_ms'               => $this->elapsed_ms,
			'audit'                    => $this->audit,
		);
	}
}
