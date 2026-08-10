<?php
/**
 * Closed TI.5 overall readiness categories (ADR-0019).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Assessment;

/**
 * Risk / readiness overall_category vocabulary.
 */
final class AssessmentCategory {

	public const BLOCKED            = 'blocked';
	public const NEEDS_REVIEW       = 'needs_review';
	public const REVIEW_RECOMMENDED = 'review_recommended';
	public const STRUCTURALLY_CLEAN = 'structurally_clean';

	/**
	 * Returns all closed vocabulary values.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::BLOCKED,
			self::NEEDS_REVIEW,
			self::REVIEW_RECOMMENDED,
			self::STRUCTURALLY_CLEAN,
		);
	}

	/**
	 * Whether the value is a valid category.
	 *
	 * @param string $value Candidate category.
	 */
	public static function is_valid( string $value ): bool {
		return in_array( $value, self::all(), true );
	}
}
