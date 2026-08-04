<?php
/**
 * Sanitized rollout configuration snapshot store.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Retains prior policy snapshots keyed by policy_version for restore.
 */
final class RolloutSnapshotStore {

	public const OPTION = 'aiml_rollout_snapshots';

	/**
	 * Maximum snapshots retained.
	 */
	private const MAX_SNAPSHOTS = 50;

	/**
	 * Stores a sanitized snapshot before a policy change.
	 *
	 * @param RolloutConfiguration $configuration Configuration to snapshot.
	 */
	public function store( RolloutConfiguration $configuration ): void {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return;
		}

		$snapshots = $this->load_all();
		$key       = (string) $configuration->policy_version;

		$snapshots[ $key ] = $this->sanitize_snapshot( $configuration->to_array() );

		ksort( $snapshots, SORT_NUMERIC );

		if ( count( $snapshots ) > self::MAX_SNAPSHOTS ) {
			$snapshots = array_slice( $snapshots, -self::MAX_SNAPSHOTS, null, true );
		}

		update_option( self::OPTION, $snapshots, false );
	}

	/**
	 * Returns a snapshot by policy version.
	 *
	 * @param int $policy_version Snapshot policy version.
	 * @return array<string, mixed>|null
	 */
	public function get( int $policy_version ): ?array {
		$snapshots = $this->load_all();
		$key       = (string) $policy_version;

		if ( ! isset( $snapshots[ $key ] ) || ! is_array( $snapshots[ $key ] ) ) {
			return null;
		}

		return $snapshots[ $key ];
	}

	/**
	 * Lists recent policy versions with snapshots.
	 *
	 * @return list<int>
	 */
	public function list_versions(): array {
		$snapshots = $this->load_all();
		$versions  = array_map( 'intval', array_keys( $snapshots ) );
		sort( $versions, SORT_NUMERIC );

		return $versions;
	}

	/**
	 * Loads all stored snapshots from the option table.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function load_all(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}

		$raw = get_option( self::OPTION, array() );

		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Strips any unexpected keys from a snapshot payload.
	 *
	 * @param array<string, mixed> $data Configuration array.
	 * @return array<string, mixed>
	 */
	private function sanitize_snapshot( array $data ): array {
		$allowed = array_keys( RolloutConfiguration::defaults()->to_array() );

		return array_intersect_key( $data, array_flip( $allowed ) );
	}
}
