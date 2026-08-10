<?php
/**
 * Closed TI.5 provenance classes (best-effort).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Assessment;

/**
 * Provenance_class vocabulary — evidence only, not semantic quality.
 */
final class ProvenanceClass {

	public const MISSING                = 'missing';
	public const AI_GENERATED           = 'ai_generated';
	public const TM_DIRECT_REUSE        = 'tm_direct_reuse';
	public const MANUALLY_EDITED        = 'manually_edited';
	public const LEGACY_REVIEWED_STATUS = 'legacy_reviewed_status';
	public const UNKNOWN                = 'unknown';

	/**
	 * Returns all closed vocabulary values.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::MISSING,
			self::AI_GENERATED,
			self::TM_DIRECT_REUSE,
			self::MANUALLY_EDITED,
			self::LEGACY_REVIEWED_STATUS,
			self::UNKNOWN,
		);
	}
}
