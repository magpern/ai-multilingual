<?php
/**
 * Retry taxonomy, backoff, and jitter for background translation items.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Classifies item failures and computes retry delays (plan §14).
 */
final class BackgroundTranslationRetryPolicy {

	public const MAX_ATTEMPTS = 5;

	public const BASE_DELAY_SECONDS = 30;

	public const MAX_DELAY_SECONDS = 900;

	public const DISPOSITION_RETRYABLE = 'retryable';

	public const DISPOSITION_TERMINAL = 'terminal';

	/**
	 * Whether another attempt is allowed for the given attempt count.
	 *
	 * The attempt_count reflects claims including the attempt that just failed.
	 *
	 * @param int $attempt_count Item attempt counter after claim.
	 */
	public function should_retry( int $attempt_count ): bool {
		return $attempt_count > 0 && $attempt_count < self::MAX_ATTEMPTS;
	}

	/**
	 * Classify an error as retryable or terminal.
	 *
	 * @param string   $error_code  Stable error code.
	 * @param int|null $http_status Optional HTTP status from provider transport.
	 */
	public function classify( string $error_code, ?int $http_status = null ): string {
		$code = strtolower( trim( $error_code ) );

		if ( in_array( $code, $this->terminal_codes(), true ) ) {
			return self::DISPOSITION_TERMINAL;
		}

		if ( in_array( $code, $this->retryable_codes(), true ) ) {
			return self::DISPOSITION_RETRYABLE;
		}

		if ( null !== $http_status && $http_status >= 500 && $http_status < 600 ) {
			return self::DISPOSITION_RETRYABLE;
		}

		if ( null !== $http_status && 429 === $http_status ) {
			return self::DISPOSITION_RETRYABLE;
		}

		return self::DISPOSITION_TERMINAL;
	}

	/**
	 * Compute backoff delay for a retry attempt.
	 *
	 * @param int $attempt         1-based attempt number (after increment on claim).
	 * @param int $retry_after_sec Optional Retry-After hint in seconds.
	 */
	public function delay_seconds( int $attempt, int $retry_after_sec = 0 ): int {
		$attempt = max( 1, $attempt );

		$exponential = self::BASE_DELAY_SECONDS * ( 2 ** ( $attempt - 1 ) );
		$exponential = min( $exponential, self::MAX_DELAY_SECONDS );

		$jitter_max = (int) max( 1, floor( $exponential * 0.1 ) );
		$jitter     = function_exists( 'wp_rand' ) ? wp_rand( 0, $jitter_max ) : random_int( 0, $jitter_max );
		$delay      = $exponential + $jitter;

		if ( $retry_after_sec > 0 ) {
			$delay = max( $delay, $retry_after_sec );
		}

		return min( $delay, self::MAX_DELAY_SECONDS );
	}

	/**
	 * Stable retryable error codes (plan §14).
	 *
	 * @return list<string>
	 */
	private function retryable_codes(): array {
		return array(
			'rate_limit',
			'aiml_rate_limited',
			'network',
			'http_request_failed',
			'provider_5xx',
			'lock_contention',
			'lease_contention',
			'job_lease_claim_failed',
		);
	}

	/**
	 * Stable terminal error codes (plan §14).
	 *
	 * @return list<string>
	 */
	private function terminal_codes(): array {
		return array(
			'invalid_language',
			'aiml_invalid_language',
			'unsupported_provider',
			'validation',
			'aiml_validation_failed',
			'source_conflict',
			'stale_source',
			'translation_conflict',
			'skipped_conflict',
			'malformed_item',
			'aiml_invalid_segment',
			'cancelled',
			'budget_exceeded',
			'provider_unavailable',
		);
	}
}
