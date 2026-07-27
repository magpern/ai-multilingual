<?php
/**
 * Strategy F block migration batch outcome.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Immutable batch migration summary.
 */
final class BlockBatchMigrationResult {

	/**
	 * Builds a batch migration result.
	 *
	 * @param BlockMigrationResult[] $results     Per-post results in request order.
	 * @param int                    $next_offset Continuation offset for the next batch.
	 * @param bool                   $has_more    Whether more posts remain.
	 * @param int                    $elapsed_ms  Total wall time in milliseconds.
	 */
	public function __construct(
		public readonly array $results,
		public readonly int $next_offset,
		public readonly bool $has_more,
		public readonly int $elapsed_ms = 0,
	) {
	}

	/**
	 * Converts the batch result to a CLI-safe array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'results'     => array_map(
				static fn( BlockMigrationResult $result ): array => $result->to_array(),
				$this->results
			),
			'next_offset' => $this->next_offset,
			'has_more'    => $this->has_more,
			'elapsed_ms'  => $this->elapsed_ms,
		);
	}
}
