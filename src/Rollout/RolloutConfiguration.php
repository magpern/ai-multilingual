<?php
/**
 * Frozen F12 rollout configuration value object.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Immutable rollout policy configuration (current schema only).
 */
final class RolloutConfiguration {

	public const SCHEMA_VERSION = 1;

	/**
	 * Approved post types for limited rollout.
	 *
	 * @var list<string>
	 */
	public const APPROVED_POST_TYPES = array( 'post', 'page' );

	/**
	 * Stages that require a non-empty post allowlist when rollout render is on.
	 *
	 * @var list<int>
	 */
	public const LIMITED_ROLLOUT_STAGES = array( 1, 2, 3, 4 );

	/**
	 * Builds a configuration value object.
	 *
	 * @param int          $schema_version           Configuration structure version.
	 * @param int          $policy_version           Operator-visible policy revision.
	 * @param int          $rollout_stage            Stage 0–5.
	 * @param bool         $rollout_render_enabled   Enables cohort evaluation.
	 * @param list<int>    $allowed_post_ids         Normalized positive post IDs.
	 * @param list<string> $allowed_post_types       Subset of approved post types.
	 * @param list<string> $allowed_language_codes   Configured language codes.
	 * @param bool         $render_cache_enabled     Render cache flag (default off).
	 * @param bool         $block_diagnostics_enabled Diagnostics verbosity.
	 * @param string       $updated_at               GMT ISO timestamp.
	 * @param int          $updated_by               Acting user ID.
	 */
	public function __construct(
		public readonly int $schema_version,
		public readonly int $policy_version,
		public readonly int $rollout_stage,
		public readonly bool $rollout_render_enabled,
		public readonly array $allowed_post_ids,
		public readonly array $allowed_post_types,
		public readonly array $allowed_language_codes,
		public readonly bool $render_cache_enabled,
		public readonly bool $block_diagnostics_enabled,
		public readonly string $updated_at,
		public readonly int $updated_by,
	) {
	}

	/**
	 * Default disabled configuration for stage 0.
	 */
	public static function defaults(): self {
		return new self(
			self::SCHEMA_VERSION,
			1,
			0,
			false,
			array(),
			self::APPROVED_POST_TYPES,
			array(),
			false,
			false,
			'1970-01-01T00:00:00+00:00',
			0,
		);
	}

	/**
	 * Whether stage 1 shadow evaluation is active.
	 */
	public function is_shadow_stage(): bool {
		return 1 === $this->rollout_stage;
	}

	/**
	 * Whether limited-rollout stages require a non-empty post allowlist.
	 */
	public function requires_post_allowlist(): bool {
		return in_array( $this->rollout_stage, self::LIMITED_ROLLOUT_STAGES, true )
			&& $this->rollout_render_enabled;
	}

	/**
	 * Serializes to a storable array (sanitized).
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'schema_version'              => $this->schema_version,
			'policy_version'              => $this->policy_version,
			'rollout_stage'               => $this->rollout_stage,
			'rollout_render_enabled'      => $this->rollout_render_enabled,
			'allowed_post_ids'            => $this->allowed_post_ids,
			'allowed_post_types'          => $this->allowed_post_types,
			'allowed_language_codes'      => $this->allowed_language_codes,
			'render_cache_enabled'        => $this->render_cache_enabled,
			'block_diagnostics_enabled'   => $this->block_diagnostics_enabled,
			'updated_at'                  => $this->updated_at,
			'updated_by'                  => $this->updated_by,
		);
	}

	/**
	 * Returns a copy with one or more fields replaced.
	 *
	 * @param array<string, mixed> $overrides Field overrides.
	 */
	public function with( array $overrides ): self {
		$data = $this->to_array();

		foreach ( $overrides as $key => $value ) {
			$data[ $key ] = $value;
		}

		return RolloutConfigurationFactory::from_validated_array( $data );
	}
}
