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
			return $this->migrate_v0_to_v1( $raw );
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
		$defaults = RolloutConfiguration::defaults()->to_array();

		$merged = array_merge(
			$defaults,
			array_intersect_key( $raw, array_flip( array_keys( $defaults ) ) )
		);

		$merged['schema_version'] = RolloutConfiguration::SCHEMA_VERSION;

		return $merged;
	}
}
