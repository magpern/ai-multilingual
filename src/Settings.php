<?php
/**
 * Plugin settings.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual;

use AIMultilingual\Block\FeatureFlags;

/**
 * Sole owner of the `aiml_settings` option.
 *
 * `defaults()` and `sanitize()` are pure: they call no WordPress function,
 * touch no global state and never throw. Invalid input is dropped or coerced
 * rather than rejected, so a corrupted option can never make the plugin fatal.
 * That purity is what lets the settings suite run without a WordPress
 * bootstrap.
 *
 * Language configuration deliberately does NOT live here — languages are rows
 * in `aiml_languages`, because they are queried, indexed and referenced by
 * foreign key from the segment store.
 */
final class Settings {

	/**
	 * Option name.
	 */
	public const OPTION = 'aiml_settings';

	/**
	 * Shape version of the settings array (not the database schema version).
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * Lazily loaded, sanitized settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $data;

	/**
	 * Builds the settings accessor.
	 *
	 * @param array<string, mixed>|null $data Pre-loaded settings, for tests.
	 */
	public function __construct( ?array $data = null ) {
		$this->data = null === $data ? null : self::sanitize( $data );
	}

	/**
	 * Clears the in-memory settings cache so the next read loads from the database.
	 */
	public function reload(): void {
		$this->data = null;
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'schema_version'                       => self::SCHEMA_VERSION,

			/*
			 * Data retention on uninstall. Default off: translation work is
			 * expensive to recreate and deleting it must be a deliberate act
			 * (invariant I5).
			 */
			'remove_data_on_uninstall'             => false,

			// Language switcher presentation.
			'switcher_show_native_name'            => true,
			'switcher_hide_current'                => false,

			/*
			 * Strategy F (F1): block attribute registration.
			 *
			 * Default off so production behavior is unchanged until deliberately
			 * enabled in pre-rollout environments. After UUID rollout begins,
			 * registration becomes a compatibility requirement — not a normal
			 * post-rollout kill switch (see Strategy F plan §2.2).
			 */
			'block_attr_registration_enabled'      => false,

			/*
			 * Strategy F (F2): save-time UUID injection on canonical posts.
			 *
			 * Requires attribute registration. Default off so production
			 * behavior is unchanged until deliberately enabled.
			 */
			'block_uuid_injection_enabled'         => false,

			/*
			 * Strategy F (F4): block-level extraction for sync_source reconciliation.
			 *
			 * Requires attribute registration and UUID injection. Default off so
			 * production behavior is unchanged until deliberately enabled.
			 */
			'block_extraction_enabled'             => false,

			/*
			 * Strategy F (F6): gated frontend block rendering.
			 *
			 * Requires attribute registration, UUID injection, and block
			 * extraction. Default off so production behavior is unchanged until
			 * deliberately enabled.
			 */
			'block_frontend_rendering_enabled'     => false,

			/*
			 * A.2 Elementor Foundation: extraction of allowlisted widget controls.
			 * Default off until deliberately enabled after validation.
			 */
			'elementor_extraction_enabled'         => false,

			/*
			 * A.2 Elementor Foundation: request-time frontend overlays.
			 * Requires elementor_extraction_enabled. Default off.
			 */
			'elementor_frontend_rendering_enabled' => false,

			/*
			 * F11 AI provider configuration (server-side only; API key encrypted).
			 */
			'ai_enabled'                           => false,
			'ai_provider'                          => '',
			'ai_model'                             => '',
			'ai_api_key_encrypted'                 => '',
			'qa_block_on_error'                    => true,

			/*
			 * TI.7 / ADR-0020: segment publication gate (rollout). Default off so
			 * upgrades preserve pre-TI.7 overlay behavior until operators opt in.
			 */
			'segment_publication_gate_enabled'     => false,

			/*
			 * TI.7 / ADR-0020: automatic publication mode. Default manual —
			 * installing TI.7 must never begin auto-publication.
			 */
			'auto_publication_mode'                => 'manual',

			/*
			 * MSEO / ADR-0023: localized URL activation state machine.
			 * Default off — MSEO.0 exposes no administrator enable UI.
			 */
			'localized_urls_state'                 => 'off',
			'localized_urls_activation_checkpoint' => null,
			'localized_urls_activation_error'      => '',
		);
	}

	/**
	 * Coerces arbitrary input into a valid settings array.
	 *
	 * Pure: no WordPress functions, no exceptions, no side effects.
	 *
	 * @param mixed $raw Raw option value.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $raw ): array {
		$defaults = self::defaults();

		if ( ! is_array( $raw ) ) {
			return $defaults;
		}

		$clean = $defaults;

		foreach ( array( 'remove_data_on_uninstall', 'switcher_show_native_name', 'switcher_hide_current', 'block_attr_registration_enabled', 'block_uuid_injection_enabled', 'block_extraction_enabled', 'block_frontend_rendering_enabled', 'elementor_extraction_enabled', 'elementor_frontend_rendering_enabled', 'ai_enabled', 'qa_block_on_error', 'segment_publication_gate_enabled' ) as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$clean[ $key ] = self::to_bool( $raw[ $key ] );
			}
		}

		// Frontend Elementor overlays require extraction.
		if ( ! empty( $clean['elementor_frontend_rendering_enabled'] ) && empty( $clean['elementor_extraction_enabled'] ) ) {
			$clean['elementor_frontend_rendering_enabled'] = false;
		}

		if ( array_key_exists( 'ai_provider', $raw ) ) {
			$provider             = strtolower( trim( (string) $raw['ai_provider'] ) );
			$provider             = preg_replace( '/[^a-z0-9_\-]/', '', $provider ) ?? '';
			$allowed              = array( '', 'openai' );
			$clean['ai_provider'] = in_array( $provider, $allowed, true ) ? $provider : '';
		}

		if ( array_key_exists( 'auto_publication_mode', $raw ) ) {
			$mode                           = strtolower( trim( (string) $raw['auto_publication_mode'] ) );
			$allowed_modes                  = array( 'manual', 'approved_only', 'controlled_auto' );
			$clean['auto_publication_mode'] = in_array( $mode, $allowed_modes, true ) ? $mode : 'manual';
		}

		if ( array_key_exists( 'localized_urls_state', $raw ) ) {
			$state   = strtolower( trim( (string) $raw['localized_urls_state'] ) );
			$allowed = array( 'off', 'activating', 'on', 'failed' );
			$clean['localized_urls_state'] = in_array( $state, $allowed, true ) ? $state : 'off';
		}

		if ( array_key_exists( 'localized_urls_activation_checkpoint', $raw ) ) {
			$checkpoint = $raw['localized_urls_activation_checkpoint'];
			if ( null === $checkpoint || '' === $checkpoint ) {
				$clean['localized_urls_activation_checkpoint'] = null;
			} else {
				$clean['localized_urls_activation_checkpoint'] = substr( (string) $checkpoint, 0, 4096 );
			}
		}

		if ( array_key_exists( 'localized_urls_activation_error', $raw ) ) {
			$clean['localized_urls_activation_error'] = substr( (string) $raw['localized_urls_activation_error'], 0, 500 );
		}

		if ( array_key_exists( 'ai_model', $raw ) ) {
			$model             = trim( (string) $raw['ai_model'] );
			$model             = preg_replace( '/[^\w.\-\/: ]/', '', $model ) ?? '';
			$clean['ai_model'] = substr( $model, 0, 191 );
		}

		if ( array_key_exists( 'ai_api_key_encrypted', $raw ) ) {
			$key = (string) $raw['ai_api_key_encrypted'];
			// Only accept vault ciphertext or empty — never store plaintext via sanitize.
			if ( '' === $key || str_starts_with( $key, 'aiml1:' ) ) {
				$clean['ai_api_key_encrypted'] = substr( $key, 0, 4096 );
			}
		}

		$flag_keys  = FeatureFlags::PRODUCTION_FLAGS;
		$flag_slice = array();
		foreach ( $flag_keys as $flag_key ) {
			$flag_slice[ $flag_key ] = $clean[ $flag_key ] ?? false;
		}
		$flag_slice = FeatureFlags::validate_dependencies( $flag_slice );
		foreach ( $flag_keys as $flag_key ) {
			$clean[ $flag_key ] = ! empty( $flag_slice[ $flag_key ] );
		}

		$clean['schema_version'] = self::SCHEMA_VERSION;

		return $clean;
	}

	/**
	 * Interprets loose truthy values the way HTML form posts and options deliver them.
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

	/**
	 * Returns the sanitized settings, loading them on first use.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		if ( null === $this->data ) {
			$this->data = self::sanitize( get_option( self::OPTION ) );
		}

		return $this->data;
	}

	/**
	 * Sanitizes and persists settings.
	 *
	 * @param array<string, mixed> $settings Settings to store.
	 */
	public function save( array $settings ): void {
		$clean = self::sanitize( $settings );

		update_option( self::OPTION, $clean );

		$this->data = $clean;
	}

	/**
	 * Production Strategy F flag keys owned by {@see Settings::OPTION}.
	 *
	 * @return list<string>
	 */
	public static function production_flag_keys(): array {
		return FeatureFlags::PRODUCTION_FLAGS;
	}

	/**
	 * Emits {@see 'aiml_settings_flag_changed'} for each changed production flag.
	 *
	 * @param array<string, mixed> $previous Sanitized settings before save.
	 * @param array<string, mixed> $next     Sanitized settings after save.
	 * @param string               $source   Change origin identifier.
	 */
	public static function emit_flag_change_audit( array $previous, array $next, string $source = 'admin_settings' ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		foreach ( self::production_flag_keys() as $flag ) {
			$old = ! empty( $previous[ $flag ] );
			$new = ! empty( $next[ $flag ] );

			if ( $old === $new ) {
				continue;
			}

			/**
			 * Fires when a Strategy F production flag changes.
			 *
			 * @since 0.1.0
			 *
			 * @param array{
			 *     flag: string,
			 *     old: bool,
			 *     new: bool,
			 *     user_id: int,
			 *     timestamp: int,
			 *     source: string
			 * } $payload Audit payload (no content or secrets).
			 */
			do_action(
				'aiml_settings_flag_changed',
				array(
					'flag'      => $flag,
					'old'       => $old,
					'new'       => $new,
					'user_id'   => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
					'timestamp' => time(),
					'source'    => $source,
				)
			);
		}
	}

	/**
	 * Emits {@see SettingsOperationalLogger::EVENT_FLAG_COMBO_REJECTED} once per rejected save.
	 *
	 * @param array<string, mixed> $payload Bounded rejection audit payload.
	 */
	public static function emit_flag_combo_rejected( array $payload ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		$logger = new SettingsOperationalLogger();
		$logger->log(
			SettingsOperationalLogger::EVENT_FLAG_COMBO_REJECTED,
			$payload
		);
	}

	/**
	 * Whether uninstall should remove all plugin data.
	 */
	public function remove_data_on_uninstall(): bool {
		return (bool) $this->get()['remove_data_on_uninstall'];
	}

	/**
	 * Whether the switcher shows each language's native name.
	 */
	public function switcher_show_native_name(): bool {
		return (bool) $this->get()['switcher_show_native_name'];
	}

	/**
	 * Whether the switcher omits the language currently being viewed.
	 */
	public function switcher_hide_current(): bool {
		return (bool) $this->get()['switcher_hide_current'];
	}

	/**
	 * Whether Strategy F block attribute registration is active.
	 *
	 * Pre-rollout environments may disable this flag. After production UUID
	 * rollout, registration must remain enabled as a compatibility requirement.
	 */
	public function block_attr_registration_enabled(): bool {
		return (bool) $this->get()['block_attr_registration_enabled'];
	}

	/**
	 * Whether Strategy F UUID injection runs on canonical post saves.
	 *
	 * Requires {@see self::block_attr_registration_enabled()}.
	 */
	public function block_uuid_injection_enabled(): bool {
		return (bool) $this->get()['block_uuid_injection_enabled'];
	}

	/**
	 * Whether Strategy F block extraction runs during sync_source reconciliation.
	 *
	 * Requires {@see self::block_attr_registration_enabled()} and
	 * {@see self::block_uuid_injection_enabled()}.
	 */
	public function block_extraction_enabled(): bool {
		return (bool) $this->get()['block_extraction_enabled'];
	}

	/**
	 * Whether Strategy F frontend block rendering is active.
	 *
	 * Requires {@see self::block_attr_registration_enabled()},
	 * {@see self::block_uuid_injection_enabled()}, and
	 * {@see self::block_extraction_enabled()}.
	 */
	public function block_frontend_rendering_enabled(): bool {
		return (bool) $this->get()['block_frontend_rendering_enabled'];
	}

	/**
	 * Whether A.2 Elementor control extraction is active.
	 */
	public function elementor_extraction_enabled(): bool {
		return (bool) $this->get()['elementor_extraction_enabled'];
	}

	/**
	 * Whether A.2 Elementor frontend overlays are active.
	 *
	 * Requires {@see self::elementor_extraction_enabled()}.
	 */
	public function elementor_frontend_rendering_enabled(): bool {
		return (bool) $this->get()['elementor_frontend_rendering_enabled'];
	}

	/**
	 * Localized URL activation state (ADR-0023).
	 */
	public function localized_urls_state(): string {
		return (string) ( $this->get()['localized_urls_state'] ?? 'off' );
	}

	/**
	 * Whether localized URL generation is enabled for public surfaces.
	 */
	public function is_localized_url_generation_enabled(): bool {
		return 'on' === $this->localized_urls_state();
	}
}
