<?php
/**
 * Plugin settings.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual;

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
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'schema_version'            => self::SCHEMA_VERSION,

			/*
			 * Data retention on uninstall. Default off: translation work is
			 * expensive to recreate and deleting it must be a deliberate act
			 * (invariant I5).
			 */
			'remove_data_on_uninstall'  => false,

			// Language switcher presentation.
			'switcher_show_native_name' => true,
			'switcher_hide_current'     => false,
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

		foreach ( array( 'remove_data_on_uninstall', 'switcher_show_native_name', 'switcher_hide_current' ) as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$clean[ $key ] = self::to_bool( $raw[ $key ] );
			}
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
}
