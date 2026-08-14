<?php
/**
 * MSEO.0 deferred structural guards.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Database\Migrator;
use AIMultilingual\Database\Schema;
use AIMultilingual\Routing\EffectiveUrlService;
use AIMultilingual\Routing\PathHash;
use AIMultilingual\Settings;

/**
 * Proves MSEO.0 inert boundary — foundation only, no public URL activation.
 */
final class Mseo0DeferredGuardTest extends AimlTestCase {

	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	public function test_target_is_eight(): void {
		$this->assertSame( 8, Migrator::TARGET );
	}

	public function test_path_hash_uses_sha256(): void {
		$ref    = new \ReflectionClass( PathHash::class );
		$method = $ref->getMethod( 'from_canonical' );
		$source = (string) file_get_contents( $ref->getFileName() );
		$this->assertStringContainsString( "hash( 'sha256'", $source );
		$this->assertStringNotContainsString( 'sha1(', $source );
	}

	public function test_no_slug_route_activation_job_class(): void {
		$this->assertFalse( class_exists( 'AIMultilingual\\Routing\\SlugRouteActivationJob' ) );
	}

	public function test_effective_url_service_not_wired_to_home_url(): void {
		$plugin = (string) file_get_contents( $this->root() . '/src/Plugin.php' );
		$this->assertStringNotContainsString( 'EffectiveUrlService', $plugin );
	}

	public function test_router_has_no_mseo_inbound_substitution(): void {
		$router = (string) file_get_contents( $this->root() . '/src/Routing/Router.php' );
		$this->assertStringNotContainsString( 'SlugRouteRepository', $router );
		$this->assertStringNotContainsString( 'PathCanonicalizer', $router );
		$this->assertStringNotContainsString( 'localized_path', $router );
	}

	public function test_no_provider_calls_in_routing_namespace(): void {
		$dir      = $this->root() . '/src/Routing';
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir ) );
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
				continue;
			}
			$code = (string) file_get_contents( $file->getPathname() );
			$this->assertStringNotContainsString( 'Provider', $code, $file->getPathname() );
			$this->assertStringNotContainsString( 'openai', strtolower( $code ), $file->getPathname() );
		}
	}

	public function test_no_localized_url_settings_page_control(): void {
		$page = (string) file_get_contents( $this->root() . '/src/Admin/SettingsPage.php' );
		$this->assertStringNotContainsString( 'localized_urls_state', $page );
		$this->assertStringNotContainsString( 'localized url', strtolower( $page ) );
	}

	public function test_mseo_tables_exist_but_routing_remains_deferred(): void {
		global $wpdb;

		$this->assertSame(
			Schema::slug_routes(),
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::slug_routes() ) )
		);

		$router_source = (string) file_get_contents( $this->root() . '/src/Routing/Router.php' );
		$this->assertStringNotContainsString( 'EffectiveUrlService', $router_source );
		$this->assertStringNotContainsString( 'find_by_localized_path', $router_source );
	}

	public function test_effective_url_service_constructor_is_settings_only(): void {
		$ref    = new \ReflectionClass( EffectiveUrlService::class );
		$params = $ref->getConstructor()->getParameters();
		$this->assertCount( 1, $params );
		$this->assertSame( Settings::class, $params[0]->getType()->getName() );
	}
}
