<?php
/**
 * EffectiveUrlService unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Routing;

use AIMultilingual\Routing\EffectiveUrlService;
use AIMultilingual\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Inert passthrough (R7).
 */
final class EffectiveUrlServiceTest extends TestCase {

	public function test_state_off_returns_source_path_unchanged(): void {
		$settings = new Settings(
			array(
				'localized_urls_state' => 'off',
			)
		);
		$service  = new EffectiveUrlService( $settings );

		$this->assertSame( '/about-us', $service->unprefixed_effective_path( '/about-us', 2 ) );
		$this->assertSame( '/shop/product', $service->unprefixed_effective_path( '/shop/product', 99 ) );
	}

	public function test_non_on_states_do_not_localize(): void {
		foreach ( array( 'off', 'activating', 'failed' ) as $state ) {
			$settings = new Settings( array( 'localized_urls_state' => $state ) );
			$service  = new EffectiveUrlService( $settings );
			$this->assertSame( '/page', $service->unprefixed_effective_path( '/page', 1 ) );
		}
	}

	public function test_production_constructor_shape_is_settings_only(): void {
		$ref = new \ReflectionClass( EffectiveUrlService::class );
		$this->assertCount( 1, $ref->getConstructor()->getParameters() );
		$this->assertSame( Settings::class, $ref->getConstructor()->getParameters()[0]->getType()->getName() );
	}
}
