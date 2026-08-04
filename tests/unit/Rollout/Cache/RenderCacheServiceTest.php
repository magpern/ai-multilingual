<?php
/**
 * Render cache service unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Rollout\Cache;

use AIMultilingual\Rollout\Cache\RenderCacheService;
use AIMultilingual\Rollout\RolloutConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Rollout\Cache\RenderCacheService
 */
final class RenderCacheServiceTest extends TestCase {

	public function test_disabled_cache_returns_null_without_backend(): void {
		$service = new RenderCacheService();
		$config  = RolloutConfiguration::defaults()->with( array( 'render_cache_enabled' => false ) );

		$this->assertNull( $service->get( 'aiml_render:abc', 2, $config ) );
		$this->assertFalse( $service->set( 'aiml_render:abc', 2, '<p>x</p>', $config ) );
	}

	public function test_set_rejects_empty_html(): void {
		$service = new RenderCacheService();
		$config  = RolloutConfiguration::defaults()->with( array( 'render_cache_enabled' => true ) );

		$this->assertFalse( $service->set( 'aiml_render:abc', 2, '', $config ) );
	}
}
