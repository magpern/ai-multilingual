<?php
/**
 * Bounded Jobs failure presentation for operators (OTL.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Maps stored Jobs error fields to safe operator-facing labels.
 *
 * Does not invent Jobs policy — presentation adapter only.
 */
final class JobsFailurePresenter {

	public const CATEGORY_PROVIDER_TRANSPORT = 'provider_transport';
	public const CATEGORY_RATE_LIMIT         = 'rate_limit_retry_wait';
	public const CATEGORY_TERMINAL_PROVIDER  = 'terminal_provider';
	public const CATEGORY_STRUCTURAL_SAFETY  = 'structural_safety';
	public const CATEGORY_STALE_SOURCE       = 'stale_source';
	public const CATEGORY_CONFLICT           = 'conflict';
	public const CATEGORY_CONCURRENCY        = 'concurrency';
	public const CATEGORY_BUDGET             = 'budget';
	public const CATEGORY_UNKNOWN            = 'unknown_fail_closed';

	/**
	 * Maximum operator-facing message length.
	 */
	public const MAX_MESSAGE_CHARS = 280;

	/**
	 * Builds a bounded failure presentation from job/item error fields.
	 *
	 * @param string $error_code    Stored error code.
	 * @param string $error_class   Stored error class.
	 * @param string $error_message Stored message (may be empty).
	 * @param string $result_code   Optional item result_code.
	 * @return array{category: string, code: string, message: string}|null
	 */
	public function present(
		string $error_code,
		string $error_class = '',
		string $error_message = '',
		string $result_code = ''
	): ?array {
		$code = strtolower( trim( '' !== $error_code ? $error_code : $result_code ) );
		if ( '' === $code && '' === trim( $error_message ) ) {
			return null;
		}

		$category = $this->categorize( $code, $error_class );
		$message  = $this->bound_message( $error_message );
		if ( '' === $message ) {
			$message = $this->default_message( $category );
		}

		return array(
			'category' => $category,
			'code'     => $code,
			'message'  => $message,
		);
	}

	/**
	 * Maps an error code to a presentation category.
	 *
	 * @param string $code        Error code.
	 * @param string $error_class Error class.
	 */
	private function categorize( string $code, string $error_class ): string {
		if ( in_array( $code, array( 'stale_source', 'source_hash_mismatch' ), true ) ) {
			return self::CATEGORY_STALE_SOURCE;
		}
		if ( in_array( $code, array( 'skipped_conflict', 'aiml_translation_hash_mismatch', 'approved_conflict' ), true ) ) {
			return self::CATEGORY_CONFLICT;
		}
		if ( in_array( $code, array( 'budget_exceeded', 'budget_exhausted' ), true ) ) {
			return self::CATEGORY_BUDGET;
		}
		if ( in_array( $code, array( 'concurrency_rejected', 'concurrency_limit' ), true ) ) {
			return self::CATEGORY_CONCURRENCY;
		}
		if ( in_array( $code, array( 'rate_limited', 'retry_after', 'http_429' ), true ) ) {
			return self::CATEGORY_RATE_LIMIT;
		}
		if ( in_array( $code, array( 'structural_safety', 'ti1_safety', 'html_safety' ), true ) ) {
			return self::CATEGORY_STRUCTURAL_SAFETY;
		}
		if ( 'permanent' === strtolower( $error_class ) || str_contains( $code, 'permanent' ) ) {
			return self::CATEGORY_TERMINAL_PROVIDER;
		}
		if ( '' !== $code && ( str_contains( $code, 'provider' ) || str_contains( $code, 'http_' ) || str_contains( $code, 'transport' ) ) ) {
			return self::CATEGORY_PROVIDER_TRANSPORT;
		}
		if ( 'retryable' === strtolower( $error_class ) ) {
			return self::CATEGORY_PROVIDER_TRANSPORT;
		}

		return self::CATEGORY_UNKNOWN;
	}

	/**
	 * Bounds and sanitizes an operator-facing message.
	 *
	 * @param string $message Raw message.
	 */
	private function bound_message( string $message ): string {
		$message = wp_strip_all_tags( $message );
		$message = preg_replace( '/\s+/', ' ', $message ) ?? $message;
		$message = trim( $message );
		if ( strlen( $message ) > self::MAX_MESSAGE_CHARS ) {
			$message = substr( $message, 0, self::MAX_MESSAGE_CHARS - 1 ) . '…';
		}

		return $message;
	}

	/**
	 * Default message for a category when none was stored.
	 *
	 * @param string $category Category id.
	 */
	private function default_message( string $category ): string {
		return match ( $category ) {
			self::CATEGORY_STALE_SOURCE => __( 'Source changed while the job item was running.', 'universal-multilingual' ),
			self::CATEGORY_CONFLICT => __( 'A target conflict prevented this job item from completing.', 'universal-multilingual' ),
			self::CATEGORY_BUDGET => __( 'The job reached its provider budget limit.', 'universal-multilingual' ),
			self::CATEGORY_CONCURRENCY => __( 'The job could not start because the concurrency limit was reached.', 'universal-multilingual' ),
			self::CATEGORY_RATE_LIMIT => __( 'The provider asked the job to wait before retrying.', 'universal-multilingual' ),
			self::CATEGORY_STRUCTURAL_SAFETY => __( 'A structural safety check blocked this job item.', 'universal-multilingual' ),
			self::CATEGORY_TERMINAL_PROVIDER => __( 'A permanent provider error stopped this job item.', 'universal-multilingual' ),
			self::CATEGORY_PROVIDER_TRANSPORT => __( 'A provider or transport error interrupted this job item.', 'universal-multilingual' ),
			default => __( 'The job item failed. Open Jobs for details.', 'universal-multilingual' ),
		};
	}
}
