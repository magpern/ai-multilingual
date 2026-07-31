<?php
/**
 * Strategy F read-only block identity compliance result.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * UUID compliance counts for one parsed post body.
 */
final class BlockIdentityCompliance {

	/**
	 * Builds a compliance summary.
	 *
	 * @param int $eligible_block_count Eligible blocks inspected.
	 * @param int $missing_uuid_count   Eligible blocks without a valid UUID.
	 * @param int $malformed_uuid_count Eligible blocks with invalid UUID values.
	 * @param int $duplicate_uuid_count Eligible blocks participating in duplicate UUIDs.
	 */
	public function __construct(
		public readonly int $eligible_block_count = 0,
		public readonly int $missing_uuid_count = 0,
		public readonly int $malformed_uuid_count = 0,
		public readonly int $duplicate_uuid_count = 0,
	) {
	}

	/**
	 * Whether every eligible block has a valid, document-unique UUID.
	 */
	public function is_compliant(): bool {
		return 0 === $this->missing_uuid_count
			&& 0 === $this->malformed_uuid_count
			&& 0 === $this->duplicate_uuid_count;
	}
}
