<?php
/**
 * Immutable source segment lookup identity for the public resolver.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

/**
 * Complete lookup identity: segment keys are unique only within a source object.
 */
final class SourceSegmentReference {

	/**
	 * Captures complete source segment lookup identity.
	 *
	 * @param string $source_type Source type (post|term).
	 * @param int    $source_id   Source object id.
	 * @param string $segment_key Segment key (m:, p:, or b: family).
	 */
	public function __construct(
		public readonly string $source_type,
		public readonly int $source_id,
		public readonly string $segment_key,
	) {
	}
}
