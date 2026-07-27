<?php
/**
 * Strategy F feature-flag dependency validation.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

use AIMultilingual\SettingsOperationalLogger;

/**
 * Validates Strategy F flag combinations.
 *
 * F1 exposes only registration in {@see Settings}. Additional flags are defined
 * here so dependency rules can be unit-tested before later milestones wire them
 * into settings UI (F8).
 */
final class FeatureFlags {

	public const REGISTRATION = 'block_attr_registration_enabled';

	public const ANALYSIS = 'block_uuid_analysis_enabled';

	public const INJECTION = 'block_uuid_injection_enabled';

	public const REPAIR = 'block_uuid_repair_enabled';

	public const AUTOSAVE_INJECT = 'block_uuid_autosave_inject_enabled';

	public const EXTRACTION = 'block_extraction_enabled';

	public const RENDER = 'block_render_enabled';

	public const FRONTEND_RENDER = 'block_frontend_rendering_enabled';

	public const RENDERER_PROOF = 'block_renderer_proof_mode';

	public const MIGRATION = 'block_migration_enabled';

	public const DIAGNOSTICS = 'block_diagnostics_enabled';

	/**
	 * Production Strategy F flags exposed in admin settings (F8).
	 *
	 * @var list<string>
	 */
	public const PRODUCTION_FLAGS = array(
		self::REGISTRATION,
		self::INJECTION,
		self::EXTRACTION,
		self::FRONTEND_RENDER,
	);

	/**
	 * Enforces safe flag combinations by disabling dependent flags when prerequisites are off.
	 *
	 * @param array<string, mixed> $flags Flag map keyed by {@see self} constants.
	 * @return array<string, mixed> Sanitized flag map.
	 */
	public static function validate_dependencies( array $flags ): array {
		$flags = self::coerce_bools( $flags );

		if ( self::is_enabled( $flags, self::REPAIR ) && ! self::is_enabled( $flags, self::INJECTION ) ) {
			$flags[ self::REPAIR ] = false;
		}

		if ( self::is_enabled( $flags, self::INJECTION ) && ! self::is_enabled( $flags, self::REGISTRATION ) ) {
			$flags[ self::INJECTION ] = false;
			$flags[ self::REPAIR ]    = false;
		}

		if ( self::is_enabled( $flags, self::EXTRACTION )
			&& ( ! self::is_enabled( $flags, self::INJECTION ) || ! self::is_enabled( $flags, self::REGISTRATION ) ) ) {
			$flags[ self::EXTRACTION ] = false;
		}

		if ( self::is_enabled( $flags, self::RENDER )
			&& ( ! self::is_enabled( $flags, self::EXTRACTION ) || ! self::is_enabled( $flags, self::REGISTRATION ) ) ) {
			$flags[ self::RENDER ] = false;
		}

		if ( self::is_enabled( $flags, self::FRONTEND_RENDER )
			&& ( ! self::is_enabled( $flags, self::EXTRACTION )
				|| ! self::is_enabled( $flags, self::INJECTION )
				|| ! self::is_enabled( $flags, self::REGISTRATION ) ) ) {
			$flags[ self::FRONTEND_RENDER ] = false;
		}

		if ( self::is_enabled( $flags, self::AUTOSAVE_INJECT ) && ! self::is_enabled( $flags, self::INJECTION ) ) {
			$flags[ self::AUTOSAVE_INJECT ] = false;
		}

		return $flags;
	}

	/**
	 * Whether a flag combination is prohibited.
	 *
	 * @param array<string, mixed> $flags Flag map keyed by {@see self} constants.
	 */
	public static function has_prohibited_combination( array $flags ): bool {
		return array() !== self::flags_dropped_by_validation( $flags );
	}

	/**
	 * Production flags the submitter requested but dependency validation removed.
	 *
	 * @param array<string, mixed> $flags Flag map reflecting submitted intent.
	 * @return list<string> Flag keys dropped by {@see self::validate_dependencies()}.
	 */
	public static function flags_dropped_by_validation( array $flags ): array {
		$sanitized = self::validate_dependencies( $flags );
		$dropped   = array();

		foreach ( array( self::REPAIR, self::INJECTION, self::EXTRACTION, self::RENDER, self::FRONTEND_RENDER, self::AUTOSAVE_INJECT ) as $key ) {
			if ( self::is_enabled( $flags, $key ) && ! self::is_enabled( $sanitized, $key ) ) {
				$dropped[] = $key;
			}
		}

		return $dropped;
	}

	/**
	 * Whether submitted production-flag checkboxes differ from stored settings.
	 *
	 * @param array<string, mixed> $raw      Raw settings form post body.
	 * @param array<string, mixed> $previous Sanitized settings before save.
	 */
	public static function production_flags_submission_changed( array $raw, array $previous ): bool {
		$submitted = self::production_flags_from_raw( $raw );

		foreach ( self::PRODUCTION_FLAGS as $flag ) {
			$old = self::is_enabled( $previous, $flag );
			$new = $submitted[ $flag ];

			if ( $old !== $new ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds a bounded audit payload when submitted production flags were normalized away.
	 *
	 * @param array<string, mixed> $raw   Raw settings form post body.
	 * @param array<string, mixed> $clean Sanitized settings after dependency validation.
	 * @return array<string, mixed>|null Audit payload, or null when no rejection occurred.
	 */
	public static function flag_rejection_payload( array $raw, array $clean ): ?array {
		$submitted = self::production_flags_from_raw( $raw );
		$dropped   = self::flags_dropped_by_validation( $submitted );

		if ( array() === $dropped ) {
			return null;
		}

		$submitted_states = array();
		$effective_states = array();
		$prerequisite_map = array();

		foreach ( self::PRODUCTION_FLAGS as $flag ) {
			$submitted_states[ $flag ] = $submitted[ $flag ];
			$effective_states[ $flag ] = self::is_enabled( $clean, $flag );
		}

		foreach ( $dropped as $flag ) {
			$prerequisite_map[ $flag ] = self::missing_prerequisite_key( $flag, $submitted_states );
		}

		return array(
			'event'            => SettingsOperationalLogger::EVENT_FLAG_COMBO_REJECTED,
			'submitted'        => $submitted_states,
			'effective'        => $effective_states,
			'dropped_flags'    => $dropped,
			'prerequisite_map' => $prerequisite_map,
			'user_id'          => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
			'timestamp'        => time(),
			'source'           => 'admin_settings',
		);
	}

	/**
	 * Returns the first missing prerequisite for one dropped production flag.
	 *
	 * @param string              $flag      Dropped production flag key.
	 * @param array<string, bool> $submitted Submitted production-flag intent map.
	 */
	public static function missing_prerequisite_key( string $flag, array $submitted ): string {
		switch ( $flag ) {
			case self::INJECTION:
				return self::REGISTRATION;
			case self::EXTRACTION:
				if ( empty( $submitted[ self::REGISTRATION ] ) ) {
					return self::REGISTRATION;
				}

				return self::INJECTION;
			case self::FRONTEND_RENDER:
				if ( empty( $submitted[ self::REGISTRATION ] ) ) {
					return self::REGISTRATION;
				}

				if ( empty( $submitted[ self::INJECTION ] ) ) {
					return self::INJECTION;
				}

				return self::EXTRACTION;
			default:
				return '';
		}
	}

	/**
	 * Interprets submitted production-flag checkboxes from a settings form post.
	 *
	 * Unchecked boxes are omitted from the post body and treated as off.
	 *
	 * @param array<string, mixed> $raw Raw settings array from the form.
	 * @return array<string, bool> Production flag intent map.
	 */
	public static function production_flags_from_raw( array $raw ): array {
		$flags = array();

		foreach ( self::PRODUCTION_FLAGS as $key ) {
			$flags[ $key ] = array_key_exists( $key, $raw ) && self::to_bool( $raw[ $key ] );
		}

		return $flags;
	}

	/**
	 * Human-readable prerequisite label for a production flag key.
	 *
	 * @param string $flag Flag key from {@see self::PRODUCTION_FLAGS}.
	 */
	public static function prerequisite_label( string $flag ): string {
		switch ( $flag ) {
			case self::INJECTION:
				return self::REGISTRATION;
			case self::EXTRACTION:
				return self::INJECTION . ' + ' . self::REGISTRATION;
			case self::FRONTEND_RENDER:
				return self::EXTRACTION . ' + ' . self::INJECTION . ' + ' . self::REGISTRATION;
			default:
				return '';
		}
	}

	/**
	 * Whether a flag is enabled in the map.
	 *
	 * @param array<string, mixed> $flags Flag map.
	 * @param string               $key   Flag key.
	 */
	private static function is_enabled( array $flags, string $key ): bool {
		return ! empty( $flags[ $key ] );
	}

	/**
	 * Coerces flag values to booleans.
	 *
	 * @param array<string, mixed> $flags Flag map.
	 * @return array<string, mixed>
	 */
	private static function coerce_bools( array $flags ): array {
		foreach ( array_keys( $flags ) as $key ) {
			$flags[ $key ] = self::to_bool( $flags[ $key ] );
		}

		return $flags;
	}

	/**
	 * Interprets a loose truthy value as boolean.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return $value > 0;
		}

		return false;
	}
}
