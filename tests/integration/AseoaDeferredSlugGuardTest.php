<?php
/**
 * A.SEOa Deferred admission guardrails (SA1–SA6 / SA8 / SA9).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Database\Migrator;
use AIMultilingual\Database\Schema;
use AIMultilingual\Routing\Router;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use ReflectionClass;

/**
 * Proves A.SEOa did not accidentally ship Deferred leaf-slug / history scope.
 */
final class AseoaDeferredSlugGuardTest extends AimlTestCase {

	public function test_target_remains_six(): void {
		$this->assertSame( 6, Migrator::TARGET );
	}

	public function test_store_has_no_source_term_constant(): void {
		$ref = new ReflectionClass( Store::class );
		$this->assertFalse( $ref->hasConstant( 'SOURCE_TERM' ) );
		$this->assertSame( 'post', Store::SOURCE_POST );
	}

	public function test_store_has_no_reverse_translated_text_lookup_api(): void {
		$methods = get_class_methods( Store::class );
		foreach ( $methods as $method ) {
			$this->assertDoesNotMatchRegularExpression(
				'/translated_text|by_slug|reverse|lookup_slug|find_by_value/i',
				$method,
				'Store must not expose reverse slug lookup without ADR'
			);
		}
	}

	public function test_schema_has_no_slugs_or_redirect_history_table(): void {
		$tables = Schema::all_tables();
		foreach ( $tables as $table ) {
			$this->assertDoesNotMatchRegularExpression(
				'/slug|redirect_history|url_history/i',
				(string) $table,
				'No URL-history / slug registry table under TARGET 6'
			);
		}
	}

	public function test_extractor_does_not_emit_post_name(): void {
		$post     = $this->create_page( 'About Us' );
		$segments = $this->extractor->extract( $post );
		$this->assertArrayNotHasKey( 'post_name', $segments );
		foreach ( array_keys( $segments ) as $key ) {
			$this->assertStringNotContainsString( 'post_name', (string) $key );
		}
	}

	public function test_router_register_adds_no_rewrite_rules_and_no_add_rewrite_hooks(): void {
		$router = new Router( $this->languages, $this->resolver, $this->context );
		$router->register();

		global $wp_filter;
		$plugins = $wp_filter['plugins_loaded'] ?? null;
		$this->assertNotNull( $plugins );

		// No AIML rewrite registration hooks for translated bases.
		foreach ( array( 'init', 'generate_rewrite_rules' ) as $hook ) {
			if ( ! isset( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $prio => $callbacks ) {
				foreach ( $callbacks as $cb ) {
					$fn = $cb['function'] ?? null;
					if ( is_array( $fn ) && is_object( $fn[0] ) && $fn[0] instanceof Router ) {
						$this->fail( 'Router must not attach rewrite generation callbacks' );
					}
				}
			}
		}

		$this->assertFalse( has_action( 'generate_rewrite_rules', array( $router, 'register' ) ) );
	}

	public function test_no_aiml_slug_uniqueness_or_history_classes(): void {
		$paths = array(
			'AIMultilingual\\Routing\\SlugResolver',
			'AIMultilingual\\Routing\\SlugHistory',
			'AIMultilingual\\Routing\\RedirectRegistry',
			'AIMultilingual\\Translation\\SlugStore',
		);
		foreach ( $paths as $class ) {
			$this->assertFalse( class_exists( $class ), $class . ' must not exist in A.SEOa' );
		}
	}

	public function test_format_slug_constant_does_not_imply_end_to_end_support(): void {
		$this->assertSame( 'slug', Store::FORMAT_SLUG );
		// No Store rows with format slug for a fresh page.
		$post = $this->create_page();
		$sv   = $this->add_language();
		$map  = $this->store->load_object( Store::SOURCE_POST, (int) $post->ID, (int) $sv->language_id );
		foreach ( $map as $row ) {
			$this->assertNotSame( Store::FORMAT_SLUG, (string) ( $row->text_format ?? '' ) );
		}
	}
}
