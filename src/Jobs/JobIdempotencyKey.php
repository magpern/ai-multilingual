<?php
/**
 * Deterministic job creation idempotency key builder.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * SHA-256 hex idempotency keys without secrets or timestamps (plan §8).
 */
final class JobIdempotencyKey {

	/**
	 * Build a SHA-256 hex digest from normalized create inputs.
	 *
	 * @param array<string, mixed> $args Create arguments.
	 */
	public static function build( array $args ): string {
		$segment_keys = array_values( array_map( 'strval', (array) ( $args['segment_keys'] ?? array() ) ) );
		sort( $segment_keys, SORT_STRING );

		$parts = array(
			'job_type:' . self::normalize_scalar( $args['job_type'] ?? '' ),
			'source_type:' . self::normalize_scalar( $args['source_type'] ?? '' ),
			'source_id:' . (int) ( $args['source_id'] ?? 0 ),
			'language_id:' . (int) ( $args['language_id'] ?? 0 ),
			'segment_keys:' . implode( ',', $segment_keys ),
			'provider_id:' . self::normalize_scalar( $args['provider_id'] ?? '' ),
			'prompt_profile:' . self::normalize_scalar( $args['prompt_profile'] ?? '' ),
			'prompt_version:' . self::normalize_scalar( $args['prompt_version'] ?? '' ),
			'created_by:' . (int) ( $args['created_by'] ?? 0 ),
		);

		$client_token = self::normalize_scalar( $args['client_token'] ?? '' );
		if ( '' !== $client_token ) {
			$parts[] = 'client_token:' . $client_token;
		}

		return hash( 'sha256', implode( '|', $parts ) );
	}

	/**
	 * Whether two create arg sets would produce the same idempotency key.
	 *
	 * @param array<string, mixed> $left  First args.
	 * @param array<string, mixed> $right Second args.
	 */
	public static function args_match( array $left, array $right ): bool {
		return self::build( $left ) === self::build( $right );
	}

	/**
	 * Normalize a scalar string for stable hashing.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function normalize_scalar( $value ): string {
		return trim( (string) $value );
	}
}
