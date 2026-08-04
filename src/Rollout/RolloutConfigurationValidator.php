<?php
/**
 * Validates and normalizes rollout configuration arrays.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Pure validation — rejects percentage/hash cohort fields and unknown languages.
 */
final class RolloutConfigurationValidator {

	/**
	 * Rejected keys that indicate unsupported cohort strategies.
	 *
	 * @var list<string>
	 */
	private const REJECTED_COHORT_KEYS = array(
		'cohort_percentage',
		'cohort_hash',
		'cohort_hash_seed',
		'visitor_cohort',
		'tenant_cohort',
		'organization_cohort',
	);

	/**
	 * Validates raw configuration input.
	 *
	 * @param mixed             $raw               Raw option value.
	 * @param list<string>|null $configured_codes  Known language codes (null skips language check).
	 */
	public function validate( $raw, ?array $configured_codes = null ): RolloutConfigurationValidationResult {
		if ( ! is_array( $raw ) ) {
			return RolloutConfigurationValidationResult::fail( array( 'not_array' ) );
		}

		foreach ( self::REJECTED_COHORT_KEYS as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				return RolloutConfigurationValidationResult::fail( array( 'unsupported_cohort_field:' . $key ) );
			}
		}

		$schema = isset( $raw['schema_version'] ) ? (int) $raw['schema_version'] : 0;
		if ( RolloutConfiguration::SCHEMA_VERSION !== $schema ) {
			return RolloutConfigurationValidationResult::fail( array( 'unsupported_schema_version' ) );
		}

		$stage = isset( $raw['rollout_stage'] ) ? (int) $raw['rollout_stage'] : -1;
		if ( $stage < 0 || $stage > 5 ) {
			return RolloutConfigurationValidationResult::fail( array( 'invalid_rollout_stage' ) );
		}

		$post_ids = $this->normalize_post_ids( $raw['allowed_post_ids'] ?? array() );
		if ( null === $post_ids ) {
			return RolloutConfigurationValidationResult::fail( array( 'invalid_post_ids' ) );
		}

		$post_types = $this->normalize_post_types( $raw['allowed_post_types'] ?? RolloutConfiguration::APPROVED_POST_TYPES );
		if ( null === $post_types ) {
			return RolloutConfigurationValidationResult::fail( array( 'invalid_post_types' ) );
		}

		$lang_codes = $this->normalize_language_codes( $raw['allowed_language_codes'] ?? array() );
		if ( null === $lang_codes ) {
			return RolloutConfigurationValidationResult::fail( array( 'invalid_language_codes' ) );
		}

		if ( null !== $configured_codes && array() !== $lang_codes ) {
			foreach ( $lang_codes as $code ) {
				if ( ! in_array( $code, $configured_codes, true ) ) {
					return RolloutConfigurationValidationResult::fail( array( 'unknown_language_code:' . $code ) );
				}
			}
		}

		$policy_version = max( 1, (int) ( $raw['policy_version'] ?? 1 ) );
		$updated_at     = isset( $raw['updated_at'] ) ? trim( (string) $raw['updated_at'] ) : '1970-01-01T00:00:00+00:00';
		$updated_by     = max( 0, (int) ( $raw['updated_by'] ?? 0 ) );

		$data = array(
			'schema_version'            => RolloutConfiguration::SCHEMA_VERSION,
			'policy_version'            => $policy_version,
			'rollout_stage'             => $stage,
			'rollout_render_enabled'    => ! empty( $raw['rollout_render_enabled'] ),
			'allowed_post_ids'          => $post_ids,
			'allowed_post_types'        => $post_types,
			'allowed_language_codes'    => $lang_codes,
			'render_cache_enabled'      => ! empty( $raw['render_cache_enabled'] ),
			'block_diagnostics_enabled' => ! empty( $raw['block_diagnostics_enabled'] ),
			'updated_at'                => $updated_at,
			'updated_by'                => $updated_by,
		);

		return RolloutConfigurationValidationResult::ok(
			RolloutConfigurationFactory::from_validated_array( $data )
		);
	}

	/**
	 * Normalizes post IDs to unique positive integers.
	 *
	 * @param mixed $raw Raw post ID list.
	 * @return list<int>|null Null when invalid.
	 */
	private function normalize_post_ids( $raw ): ?array {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$ids = array();
		foreach ( $raw as $value ) {
			$id = (int) $value;
			if ( $id <= 0 ) {
				return null;
			}
			$ids[ $id ] = $id;
		}

		return array_values( $ids );
	}

	/**
	 * Normalizes post types to approved subset.
	 *
	 * @param mixed $raw Raw post type list.
	 * @return list<string>|null Null when invalid.
	 */
	private function normalize_post_types( $raw ): ?array {
		if ( ! is_array( $raw ) || array() === $raw ) {
			return null;
		}

		$types = array();
		foreach ( $raw as $value ) {
			$type = strtolower( trim( (string) $value ) );
			if ( ! in_array( $type, RolloutConfiguration::APPROVED_POST_TYPES, true ) ) {
				return null;
			}
			$types[ $type ] = $type;
		}

		return array_values( $types );
	}

	/**
	 * Normalizes language codes.
	 *
	 * @param mixed $raw Raw language code list.
	 * @return list<string>|null Null when invalid.
	 */
	private function normalize_language_codes( $raw ): ?array {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$codes = array();
		foreach ( $raw as $value ) {
			$code = strtolower( trim( (string) $value ) );
			if ( '' === $code || ! preg_match( '/^[a-z0-9\-]+$/', $code ) ) {
				return null;
			}
			$codes[ $code ] = $code;
		}

		return array_values( $codes );
	}
}
