<?php
/**
 * Background translation job audit event name catalog (plan §22).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Frozen, stable translation job audit event identifiers.
 */
final class BackgroundTranslationJobAuditEvents {

	public const CREATED         = 'translation_job_created';
	public const STARTED         = 'translation_job_started';
	public const PAUSED          = 'translation_job_paused';
	public const RESUMED         = 'translation_job_resumed';
	public const CANCELLED       = 'translation_job_cancelled';
	public const COMPLETED       = 'translation_job_completed';
	public const FAILED          = 'translation_job_failed';
	public const ITEM_FAILED     = 'translation_job_item_failed';
	public const BUDGET_EXCEEDED = 'translation_job_budget_exceeded';
	public const STALE_SOURCE    = 'translation_job_stale_source';

	/**
	 * Returns all registered audit event names.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::CREATED,
			self::STARTED,
			self::PAUSED,
			self::RESUMED,
			self::CANCELLED,
			self::COMPLETED,
			self::FAILED,
			self::ITEM_FAILED,
			self::BUDGET_EXCEEDED,
			self::STALE_SOURCE,
		);
	}
}
