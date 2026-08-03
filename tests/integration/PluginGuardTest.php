<?php
/**
 * Structural invariants.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Plugin;

/**
 * Asserts the architecture, not the behaviour.
 *
 * These read the source and the registered hook table to pin down boundaries
 * that are easy to erode one convenient call at a time: the plugin never writes
 * to canonical content, direct SQL stays in the two places allowed to have it,
 * and Milestone 1 does not quietly grow a REST API or a cookie. A behavioural
 * test would only catch the consequence; these catch the cause.
 */
final class PluginGuardTest extends AimlTestCase {

	/**
	 * Absolute path to the plugin root.
	 */
	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Every PHP file under src/, keyed by repo-relative path.
	 *
	 * @return array<string, string>
	 */
	private function sources(): array {
		$files    = array();
		$root     = $this->root();
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/src' ) );

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
				continue;
			}

			$path = str_replace( $root . '/', '', $file->getPathname() );

			$files[ $path ] = (string) file_get_contents( $file->getPathname() );
		}

		$this->assertNotEmpty( $files, 'Expected to find source files to inspect.' );

		return $files;
	}

	/**
	 * Asserts no source file contains any of the given fragments.
	 *
	 * @param string[] $needles Forbidden fragments.
	 * @param string   $why     Why they are forbidden.
	 * @param string[] $allowed Repo-relative paths exempted.
	 */
	private function assert_absent( array $needles, string $why, array $allowed = array() ): void {
		foreach ( $this->sources() as $path => $code ) {
			if ( in_array( $path, $allowed, true ) ) {
				continue;
			}

			foreach ( $needles as $needle ) {
				$this->assertStringNotContainsString(
					$needle,
					$code,
					sprintf( '%s found "%s". %s', $path, $needle, $why )
				);
			}
		}
	}

	// -- The overlay model --

	public function test_no_canonical_content_is_ever_created_or_written(): void {
		$this->assert_absent(
			array(
				'wp_insert_post(',
				'wp_update_post(',
				'wp_insert_term(',
				'wp_update_term(',
				'wp_delete_post(',
				'wp_insert_attachment(',
			),
			'Translations are overlays; there is exactly one canonical object and the plugin never writes it (I1-I3).',
			array( 'src/Block/BlockIdentityMigration.php' )
		);
	}

	public function test_no_direct_writes_to_core_content_tables(): void {
		foreach ( $this->sources() as $path => $code ) {
			foreach ( array( '$wpdb->posts', '$wpdb->postmeta', '$wpdb->terms', '$wpdb->termmeta', '$wpdb->term_taxonomy' ) as $table ) {
				$this->assertStringNotContainsString(
					$table,
					$code,
					"{$path} touches {$table}. Canonical content is read through the WordPress API and never written."
				);
			}
		}
	}

	public function test_no_woocommerce_object_is_mutated(): void {
		$this->assert_absent(
			array( '->set_price(', '->set_stock_quantity(', '->set_sku(', '->set_name(', '->set_description(' ),
			'WooCommerce owns product data; translation is a view-context overlay only (I2).'
		);
	}

	// -- Database boundary --

	public function test_direct_sql_is_confined(): void {
		$allowed = array(
			'src/Database/Schema.php',
			'src/Database/Migrator.php',
			'src/Language/Languages.php',
			'src/Translation/Store.php',
		);

		foreach ( $this->sources() as $path => $code ) {
			if ( in_array( $path, $allowed, true ) ) {
				continue;
			}

			$this->assertStringNotContainsString(
				'$wpdb',
				$code,
				"{$path} uses \$wpdb. Direct SQL belongs in src/Database or a store class (I9)."
			);
		}
	}

	public function test_table_names_are_always_prefixed_at_runtime(): void {
		foreach ( $this->sources() as $path => $code ) {
			$this->assertDoesNotMatchRegularExpression(
				"/'wp_aiml_/",
				$code,
				"{$path} hardcodes a wp_ table prefix; names must come from \$wpdb->prefix (I9)."
			);
		}
	}

	public function test_every_interpolated_query_is_prepared_or_built_from_schema(): void {
		$store = (string) file_get_contents( $this->root() . '/src/Translation/Store.php' );

		// Every read/write of user-supplied values goes through prepare(); the
		// only interpolation is the table name, which comes from Schema.
		$this->assertSame(
			substr_count( $store, '$wpdb->get_results(' ) + substr_count( $store, '$wpdb->get_var(' ),
			substr_count( $store, '$wpdb->prepare(' ) - substr_count( $store, '$wpdb->query( $wpdb->prepare(' ),
			'Every read in Store must be a prepared statement.'
		);
	}

	// -- Milestone 1 scope --

	public function test_no_rest_routes_are_registered(): void {
		$this->assert_absent(
			array( 'register_rest_route', 'WP_REST_Controller', 'rest_api_init' ),
			'REST is confined to the translator workspace controller under aiml/v1.',
			array( 'src/Rest/WorkspaceController.php' )
		);
	}

	public function test_no_cookie_is_set(): void {
		$this->assert_absent(
			array( 'setcookie(', 'wp_set_auth_cookie(', '$_COOKIE' ),
			'The URL is the only language authority in Milestone 1; a Set-Cookie header would hurt cacheability for nothing.'
		);
	}

	public function test_no_rewrite_rules_are_registered_or_flushed(): void {
		$this->assert_absent(
			array( 'add_rewrite_rule(', 'add_rewrite_tag(', 'flush_rewrite_rules(' ),
			'Routing strips the prefix before WordPress parses the request, so no rewrite state exists to manage (ADR-0002).'
		);
	}

	// -- Independence and safety --

	public function test_no_coupling_to_another_translation_plugin(): void {
		$this->assert_absent(
			array( 'trp_', 'TranslatePress', 'icl_', 'polylang', 'Polylang' ),
			'This plugin owns its own data and never reads another translation plugin.'
		);
	}

	public function test_no_broad_exception_is_swallowed(): void {
		foreach ( $this->sources() as $path => $code ) {
			$this->assertDoesNotMatchRegularExpression(
				'/catch\s*\(\s*\\\\?(Throwable|Exception)\s/',
				$code,
				"{$path} swallows a broad exception; failures must stay visible."
			);
		}
	}

	public function test_object_cache_access_goes_through_the_wrapper(): void {
		foreach ( $this->sources() as $path => $code ) {
			if ( 'src/Cache/Cache.php' === $path ) {
				continue;
			}

			$this->assertStringNotContainsString(
				'wp_cache_',
				$code,
				"{$path} calls the object cache directly. All access goes through Cache so keys always carry the language (I10)."
			);
		}
	}

	public function test_cache_keys_embed_the_language(): void {
		$cache = (string) file_get_contents( $this->root() . '/src/Cache/Cache.php' );

		$this->assertMatchesRegularExpression(
			'/private function key\([^)]*\): string \{.*?\$language_id.*?\}/s',
			$cache,
			'Cache::key() must include the language, or one language will serve another its translations.'
		);
	}

	// -- Uninstall --

	public function test_uninstall_never_removes_data_unconditionally(): void {
		$uninstall = (string) file_get_contents( $this->root() . '/uninstall.php' );

		$this->assertStringContainsString(
			'remove_data_on_uninstall',
			$uninstall,
			'Uninstall must consult the retention setting.'
		);

		$guard = strpos( $uninstall, "empty( \$aiml_settings['remove_data_on_uninstall'] )" );

		$this->assertNotFalse( $guard, 'Expected the early-return retention guard.' );

		foreach ( array( 'DROP TABLE', 'delete_option(', 'remove_cap(' ) as $destructive ) {
			$position = strpos( $uninstall, $destructive );

			$this->assertNotFalse( $position, "Expected uninstall to be able to {$destructive}." );
			$this->assertGreaterThan(
				$guard,
				$position,
				"{$destructive} appears before the retention guard; retention is all-or-nothing (I5)."
			);
		}
	}

	// -- Boot --

	public function test_boot_is_idempotent(): void {
		$before = count( $GLOBALS['wp_filter']['the_title']->callbacks[10] ?? array() );

		Plugin::instance()->init();
		Plugin::instance()->init();

		$after = count( $GLOBALS['wp_filter']['the_title']->callbacks[10] ?? array() );

		$this->assertSame( $before, $after, 'Re-initialising must not register a second set of filters.' );
	}

	public function test_instance_is_shared(): void {
		$this->assertSame( Plugin::instance(), Plugin::instance() );
	}

	public function test_the_content_overlay_runs_before_core_block_rendering(): void {
		$reflection = new \ReflectionMethod( \AIMultilingual\Translation\Renderer::class, 'register' );
		$source     = (string) file_get_contents( (string) $reflection->getFileName() );

		$this->assertStringContainsString(
			"add_filter( 'the_content', array( \$this, 'filter_content' ), 1 )",
			$source,
			'Core attaches block hooks at 8 and do_blocks at 9; the overlay must run before both.'
		);
	}
}
