<?php
/**
 * Strategy F block health snapshot.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Immutable read-only block health scan result.
 */
final class BlockHealthSnapshot {

	public const SCAN_MODE_SAMPLE = 'sample';

	public const SCAN_MODE_FULL = 'full';

	/**
	 * Builds a health snapshot.
	 *
	 * @param string             $generated_at                  ISO-8601 timestamp.
	 * @param string             $scan_mode                     {@see self::SCAN_MODE_SAMPLE} or {@see self::SCAN_MODE_FULL}.
	 * @param int                $requested_sample_size         Requested sample size.
	 * @param int                $scanned_post_count            Posts scanned.
	 * @param int                $eligible_post_count           Eligible posts in population.
	 * @param int                $compliant_post_count          UUID-compliant scanned posts.
	 * @param int                $non_compliant_post_count      UUID-non-compliant scanned posts.
	 * @param int                $skipped_post_count            Skipped posts.
	 * @param array<string, int> $skip_reason_counts            Skip reason tallies.
	 * @param int                $posts_with_missing_uuids      Posts with missing UUIDs.
	 * @param int                $posts_with_malformed_uuids    Posts with malformed UUIDs.
	 * @param int                $posts_with_duplicate_uuids    Posts with duplicate UUIDs.
	 * @param int                $total_block_segments          Store block segment count.
	 * @param int                $translated_block_segments     Store translated block segment count.
	 * @param int                $renderable_block_segments     Store renderable block segment count.
	 * @param int                $stale_block_segments          Store stale block segment count.
	 * @param int                $orphaned_block_segments       Store orphaned block segment count.
	 * @param int|null           $duplicate_segment_rows        Duplicate segment rows when detectable.
	 * @param bool               $duplicate_segment_rows_detectable Whether duplicate rows can be detected.
	 * @param array              $errors                        Stable error codes.
	 * @param array              $limitations                   Non-fatal limitation notes.
	 * @param bool               $incomplete                    Whether the snapshot is partial.
	 * @param bool               $sampled                       Whether post scan was sampled.
	 * @param int                $elapsed_ms                    Elapsed milliseconds.
	 * @param array              $post_results                  Per-post results (bounded).
	 */
	public function __construct(
		public readonly string $generated_at,
		public readonly string $scan_mode,
		public readonly int $requested_sample_size,
		public readonly int $scanned_post_count,
		public readonly int $eligible_post_count,
		public readonly int $compliant_post_count,
		public readonly int $non_compliant_post_count,
		public readonly int $skipped_post_count,
		public readonly array $skip_reason_counts,
		public readonly int $posts_with_missing_uuids,
		public readonly int $posts_with_malformed_uuids,
		public readonly int $posts_with_duplicate_uuids,
		public readonly int $total_block_segments,
		public readonly int $translated_block_segments,
		public readonly int $renderable_block_segments,
		public readonly int $stale_block_segments,
		public readonly int $orphaned_block_segments,
		public readonly ?int $duplicate_segment_rows,
		public readonly bool $duplicate_segment_rows_detectable,
		public readonly array $errors,
		public readonly array $limitations,
		public readonly bool $incomplete,
		public readonly bool $sampled,
		public readonly int $elapsed_ms,
		public readonly array $post_results = array(),
	) {
	}

	/**
	 * Serializes the snapshot for CLI/admin consumers.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'generated_at'                      => $this->generated_at,
			'scan_mode'                         => $this->scan_mode,
			'requested_sample_size'             => $this->requested_sample_size,
			'scanned_post_count'                => $this->scanned_post_count,
			'eligible_post_count'               => $this->eligible_post_count,
			'compliant_post_count'              => $this->compliant_post_count,
			'non_compliant_post_count'          => $this->non_compliant_post_count,
			'skipped_post_count'                => $this->skipped_post_count,
			'skip_reason_counts'                => $this->skip_reason_counts,
			'posts_with_missing_uuids'          => $this->posts_with_missing_uuids,
			'posts_with_malformed_uuids'        => $this->posts_with_malformed_uuids,
			'posts_with_duplicate_uuids'        => $this->posts_with_duplicate_uuids,
			'total_block_segments'              => $this->total_block_segments,
			'translated_block_segments'         => $this->translated_block_segments,
			'renderable_block_segments'         => $this->renderable_block_segments,
			'stale_block_segments'              => $this->stale_block_segments,
			'orphaned_block_segments'           => $this->orphaned_block_segments,
			'duplicate_segment_rows'            => $this->duplicate_segment_rows,
			'duplicate_segment_rows_detectable' => $this->duplicate_segment_rows_detectable,
			'errors'                            => $this->errors,
			'limitations'                       => $this->limitations,
			'incomplete'                        => $this->incomplete,
			'sampled'                           => $this->sampled,
			'elapsed_ms'                        => $this->elapsed_ms,
		);
	}
}
