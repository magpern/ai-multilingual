<?php
/**
 * Job checkpoint encode/decode with bounded, allowlisted keys.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use WP_Error;

/**
 * Serializes resumable job checkpoints without bodies, prompts, or secrets.
 */
final class JobCheckpoint {

	/**
	 * Soft byte cap for encoded checkpoint JSON.
	 */
	public const SOFT_CAP_BYTES = 16384;

	/**
	 * Allowlisted checkpoint keys.
	 *
	 * @var string[]
	 */
	private const ALLOWED_KEYS = array(
		'checkpoint_schema_version',
		'stage',
		'batch_index',
		'segment_ids',
		'last_item_id',
	);

	/**
	 * Forbidden substrings in checkpoint keys (case-insensitive).
	 *
	 * @var string[]
	 */
	private const FORBIDDEN_KEY_FRAGMENTS = array(
		'prompt',
		'source',
		'body',
		'secret',
		'api_key',
		'password',
		'token',
		'translated',
		'translation_text',
		'source_text',
	);

	/**
	 * Encode a checkpoint array for persistence.
	 *
	 * @param array<string, mixed> $data Checkpoint payload.
	 * @return string|null|WP_Error JSON string, null when empty, or error.
	 */
	public static function encode( array $data ) {
		if ( array() === $data ) {
			return null;
		}

		$validation = self::validate_keys( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$normalized = self::normalize( $data );
		if ( array() === $normalized ) {
			return null;
		}

		$json = wp_json_encode( $normalized );
		if ( false === $json || ! is_string( $json ) ) {
			return new WP_Error( 'job_checkpoint_encode_failed', 'Failed to encode job checkpoint.' );
		}

		if ( strlen( $json ) > self::SOFT_CAP_BYTES ) {
			return new WP_Error( 'job_checkpoint_too_large', 'Job checkpoint exceeds soft size cap.' );
		}

		return $json;
	}

	/**
	 * Decode a persisted checkpoint string.
	 *
	 * @param string|null $json Stored checkpoint JSON.
	 * @return array<string, mixed>
	 */
	public static function decode( ?string $json ): array {
		if ( null === $json || '' === $json ) {
			return array();
		}

		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		return $decoded;
	}

	/**
	 * Validate checkpoint keys against allowlist and forbidden fragments.
	 *
	 * @param array<string, mixed> $data Checkpoint payload.
	 * @return true|WP_Error
	 */
	private static function validate_keys( array $data ) {
		foreach ( array_keys( $data ) as $key ) {
			$key_string = (string) $key;

			if ( ! in_array( $key_string, self::ALLOWED_KEYS, true ) ) {
				return new WP_Error( 'job_checkpoint_invalid_key', 'Checkpoint key is not allowlisted.' );
			}

			$lower = strtolower( $key_string );
			foreach ( self::FORBIDDEN_KEY_FRAGMENTS as $fragment ) {
				if ( str_contains( $lower, $fragment ) ) {
					return new WP_Error( 'job_checkpoint_forbidden_key', 'Checkpoint key contains forbidden fragment.' );
				}
			}
		}

		return true;
	}

	/**
	 * Normalize allowlisted values for stable JSON encoding.
	 *
	 * @param array<string, mixed> $data Checkpoint payload.
	 * @return array<string, mixed>
	 */
	private static function normalize( array $data ): array {
		$out = array();

		foreach ( self::ALLOWED_KEYS as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			$value = $data[ $key ];
			if ( 'segment_ids' === $key ) {
				if ( ! is_array( $value ) ) {
					continue;
				}
				$out[ $key ] = array_values(
					array_map(
						static fn( $segment_id ): string => (string) $segment_id,
						$value
					)
				);
				continue;
			}

			if ( 'batch_index' === $key || 'last_item_id' === $key || 'checkpoint_schema_version' === $key ) {
				$out[ $key ] = (int) $value;
				continue;
			}

			$out[ $key ] = (string) $value;
		}

		return $out;
	}
}
