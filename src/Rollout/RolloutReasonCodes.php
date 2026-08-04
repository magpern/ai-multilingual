<?php
/**
 * Frozen F12 rollout policy reason codes.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Stable operator diagnostics — not localization keys or user-facing copy.
 */
final class RolloutReasonCodes {

	public const ROLLOUT_DISABLED         = 'rollout_disabled';
	public const STAGE_DISABLED           = 'stage_disabled';
	public const POST_NOT_ALLOWLISTED     = 'post_not_allowlisted';
	public const POST_TYPE_NOT_ALLOWED    = 'post_type_not_allowed';
	public const LANGUAGE_NOT_ALLOWED     = 'language_not_allowed';
	public const UNSUPPORTED_REQUEST      = 'unsupported_request';
	public const INVALID_CONFIGURATION    = 'invalid_configuration';
	public const POLICY_ERROR             = 'policy_error';
	public const ALLOWED                  = 'allowed';

	/**
	 * Additive-only catalog after F12.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::ROLLOUT_DISABLED,
			self::STAGE_DISABLED,
			self::POST_NOT_ALLOWLISTED,
			self::POST_TYPE_NOT_ALLOWED,
			self::LANGUAGE_NOT_ALLOWED,
			self::UNSUPPORTED_REQUEST,
			self::INVALID_CONFIGURATION,
			self::POLICY_ERROR,
			self::ALLOWED,
		);
	}

	/**
	 * Whether a reason code is in the frozen catalog.
	 */
	public static function is_known( string $code ): bool {
		return in_array( $code, self::all(), true );
	}
}
