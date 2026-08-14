<?php
/**
 * Unit tests for A.SEOe Rank Math sitemap overlays.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Cache\Cache;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\RankMath\RankMathIntegration;
use AIMultilingual\Integration\RankMath\RankMathSitemapOverlay;
use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Language\Languages;
use AIMultilingual\Routing\EffectiveUrlService;
use AIMultilingual\Routing\ObjectLanguagePublicEligibility;
use AIMultilingual\Routing\PathCanonicalizer;
use AIMultilingual\Routing\RoutingCapabilityRegistry;
use AIMultilingual\Routing\SlugRouteRepository;
use AIMultilingual\Seo\LanguageRelationshipService;
use AIMultilingual\Settings;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Integration\RankMath\RankMathSitemapOverlay
 * @covers \AIMultilingual\Integration\RankMath\RankMathIntegration::register_sitemap_hooks
 */
final class RankMathSitemapOverlayTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_all_filters( RankMathSitemapOverlay::HOOK_URL );
		remove_all_filters( RankMathSitemapOverlay::HOOK_ENTRY );
		remove_all_filters( RankMathSitemapOverlay::HOOK_INCLUDE_NOINDEX );
		remove_all_filters( 'rank_math/sitemap/page_urlset' );
		remove_all_filters( 'rank_math/sitemap/product_urlset' );
		remove_all_filters( RankMathSitemapOverlay::HOOK_PROVIDERS );
	}

	public function test_inactive_rank_math_skips_sitemap_hooks(): void {
		$integration = $this->make_integration();
		$integration->configure( true, false, '1.0.275', false, true );
		$integration->register_sitemap_hooks();

		$this->assertFalse( has_filter( RankMathSitemapOverlay::HOOK_URL ) );
		$this->assertFalse( has_filter( 'rank_math/sitemap/page_urlset' ) );
	}

	public function test_compatible_registers_official_sitemap_hooks_not_providers(): void {
		$integration = $this->make_integration();
		$integration->configure( true, true, '1.0.275', false, true );
		$integration->register_sitemap_hooks();

		$this->assertTrue( has_filter( RankMathSitemapOverlay::HOOK_URL ) );
		$this->assertTrue( has_filter( RankMathSitemapOverlay::HOOK_ENTRY ) );
		$this->assertTrue( has_filter( RankMathSitemapOverlay::HOOK_INCLUDE_NOINDEX ) );
		$this->assertTrue( has_filter( 'rank_math/sitemap/page_urlset' ) );
		$this->assertTrue( has_filter( 'rank_math/sitemap/product_urlset' ) );
		$this->assertFalse( has_filter( RankMathSitemapOverlay::HOOK_PROVIDERS ) );
		$this->assertNotNull( $integration->sitemap_overlay() );
		$this->assertTrue( $integration->sitemap_overlay()->is_registered() );
	}

	public function test_register_sitemap_hooks_is_idempotent(): void {
		$integration = $this->make_integration();
		$integration->configure( true, true, '1.0.275', false, true );
		$integration->register_sitemap_hooks();
		$integration->register_sitemap_hooks();

		$hooks = $GLOBALS['aiml_unit_filters'][ RankMathSitemapOverlay::HOOK_URL ] ?? array();
		$count = 0;
		foreach ( $hooks as $callbacks ) {
			$count += count( $callbacks );
		}
		$this->assertSame( 1, $count );
	}

	public function test_without_relationships_skips_sitemap_hooks(): void {
		$integration = new RankMathIntegration(
			new PluginIdentity(),
			new Store( new Cache() ),
			new LanguageContext(),
			null,
			true,
			true,
			'1.0.275',
			false,
			true
		);
		$integration->register_sitemap_hooks();
		$this->assertNull( $integration->sitemap_overlay() );
		$this->assertFalse( has_filter( RankMathSitemapOverlay::HOOK_URL ) );
	}

	public function test_no_sitemap_discovery_contract_class(): void {
		$this->assertFalse( class_exists( 'AIMultilingual\\Seo\\SitemapDiscovery', false ) );
		$this->assertFalse( class_exists( 'AIMultilingual\\Seo\\SitemapEmitter', false ) );
	}

	private function make_integration(): RankMathIntegration {
		$settings     = new Settings();
		$cache        = new Cache();
		$languages    = new Languages( $cache );
		$context      = new LanguageContext();
		$store        = new Store( $cache );
		$paths        = new PathCanonicalizer();
		$routes       = new SlugRouteRepository();
		$capabilities = new RoutingCapabilityRegistry();
		$effective    = new EffectiveUrlService( $settings, $routes, $capabilities, $paths, $languages );
		$eligibility  = new ObjectLanguagePublicEligibility( $store, $languages, $capabilities, $settings, $routes );

		return new RankMathIntegration(
			new PluginIdentity(),
			$store,
			$context,
			new LanguageRelationshipService( $languages, $context, $effective, $eligibility, $settings ),
			true,
			true,
			'1.0.275',
			false,
			true
		);
	}
}
