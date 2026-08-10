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

		/** @var mixed */
		private $data;

		public function __construct( string $code = '', string $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		/**
		 * @return mixed
		 */
		public function get_error_data() {
			return $this->data;
		}

		/**
		 * @param mixed $data Error data.
		 */
		public function add_data( $data ): void {
			$this->data = $data;
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

if ( ! class_exists( 'WP_Post', false ) ) {
	/**
	 * Minimal WP_Post stub for unit tests.
	 */
	class WP_Post {
		/** @var int */
		public $ID = 0;

		/** @var string */
		public $post_type = 'post';

		/** @var string */
		public $post_content = '';

		/** @var string */
		public $post_status = 'publish';
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

if ( ! function_exists( 'wp_rand' ) ) {
	/**
	 * Deterministic jitter stub for unit tests.
	 *
	 * @param int $min Minimum.
	 * @param int $max Maximum.
	 */
	function wp_rand( $min = 0, $max = 0 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature mirrors wp_rand().
		return (int) $min;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * @param string $hook Hook name.
	 * @param mixed  ...$args Hook arguments.
	 */
	function do_action( $hook, ...$args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * No-op WordPress hook registration for unit tests.
	 *
	 * Must not invoke the callback: real WordPress stores it for later
	 * `do_action` delivery. Calling it here breaks constructors that
	 * register methods with required arguments (e.g. BlockMetricsAggregator).
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Accepted args.
	 * @return true
	 */
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return true;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * @param string $text Text.
	 */
	function wp_strip_all_tags( $text ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Unit stub of wp_strip_all_tags itself.
		return trim( strip_tags( (string) $text ) );
	}
}

if ( ! isset( $GLOBALS['aiml_unit_filters'] ) ) {
	$GLOBALS['aiml_unit_filters'] = array();
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * @param string   $hook     Hook.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $accepted Accepted args.
	 * @return true
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$GLOBALS['aiml_unit_filters'][ (string) $hook ][ (int) $priority ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Extra args.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		$hook = (string) $hook;
		if ( empty( $GLOBALS['aiml_unit_filters'][ $hook ] ) ) {
			return $value;
		}
		ksort( $GLOBALS['aiml_unit_filters'][ $hook ] );
		foreach ( $GLOBALS['aiml_unit_filters'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$value = $callback( $value, ...$args );
			}
		}
		return $value;
	}
}

if ( ! function_exists( 'remove_all_filters' ) ) {
	/**
	 * @param string $hook Hook.
	 */
	function remove_all_filters( $hook ): bool {
		unset( $GLOBALS['aiml_unit_filters'][ (string) $hook ] );
		return true;
	}
}

if ( ! function_exists( 'has_filter' ) ) {
	/**
	 * @param string         $hook              Hook.
	 * @param callable|false $function_to_check Optional callback.
	 * @return bool|int
	 */
	function has_filter( $hook, $function_to_check = false ) {
		$hook = (string) $hook;
		if ( empty( $GLOBALS['aiml_unit_filters'][ $hook ] ) ) {
			return false;
		}
		if ( false === $function_to_check ) {
			return true;
		}
		foreach ( $GLOBALS['aiml_unit_filters'][ $hook ] as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( $callback === $function_to_check ) {
					return (int) $priority;
				}
			}
		}
		return false;
	}
}
