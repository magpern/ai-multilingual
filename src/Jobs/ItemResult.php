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
	 * Provider request units evidenced for this attempt.
	 *
	 * @var int
	 */
	public int $provider_requests;

	/**
	 * Provider-reported input tokens.
	 *
	 * @var int
	 */
	public int $input_tokens;

	/**
	 * Provider-reported output tokens.
	 *
	 * @var int
	 */
	public int $output_tokens;

	/**
	 * Whether usage evidence is known.
	 *
	 * @var bool
	 */
	public bool $usage_known;

	/**
	 * Optional TM generation outcome code.
	 *
	 * @var string
	 */
	public string $tm_outcome_code;

	/**
	 * Retry-After hint in seconds when retryable.
	 *
	 * @var int
	 */
	public int $retry_after_seconds;

	/**
	 * Human-readable skip reason for conflict/stale outcomes.
	 *
	 * @var string
	 */
	public string $skip_reason;

	/**
	 * Builds an item outcome.
	 *
	 * @param string                    $status                  Item status / result.
	 * @param string                    $result_code             Result code stored on item row.
	 * @param string                    $error_code              Optional error code.
	 * @param string                    $error_class             Optional error class.
	 * @param string                    $error_message           Optional bounded message.
	 * @param int                       $glossary_version_actual Glossary version at execution.
	 * @param AttemptUsageEvidence|null $usage Attempt usage evidence.
	 * @param string                    $skip_reason             Skip/stale reason when applicable.
	 * @param int                       $retry_after_seconds     Retry-After hint when retryable.
	 */
	public function __construct(
		string $status,
		string $result_code = '',
		string $error_code = '',
		string $error_class = '',
		string $error_message = '',
		int $glossary_version_actual = 0,
		?AttemptUsageEvidence $usage = null,
		string $skip_reason = '',
		int $retry_after_seconds = 0
	) {
		$usage = $usage ?? AttemptUsageEvidence::unknown();

		$this->status                  = $status;
		$this->result_code             = '' !== $result_code ? $result_code : $status;
		$this->error_code              = $error_code;
		$this->error_class             = $error_class;
		$this->error_message           = $error_message;
		$this->glossary_version_actual = $glossary_version_actual;
		$this->provider_requests       = $usage->provider_requests;
		$this->input_tokens            = $usage->input_tokens;
		$this->output_tokens           = $usage->output_tokens;
		$this->usage_known             = $usage->usage_known;
		$this->tm_outcome_code         = $usage->tm_outcome_code;
		$this->usage_requests          = $usage->provider_requests;
		$this->usage_tokens            = $usage->usage_known ? $usage->token_units() : 0;
		$this->skip_reason             = $skip_reason;
		$this->retry_after_seconds     = max( 0, $retry_after_seconds );
	}

	/**
	 * Successful machine translation persist.
	 *
	 * @param int                       $glossary_version_actual Glossary version used.
	 * @param AttemptUsageEvidence|null $usage Attempt usage evidence.
	 */
	public static function completed(
		int $glossary_version_actual = 0,
		?AttemptUsageEvidence $usage = null
	): self {
		return new self(
			ItemStatuses::COMPLETED,
			ItemStatuses::COMPLETED,
			'',
			'',
			'',
			$glossary_version_actual,
			$usage
		);
	}

	/**
	 * Source hash drift since job materialization.
	 *
	 * @param string                    $reason Skip reason.
	 * @param AttemptUsageEvidence|null $usage  Attempt usage evidence.
	 */
	public static function stale_source(
		string $reason = 'Source content changed since job creation.',
		?AttemptUsageEvidence $usage = null
	): self {
		return new self(
			ItemStatuses::STALE_SOURCE,
			ItemStatuses::STALE_SOURCE,
			'stale_source',
			'permanent',
			$reason,
			0,
			$usage ?? AttemptUsageEvidence::known_zero(),
			$reason
		);
	}

	/**
	 * Overwrite policy blocked translation.
	 *
	 * @param string                    $reason Skip reason.
	 * @param AttemptUsageEvidence|null $usage  Attempt usage evidence.
	 */
	public static function skipped_conflict( string $reason, ?AttemptUsageEvidence $usage = null ): self {
		return new self(
			ItemStatuses::SKIPPED_CONFLICT,
			ItemStatuses::SKIPPED_CONFLICT,
			'skipped_conflict',
			'permanent',
			$reason,
			0,
			$usage ?? AttemptUsageEvidence::known_zero(),
			$reason
		);
	}

	/**
	 * Terminal or retryable failure from translate_segment.
	 *
	 * @param string                    $status        Target status (failed or retry_wait).
	 * @param string                    $error_code    Error code.
	 * @param string                    $error_class   Error taxonomy.
	 * @param string                    $error_message Bounded message.
	 * @param int                       $retry_after Optional Retry-After seconds.
	 * @param AttemptUsageEvidence|null $usage       Attempt usage evidence.
	 */
	public static function from_error(
		string $status,
		string $error_code,
		string $error_class,
		string $error_message,
		int $retry_after = 0,
		?AttemptUsageEvidence $usage = null
	): self {
		return new self(
			$status,
			$status,
			$error_code,
			$error_class,
			self::bound_message( $error_message ),
			0,
			$usage ?? AttemptUsageEvidence::unknown(),
			'',
			$retry_after
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
