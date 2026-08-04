<?php
/**
 * Rollout configuration persistence.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Loads, validates, saves, and restores rollout configuration atomically.
 */
final class RolloutConfigurationRepository {

	public const OPTION = 'aiml_rollout_config';

	/**
	 * Builds the repository.
	 *
	 * @param RolloutConfigurationValidator|null $validator Optional validator override.
	 * @param RolloutConfigurationMigrator|null  $migrator  Optional migrator override.
	 * @param RolloutSnapshotStore|null          $snapshots Optional snapshot store override.
	 */
	public function __construct(
		private ?RolloutConfigurationValidator $validator = null,
		private ?RolloutConfigurationMigrator $migrator = null,
		private ?RolloutSnapshotStore $snapshots = null,
	) {
		$this->validator = $validator ?? new RolloutConfigurationValidator();
		$this->migrator  = $migrator ?? new RolloutConfigurationMigrator();
		$this->snapshots = $snapshots ?? new RolloutSnapshotStore();
	}

	/**
	 * Returns the active validated configuration.
	 *
	 * Malformed stored config fails closed to defaults.
	 *
	 * @param list<string>|null $configured_language_codes Known language codes for validation.
	 */
	public function get( ?array $configured_language_codes = null ): RolloutConfiguration {
		$raw = function_exists( 'get_option' )
			? get_option( self::OPTION, null )
			: null;

		if ( null === $raw ) {
			return RolloutConfiguration::defaults();
		}

		$migrated = $this->migrator->migrate( $raw );
		if ( null === $migrated ) {
			return RolloutConfiguration::defaults();
		}

		$result = $this->validator->validate( $migrated, $configured_language_codes );

		return $result->valid && null !== $result->config
			? $result->config
			: RolloutConfiguration::defaults();
	}

	/**
	 * Validates a proposed configuration without persisting.
	 *
	 * @param array<string, mixed> $proposed                  Raw proposed config.
	 * @param list<string>|null    $configured_language_codes Known language codes.
	 */
	public function validate_proposed( array $proposed, ?array $configured_language_codes = null ): RolloutConfigurationValidationResult {
		$migrated = $this->migrator->migrate( $proposed );

		if ( null === $migrated ) {
			return RolloutConfigurationValidationResult::fail( array( 'migration_failed' ) );
		}

		return $this->validator->validate( $migrated, $configured_language_codes );
	}

	/**
	 * Saves a validated configuration atomically, retaining a snapshot and bumping policy_version.
	 *
	 * @param RolloutConfiguration $next        New configuration (policy_version should already be incremented).
	 * @param int                  $updated_by  Acting user ID.
	 */
	public function save( RolloutConfiguration $next, int $updated_by ): bool {
		if ( ! function_exists( 'update_option' ) ) {
			return false;
		}

		$current = $this->get();

		$this->snapshots->store( $current );

		$payload = $next->with(
			array(
				'updated_by' => max( 0, $updated_by ),
				'updated_at' => gmdate( 'c' ),
			)
		);

		return update_option( self::OPTION, $payload->to_array(), false );
	}

	/**
	 * Applies a proposed change with automatic policy_version increment.
	 *
	 * @param array<string, mixed> $proposed                  Proposed fields.
	 * @param int                  $updated_by                Acting user ID.
	 * @param list<string>|null    $configured_language_codes Known language codes.
	 */
	public function apply_change(
		array $proposed,
		int $updated_by,
		?array $configured_language_codes = null,
	): RolloutConfigurationValidationResult {
		$current = $this->get( $configured_language_codes );

		$merged = array_merge( $current->to_array(), $proposed );
		$merged['policy_version'] = $current->policy_version + 1;
		$merged['schema_version'] = RolloutConfiguration::SCHEMA_VERSION;

		$result = $this->validate_proposed( $merged, $configured_language_codes );
		if ( ! $result->valid || null === $result->config ) {
			return $result;
		}

		if ( ! $this->save( $result->config, $updated_by ) ) {
			return RolloutConfigurationValidationResult::fail( array( 'persist_failed' ) );
		}

		return $result;
	}

	/**
	 * Restores a prior snapshot atomically and increments policy_version.
	 *
	 * @param int                  $policy_version            Snapshot version to restore.
	 * @param int                  $updated_by                Acting user ID.
	 * @param list<string>|null    $configured_language_codes Known language codes.
	 */
	public function restore(
		int $policy_version,
		int $updated_by,
		?array $configured_language_codes = null,
	): RolloutConfigurationValidationResult {
		$snapshot = $this->snapshots->get( $policy_version );
		if ( null === $snapshot ) {
			return RolloutConfigurationValidationResult::fail( array( 'snapshot_not_found' ) );
		}

		$current = $this->get( $configured_language_codes );

		$merged = array_merge( $snapshot, array(
			'policy_version' => $current->policy_version + 1,
			'schema_version' => RolloutConfiguration::SCHEMA_VERSION,
		) );

		$result = $this->validate_proposed( $merged, $configured_language_codes );
		if ( ! $result->valid || null === $result->config ) {
			return $result;
		}

		if ( ! $this->save( $result->config, $updated_by ) ) {
			return RolloutConfigurationValidationResult::fail( array( 'restore_persist_failed' ) );
		}

		return $result;
	}

	/**
	 * Exports the current sanitized configuration array.
	 *
	 * @return array<string, mixed>
	 */
	public function export(): array {
		return $this->get()->to_array();
	}

	/**
	 * Lists stored snapshot policy versions.
	 *
	 * @return list<int>
	 */
	public function list_snapshot_versions(): array {
		return $this->snapshots->list_versions();
	}
}
