<?php
/**
 * Integration API v1 frozen constants.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration;

/**
 * Public contract constants for Integration API v1.
 */
final class Contract {

	/**
	 * Public Integration API version string.
	 */
	public const API_VERSION = 'v1';

	/**
	 * Reserved segment-key family prefix (distinct from Gutenberg `b` and Elementor `e`).
	 */
	public const SEGMENT_KEY_PREFIX = 'p';

	/**
	 * Store field_key for plugin-integration units.
	 */
	public const FIELD_KEY = '_plugin';

	/**
	 * Store segment_key VARCHAR ceiling — must not be exceeded; no silent truncation.
	 */
	public const MAX_SEGMENT_KEY_LENGTH = 191;

	/**
	 * Maximum length for integration_id tokens.
	 */
	public const MAX_INTEGRATION_ID_LENGTH = 32;

	/**
	 * Maximum length for owner_type / field / nested tokens.
	 */
	public const MAX_TOKEN_LENGTH = 48;

	/**
	 * Maximum length for owner_id tokens.
	 */
	public const MAX_OWNER_ID_LENGTH = 64;

	/**
	 * Maximum nested identity components after field.
	 */
	public const MAX_NESTED_COMPONENTS = 3;

	/**
	 * Token character class (ASCII only; Unicode rejected).
	 */
	public const TOKEN_PATTERN = '/^[A-Za-z0-9_-]+$/';

	/**
	 * Integration ID must be lowercase-safe.
	 */
	public const INTEGRATION_ID_PATTERN = '/^[a-z0-9_-]+$/';

	/**
	 * Ownership classification vocabulary.
	 */
	public const OWNERSHIP_RECORD            = 'record-owned';
	public const OWNERSHIP_DOCUMENT          = 'document-owned';
	public const OWNERSHIP_SHARED_DEFINITION = 'shared-definition-owned';
	public const OWNERSHIP_RUNTIME_DYNAMIC   = 'runtime/dynamic';
	public const OWNERSHIP_UNSUPPORTED       = 'unsupported/ambiguous';

	/**
	 * Compatibility states.
	 */
	public const STATE_AVAILABLE             = 'available';
	public const STATE_UNAVAILABLE           = 'unavailable';
	public const STATE_COMPATIBLE            = 'compatible';
	public const STATE_UNSUPPORTED_VERSION   = 'unsupported_version';
	public const STATE_MISSING_REQUIRED_HOOK = 'missing_required_hook';
	public const STATE_DISABLED              = 'disabled';
	public const STATE_DEGRADED              = 'degraded';

	/**
	 * Public Integration text formats for descriptor creation.
	 */
	public const FORMAT_PLAIN = 'plain';
	public const FORMAT_HTML  = 'html';

	/**
	 * Frozen ownership classification vocabulary.
	 *
	 * @return list<string>
	 */
	public static function ownership_classes(): array {
		return array(
			self::OWNERSHIP_RECORD,
			self::OWNERSHIP_DOCUMENT,
			self::OWNERSHIP_SHARED_DEFINITION,
			self::OWNERSHIP_RUNTIME_DYNAMIC,
			self::OWNERSHIP_UNSUPPORTED,
		);
	}

	/**
	 * Frozen compatibility state vocabulary.
	 *
	 * @return list<string>
	 */
	public static function compatibility_states(): array {
		return array(
			self::STATE_AVAILABLE,
			self::STATE_UNAVAILABLE,
			self::STATE_COMPATIBLE,
			self::STATE_UNSUPPORTED_VERSION,
			self::STATE_MISSING_REQUIRED_HOOK,
			self::STATE_DISABLED,
			self::STATE_DEGRADED,
		);
	}

	/**
	 * Public descriptor text-format vocabulary.
	 *
	 * @return list<string>
	 */
	public static function text_formats(): array {
		return array(
			self::FORMAT_PLAIN,
			self::FORMAT_HTML,
		);
	}
}
