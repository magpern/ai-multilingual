<?php
/**
 * Rollout configuration validation result.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Outcome of validating a raw configuration array.
 */
final class RolloutConfigurationValidationResult {

	/**
	 * @param bool                          $valid  Whether validation passed.
	 * @param RolloutConfiguration|null     $config Validated configuration when valid.
	 * @param list<string>                  $errors Human-readable error codes/messages.
	 */
	public function __construct(
		public readonly bool $valid,
		public readonly ?RolloutConfiguration $config,
		public readonly array $errors,
	) {
	}

	/**
	 * Creates a successful result.
	 */
	public static function ok( RolloutConfiguration $config ): self {
		return new self( true, $config, array() );
	}

	/**
	 * Creates a failed result.
	 *
	 * @param list<string> $errors Validation errors.
	 */
	public static function fail( array $errors ): self {
		return new self( false, null, $errors );
	}
}
