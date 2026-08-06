<?php
/**
 * Per-item worker outcome (orchestration only — no bodies or prompts).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * DTO returned by BackgroundTranslationItemProcessor.
 */
final class ItemResult {

	/**
	 * Terminal or transient item status (plan §11).
	 *
	 * @var string
	 */
	public string $status;

	/**
	 * Stable result code recorded on the item row.
	 *
	 * @var string
	 */
	public string $result_code;

	/**
	 * Provider/domain error code when failed or retry_wait.
	 *
	 * @var string
	 */
	public string $error_code;

	/**
	 * Error taxonomy class (retryable, permanent, validation).
	 *
	 * @var string
	 */
	public string $error_class;

	/**
	 * Bounded safe error message (no provider bodies).
	 *
	 * @var string
	 */
	public string $error_message;

	/**
	 * Glossary version observed at execution.
	 *
	 * @var int
	 */
	public int $glossary_version_actual;

	/**
	 * Provider request units consumed for this item.
	 *
	 * @var int
	 */
	public int $usage_requests;

	/**
	 * Provider token units consumed for this item.
	 *
	 * @var int
	 */
	public int $usage_tokens;

	/**
	 * Human-readable skip reason for conflict/stale outcomes.
	 *
	 * @var string
	 */
	public string $skip_reason;

	/**
	 * Builds an item outcome.
	 *
	 * @param string $status                  Item status / result.
	 * @param string $result_code             Result code stored on item row.
	 * @param string $error_code              Optional error code.
	 * @param string $error_class             Optional error class.
	 * @param string $error_message           Optional bounded message.
	 * @param int    $glossary_version_actual Glossary version at execution.
	 * @param int    $usage_requests          Request units used.
	 * @param int    $usage_tokens            Token units used.
	 * @param string $skip_reason             Skip/stale reason when applicable.
	 */
	public function __construct(
		string $status,
		string $result_code = '',
		string $error_code = '',
		string $error_class = '',
		string $error_message = '',
		int $glossary_version_actual = 0,
		int $usage_requests = 0,
		int $usage_tokens = 0,
		string $skip_reason = ''
	) {
		$this->status                  = $status;
		$this->result_code             = '' !== $result_code ? $result_code : $status;
		$this->error_code              = $error_code;
		$this->error_class             = $error_class;
		$this->error_message           = $error_message;
		$this->glossary_version_actual = $glossary_version_actual;
		$this->usage_requests          = $usage_requests;
		$this->usage_tokens            = $usage_tokens;
		$this->skip_reason             = $skip_reason;
	}

	/**
	 * Successful machine translation persist.
	 *
	 * @param int $glossary_version_actual Glossary version used.
	 * @param int $usage_requests          Request units.
	 * @param int $usage_tokens            Token units.
	 */
	public static function completed(
		int $glossary_version_actual = 0,
		int $usage_requests = 0,
		int $usage_tokens = 0
	): self {
		return new self(
			ItemStatuses::COMPLETED,
			ItemStatuses::COMPLETED,
			'',
			'',
			'',
			$glossary_version_actual,
			$usage_requests,
			$usage_tokens
		);
	}

	/**
	 * Source hash drift since job materialization.
	 *
	 * @param string $reason Skip reason.
	 */
	public static function stale_source( string $reason = 'Source content changed since job creation.' ): self {
		return new self(
			ItemStatuses::STALE_SOURCE,
			ItemStatuses::STALE_SOURCE,
			'stale_source',
			'permanent',
			$reason,
			0,
			0,
			0,
			$reason
		);
	}

	/**
	 * Overwrite policy blocked translation.
	 *
	 * @param string $reason Skip reason.
	 */
	public static function skipped_conflict( string $reason ): self {
		return new self(
			ItemStatuses::SKIPPED_CONFLICT,
			ItemStatuses::SKIPPED_CONFLICT,
			'skipped_conflict',
			'permanent',
			$reason,
			0,
			0,
			0,
			$reason
		);
	}

	/**
	 * Terminal or retryable failure from translate_segment.
	 *
	 * @param string $status        Target status (failed or retry_wait).
	 * @param string $error_code    Error code.
	 * @param string $error_class   Error taxonomy.
	 * @param string $error_message Bounded message.
	 */
	public static function from_error(
		string $status,
		string $error_code,
		string $error_class,
		string $error_message
	): self {
		return new self(
			$status,
			$status,
			$error_code,
			$error_class,
			self::bound_message( $error_message ),
			0,
			0,
			0
		);
	}

	/**
	 * Whether the worker should schedule retry_wait for this outcome.
	 */
	public function is_retryable(): bool {
		return ItemStatuses::RETRY_WAIT === $this->status;
	}

	/**
	 * Truncate error text for job storage (plan §22).
	 *
	 * @param string $message Raw message.
	 */
	private static function bound_message( string $message ): string {
		$message = preg_replace( '/\s+/', ' ', trim( $message ) ) ?? '';

		if ( strlen( $message ) <= 500 ) {
			return $message;
		}

		return substr( $message, 0, 497 ) . '...';
	}
}
