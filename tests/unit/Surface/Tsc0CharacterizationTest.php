<?php
/**
 * TSC.0 characterization — CURRENT honesty locks.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Surface;

use AIMultilingual\Integration\RankMath\RankMathIntegration;
use AIMultilingual\Settings;
use AIMultilingual\Surface\PostSurfaceAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Documents TSC.0 honesty targets (plan §16) without overclaiming Supported.
 */
final class Tsc0CharacterizationTest extends TestCase {

	public function test_fluent_stale_invalidation_remains_unsupported(): void {
		$root = dirname( __DIR__, 3 );
		$ff   = (string) file_get_contents( $root . '/src/Integration/FluentForms/FluentFormsIntegration.php' );
		$embed = (string) file_get_contents( $root . '/src/Integration/FluentForms/FluentFormsEmbedDetector.php' );

		$this->assertStringContainsString( 'UNSUPPORTED', $ff );
		$this->assertStringContainsString( 'no reverse-host map', $ff );
		$this->assertStringContainsString( 'no reverse form→host index', $embed );
		$this->assertStringNotContainsString( 'register_invalidation_events', $ff );
		$this->assertStringNotContainsString( 'mark_dirty', $ff );
		$this->assertStringNotContainsString( 'RequestLocalInvalidationCoordinator', $ff );
	}

	public function test_rank_math_keys_are_allowlisted_on_post_surface(): void {
		$keys = PostSurfaceAdapter::RANK_MATH_SEO_META_KEYS;

		$this->assertContains( RankMathIntegration::META_TITLE, $keys );
		$this->assertContains( RankMathIntegration::META_DESCRIPTION, $keys );
		$this->assertContains( RankMathIntegration::META_FACEBOOK_TITLE, $keys );
		$this->assertContains( RankMathIntegration::META_FACEBOOK_DESCRIPTION, $keys );
		$this->assertContains( RankMathIntegration::META_TWITTER_TITLE, $keys );
		$this->assertContains( RankMathIntegration::META_TWITTER_DESCRIPTION, $keys );
		$this->assertNotContains( RankMathIntegration::META_TWITTER_USE_FACEBOOK, $keys );
	}

	public function test_block_and_elementor_extraction_defaults_are_off(): void {
		$defaults = Settings::defaults();
		$this->assertFalse( $defaults['block_extraction_enabled'] );
		$this->assertFalse( $defaults['elementor_extraction_enabled'] );

		$settings = new Settings( array() );
		$adapter  = new PostSurfaceAdapter( $settings );

		$this->assertTrue( $adapter->feature_implemented( 'block_extraction' ) );
		$this->assertTrue( $adapter->feature_implemented( 'elementor_extraction' ) );
		$this->assertFalse( $adapter->feature_activated( 'block_extraction' ) );
		$this->assertFalse( $adapter->feature_activated( 'elementor_extraction' ) );
	}

	public function test_no_aiml_admitted_post_types_in_src(): void {
		$root     = dirname( __DIR__, 3 );
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/src' ) );

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
				continue;
			}
			$code = (string) file_get_contents( $file->getPathname() );
			$this->assertStringNotContainsString(
				'aiml_admitted_post_types',
				$code,
				$file->getPathname() . ' must not introduce a public CPT admission filter.'
			);
		}
	}
}
