<?php
/**
 * Evidence-completeness facet states (TI.5).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Assessment;

/**
 * Completeness vocabulary — unavailable ≠ PASS.
 */
final class EvidenceCompleteness {

	public const COMPLETE    = 'complete';
	public const PARTIAL     = 'partial';
	public const UNAVAILABLE = 'unavailable';

	/**
	 * Returns all closed vocabulary values.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::COMPLETE,
			self::PARTIAL,
			self::UNAVAILABLE,
		);
	}
}
