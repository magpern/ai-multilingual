<?php
/**
 * Bounded provider/TM attempt usage evidence for Jobs (TI.6).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Attempt usage is evidence from the translation path — not item-status inference.
 */
final class AttemptUsageEvidence {

	/**
	 * Build bounded attempt usage evidence.
	 *
	 * @param int    $provider_requests Provider HTTP/call units for this attempt.
	 * @param int    $input_tokens      Provider-reported input tokens (0 if unknown/none).
	 * @param int    $output_tokens     Provider-reported output tokens (0 if unknown/none).
	 * @param bool   $usage_known       Whether request accounting is known for this attempt.
	 * @param string $tm_outcome_code Optional TMGenerationOutcome code (bounded).
	 */
	public function __construct(
		public readonly int $provider_requests = 0,
		public readonly int $input_tokens = 0,
		public readonly int $output_tokens = 0,
		public readonly bool $usage_known = true,
		public readonly string $tm_outcome_code = ''
	) {
	}

	/**
	 * Known-zero usage (TM direct reuse, skip, conflict, pre-provider failure).
	 *
	 * @param string $tm_outcome_code Optional TM code.
	 */
	public static function known_zero( string $tm_outcome_code = '' ): self {
		return new self( 0, 0, 0, true, $tm_outcome_code );
	}

	/**
	 * Provider generation with reported token usage.
	 *
	 * @param int    $requests      Call units (typically 1).
	 * @param int    $input_tokens  Input tokens.
	 * @param int    $output_tokens Output tokens.
	 * @param string $tm_outcome    Optional TM outcome when context-assisted.
	 */
	public static function provider_success(
		int $requests,
		int $input_tokens,
		int $output_tokens,
		string $tm_outcome = ''
	): self {
		return new self(
			max( 0, $requests ),
			max( 0, $input_tokens ),
			max( 0, $output_tokens ),
			true,
			$tm_outcome
		);
	}

	/**
	 * Provider attempt that performed a call but failed afterward.
	 *
	 * @param int $requests      Known request units.
	 * @param int $input_tokens  Tokens if known.
	 * @param int $output_tokens Tokens if known.
	 */
	public static function provider_attempt( int $requests, int $input_tokens = 0, int $output_tokens = 0 ): self {
		return new self( max( 0, $requests ), max( 0, $input_tokens ), max( 0, $output_tokens ), true );
	}

	/**
	 * Usage cannot be determined (do not fabricate).
	 */
	public static function unknown(): self {
		return new self( 0, 0, 0, false );
	}

	/**
	 * Token units for budget counters (input + output when known).
	 */
	public function token_units(): int {
		return $this->input_tokens + $this->output_tokens;
	}
}
