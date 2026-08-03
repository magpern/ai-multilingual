<?php
/**
 * Provider translation outcome.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

/**
 * Normalized provider response for one batch.
 */
final class ProviderResult {

	public const ERROR_RETRYABLE   = 'retryable';
	public const ERROR_PERMANENT   = 'permanent';
	public const ERROR_VALIDATION  = 'validation';

	/**
	 * Builds a provider result.
	 *
	 * @param array<int, array{segment_key: string, translated_text: string}> $segments Translated segments.
	 * @param int                                                            $input_tokens Input token count.
	 * @param int                                                            $output_tokens Output token count.
	 * @param string                                                         $model Model identifier used.
	 * @param string|null                                                    $error_class Error classification when partial failure occurs.
	 */
	public function __construct(
		public readonly array $segments,
		public readonly int $input_tokens = 0,
		public readonly int $output_tokens = 0,
		public readonly string $model = '',
		public readonly ?string $error_class = null,
	) {
	}
}
