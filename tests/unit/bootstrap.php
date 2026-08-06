<?php
/**
 * Unit test bootstrap: composer autoloader only, WordPress is not loaded.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! class_exists( 'WP_Error', false ) ) {
	/**
	 * Minimal WP_Error stub for unit tests without WordPress bootstrap.
	 */
	class WP_Error {
		/** @var string */
		private $code;

		/** @var string */
		private $message;

		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code(): string {
			return $this->code;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value to test.
	 */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data    Data to encode.
	 * @param int   $options json_encode options.
	 * @param int   $depth   Max depth.
	 * @return string|false
	 */
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}
