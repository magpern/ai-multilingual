<?php
/**
 * Background translation job type constants.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * MVP job types (plan §5).
 */
final class JobTypes {

	public const TRANSLATE_SELECTED = 'translate_selected';
	public const TRANSLATE_MISSING  = 'translate_missing';
	public const RETRANSLATE_STALE  = 'retranslate_stale';
	public const BULK_TRANSLATE     = 'bulk_translate';

	/**
	 * All MVP job type codes.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::TRANSLATE_SELECTED,
			self::TRANSLATE_MISSING,
			self::RETRANSLATE_STALE,
			self::BULK_TRANSLATE,
		);
	}

	/**
	 * Whether the job type may retranslate existing machine_translated segments.
	 *
	 * @param string $job_type Job type code.
	 */
	public static function allows_retranslate( string $job_type ): bool {
		return in_array(
			$job_type,
			array(
				self::TRANSLATE_SELECTED,
				self::RETRANSLATE_STALE,
			),
			true
		);
	}

	/**
	 * Whether segment_keys must be supplied explicitly at create time.
	 *
	 * @param string $job_type Job type code.
	 */
	public static function requires_explicit_segments( string $job_type ): bool {
		return in_array( $job_type, self::all(), true );
	}
}
