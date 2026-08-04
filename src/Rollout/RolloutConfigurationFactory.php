<?php
/**
 * Builds {@see RolloutConfiguration} from validated arrays.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Internal factory for validated configuration arrays.
 */
final class RolloutConfigurationFactory {

	/**
	 * Builds from an array that passed {@see RolloutConfigurationValidator}.
	 *
	 * @param array<string, mixed> $data Validated configuration array.
	 */
	public static function from_validated_array( array $data ): RolloutConfiguration {
		return new RolloutConfiguration(
			(int) $data['schema_version'],
			(int) $data['policy_version'],
			(int) $data['rollout_stage'],
			! empty( $data['rollout_render_enabled'] ),
			array_values( array_map( 'intval', (array) $data['allowed_post_ids'] ) ),
			array_values( array_map( 'strval', (array) $data['allowed_post_types'] ) ),
			array_values( array_map( 'strval', (array) $data['allowed_language_codes'] ) ),
			! empty( $data['render_cache_enabled'] ),
			! empty( $data['block_diagnostics_enabled'] ),
			(string) $data['updated_at'],
			(int) $data['updated_by'],
		);
	}
}
