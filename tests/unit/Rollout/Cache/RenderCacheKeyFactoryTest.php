<?php
/**
 * Render cache key factory unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Rollout\Cache;

use AIMultilingual\Rollout\Cache\RenderCacheKeyFactory;
use AIMultilingual\Rollout\RolloutConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Rollout\Cache\RenderCacheKeyFactory
 */
final class RenderCacheKeyFactoryTest extends TestCase {

	public function test_key_is_deterministic(): void {
		$factory = new RenderCacheKeyFactory();
		$config  = RolloutConfiguration::defaults()->with( array( 'policy_version' => 5 ) );
		$rows    = array(
			(object) array(
				'segment_key'      => 'b:uuid:content',
				'translation_hash' => 'abc',
			),
		);

		$a = $factory->build( 10, $factory->source_content_hash( '<p>x</p>' ), 2, $rows, $config );
		$b = $factory->build( 10, $factory->source_content_hash( '<p>x</p>' ), 2, $rows, $config );

		$this->assertSame( $a, $b );
		$this->assertStringStartsWith( 'aiml_render:', $a );
	}

	public function test_fingerprint_changes_when_translation_hash_changes(): void {
		$factory = new RenderCacheKeyFactory();
		$config  = RolloutConfiguration::defaults();

		$one = array(
			(object) array(
				'segment_key'      => 'a',
				'translation_hash' => '1',
			),
		);
		$two = array(
			(object) array(
				'segment_key'      => 'a',
				'translation_hash' => '2',
			),
		);

		$this->assertNotSame(
			$factory->translation_fingerprint( $one ),
			$factory->translation_fingerprint( $two )
		);
	}
}
