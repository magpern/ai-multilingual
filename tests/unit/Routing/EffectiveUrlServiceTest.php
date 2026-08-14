<?php
/**
 * EffectiveUrlService unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Routing;

use AIMultilingual\Cache\Cache;
use AIMultilingual\Language\Languages;
use AIMultilingual\Routing\EffectiveUrlService;
use AIMultilingual\Routing\PathCanonicalizer;
use AIMultilingual\Routing\RoutingCapabilityRegistry;
use AIMultilingual\Routing\SlugRouteRepository;
use AIMultilingual\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Effective URL authority (MSEO.2).
 */
final class EffectiveUrlServiceTest extends TestCase {

	public function test_state_off_returns_source_path_unchanged(): void {
		$service = $this->make_service( new Settings( array( 'localized_urls_state' => 'off' ) ) );

		$this->assertSame( '/about-us', $service->unprefixed_effective_path( '/about-us', 2 ) );
		$this->assertSame( '/shop/product', $service->unprefixed_effective_path( '/shop/product', 99 ) );
	}

	public function test_non_on_states_do_not_localize(): void {
		foreach ( array( 'off', 'activating', 'failed' ) as $state ) {
			$service = $this->make_service( new Settings( array( 'localized_urls_state' => $state ) ) );
			$this->assertSame( '/page', $service->unprefixed_effective_path( '/page', 1 ) );
		}
	}

	public function test_constructor_includes_route_dependencies(): void {
		$ref = new \ReflectionClass( EffectiveUrlService::class );
		$this->assertGreaterThanOrEqual( 4, count( $ref->getConstructor()->getParameters() ) );
	}

	private function make_service( Settings $settings ): EffectiveUrlService {
		return new EffectiveUrlService(
			$settings,
			new SlugRouteRepository(),
			new RoutingCapabilityRegistry(),
			new PathCanonicalizer(),
			new Languages( new Cache() )
		);
	}
}
