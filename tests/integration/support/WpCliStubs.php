<?php
/**
 * Minimal WP-CLI test doubles for integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

// phpcs:ignoreFile -- Test-only WP-CLI shims; not production code.

namespace AIMultilingual\Tests\Integration\Support {

	/**
	 * Captures WP-CLI output during tests.
	 */
	final class WpCliTestDouble {

		/** @var list<string> */
		public static array $logs = array();

		public static ?string $error = null;

		public static ?string $printed = null;

		public static function reset(): void {
			self::$logs    = array();
			self::$error   = null;
			self::$printed = null;
		}

		public static function log( string $message ): void {
			self::$logs[] = $message;
		}

		/**
		 * @throws \RuntimeException Always thrown after recording the error.
		 */
		public static function error( string $message ): void {
			self::$error = $message;
			throw new \RuntimeException( esc_html( $message ) );
		}

		/**
		 * @param mixed                $value   Value to print.
		 * @param array<string, mixed> $options Print options.
		 */
		public static function print_value( $value, array $options = array() ): void {
			unset( $options );

			if ( is_array( $value ) ) {
				self::$printed = (string) wp_json_encode( $value );
				self::$logs[]  = self::$printed;

				return;
			}

			self::$printed = (string) $value;
			self::$logs[]  = self::$printed;
		}

		public static function warning( string $message ): void {
			self::$logs[] = 'warning: ' . $message;
		}
	}
}

namespace WP_CLI\Utils {

	use AIMultilingual\Tests\Integration\Support\WpCliTestDouble;

	/**
	 * @param string                      $format Output format.
	 * @param list<array<string, string>> $items  Rows.
	 * @param array                       $fields Field names.
	 */
	function format_items( string $format, array $items, array $fields ): void {
		unset( $format, $fields );

		foreach ( $items as $item ) {
			WpCliTestDouble::log(
				sprintf( '%s: %s', $item['metric'], $item['value'] )
			);
		}
	}
}

namespace {

	use AIMultilingual\Tests\Integration\Support\WpCliTestDouble;

	if ( ! class_exists( 'WP_CLI', false ) ) {
		/**
		 * WP-CLI stub for integration tests.
		 */
		final class WP_CLI {

			public static function log( string $message ): void {
				WpCliTestDouble::log( $message );
			}

			public static function error( string $message ): void {
				WpCliTestDouble::error( $message );
			}

			/**
			 * @param mixed                $value   Value to print.
			 * @param array<string, mixed> $options Print options.
			 */
			public static function print_value( $value, array $options = array() ): void {
				WpCliTestDouble::print_value( $value, $options );
			}

			public static function warning( string $message ): void {
				WpCliTestDouble::warning( $message );
			}
		}
	}
}
