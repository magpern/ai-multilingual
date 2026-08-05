<?php
/**
 * Migrates legacy rollout configuration to the current schema.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Schema migration happens before runtime use — not inside policy evaluation.
 */
final class RolloutConfigurationMigrator {

	/**
	 * Migrates raw stored configuration to the current schema shape.
	 *
	 * Returns null when the input cannot be migrated.
	 *
	 * @param mixed $raw Raw option value.
	 * @return array<string, mixed>|null Migrated array for validation.
	 */
	public function migrate( $raw ): ?array {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$schema = (int) ( $raw['schema_version'] ?? 0 );

		if ( 0 === $schema ) {
			$raw    = $this->migrate_v0_to_v1( $raw );
			$schema = 1;
		}

		if ( 1 === $schema ) {
			$raw    = $this->migrate_v1_to_v2( $raw );
			$schema = 2;
		}

		if ( RolloutConfiguration::SCHEMA_VERSION === $schema ) {
			return $raw;
		}

		return null;
	}

	/**
	 * Bootstraps schema version 1 from an empty or legacy stub.
	 *
	 * @param array<string, mixed> $raw Legacy raw array.
	 * @return array<string, mixed>
	 */
	private function migrate_v0_to_v1( array $raw ): array {
		$v1_keys = array(
			'schema_version',
			'policy_version',
			'rollout_stage',
			'rollout_render_enabled',
			'allowed_post_ids',
			'allowed_post_types',
			'allowed_language_codes',
			'render_cache_enabled',
			'block_diagnostics_enabled',
			'updated_at',
			'updated_by',
		);

		$defaults = array(
			'schema_version'            => 1,
			'policy_version'            => 1,
			'rollout_stage'             => 0,
			'rollout_render_enabled'    => false,
			'allowed_post_ids'          => array(),
			'allowed_post_types'        => RolloutConfiguration::APPROVED_POST_TYPES,
			'allowed_language_codes'    => array(),
			'render_cache_enabled'      => false,
			'block_diagnostics_enabled' => false,
			'updated_at'                => '1970-01-01T00:00:00+00:00',
			'updated_by'                => 0,
		);

		$merged = array_merge(
			$defaults,
			array_intersect_key( $raw, array_flip( $v1_keys ) )
		);

		$merged['schema_version'] = 1;

		return $merged;
	}

	/**
	 * Upgrades schema version 1 to version 2 (adds general_rollout_enabled).
	 *
	 * @param array<string, mixed> $raw Schema v1 array.
	 * @return array<string, mixed>
	 */
	private function migrate_v1_to_v2( array $raw ): array {
		$defaults = RolloutConfiguration::defaults()->to_array();

		$merged = array_merge(
			$defaults,
			array_intersect_key( $raw, array_flip( array_keys( $defaults ) ) )
		);

		$merged['general_rollout_enabled'] = ! empty( $raw['general_rollout_enabled'] );
		$merged['schema_version']          = RolloutConfiguration::SCHEMA_VERSION;

		return $merged;
	}
}
