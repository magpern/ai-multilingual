<?php
/**
 * PostSurfaceAdapter unit tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Surface;

use AIMultilingual\Cache\Cache;
use AIMultilingual\Integration\RankMath\RankMathIntegration;
use AIMultilingual\Settings;
use AIMultilingual\Surface\PostSurfaceAdapter;
use AIMultilingual\Surface\RequestLocalInvalidationCoordinator;
use AIMultilingual\Surface\SurfaceCapabilityNames;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * @covers \AIMultilingual\Surface\PostSurfaceAdapter
 */
final class PostSurfaceAdapterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['aiml_unit_posts']            = array();
		$GLOBALS['aiml_unit_user_can']         = array();
		$GLOBALS['aiml_unit_current_user_can'] = array();
	}

	protected function tearDown(): void {
		$GLOBALS['aiml_unit_posts']            = array();
		$GLOBALS['aiml_unit_user_can']         = array();
		$GLOBALS['aiml_unit_current_user_can'] = array();
		parent::tearDown();
	}

	public function test_source_type_and_capability_declarations(): void {
		$adapter = new PostSurfaceAdapter( new Settings( array() ) );

		$this->assertSame( Store::SOURCE_POST, $adapter->source_type() );
		foreach ( SurfaceCapabilityNames::all() as $capability ) {
			$this->assertTrue( $adapter->supports( $capability ) );
		}
		$this->assertFalse( $adapter->supports( 'publish_policy' ) );
	}

	public function test_feature_implemented_vs_activated_defaults_off(): void {
		$adapter = new PostSurfaceAdapter( new Settings( array() ) );

		$this->assertTrue( $adapter->feature_implemented( 'block_extraction' ) );
		$this->assertTrue( $adapter->feature_implemented( 'elementor_extraction' ) );
		$this->assertTrue( $adapter->feature_implemented( 'rank_math_seo' ) );
		$this->assertTrue( $adapter->feature_implemented( 'fluentforms' ) );

		$this->assertFalse( $adapter->feature_activated( 'block_extraction' ) );
		$this->assertFalse( $adapter->feature_activated( 'elementor_extraction' ) );
		$this->assertFalse( $adapter->feature_activated( 'rank_math_seo' ) );
	}

	public function test_feature_activated_respects_settings_opt_in(): void {
		$adapter = new PostSurfaceAdapter(
			new Settings(
				array(
					'block_attr_registration_enabled' => true,
					'block_uuid_injection_enabled'    => true,
					'block_extraction_enabled'        => true,
					'elementor_extraction_enabled'    => true,
				)
			)
		);

		$this->assertTrue( $adapter->feature_activated( 'block_extraction' ) );
		$this->assertTrue( $adapter->feature_activated( 'elementor_extraction' ) );
	}

	public function test_rank_math_seo_meta_keys_are_allowlisted(): void {
		$this->assertSame(
			array(
				RankMathIntegration::META_TITLE,
				RankMathIntegration::META_DESCRIPTION,
				RankMathIntegration::META_FACEBOOK_TITLE,
				RankMathIntegration::META_FACEBOOK_DESCRIPTION,
				RankMathIntegration::META_TWITTER_TITLE,
				RankMathIntegration::META_TWITTER_DESCRIPTION,
			),
			PostSurfaceAdapter::RANK_MATH_SEO_META_KEYS
		);
	}

	public function test_exists_subtype_and_visibility_facts(): void {
		$post                           = new WP_Post();
		$post->ID                       = 12;
		$post->post_type                = 'page';
		$post->post_status              = 'publish';
		$GLOBALS['aiml_unit_posts'][12] = $post;

		$adapter = new PostSurfaceAdapter();

		$this->assertTrue( $adapter->exists( 12 ) );
		$this->assertFalse( $adapter->exists( 99 ) );
		$this->assertSame( 'page', $adapter->source_subtype( 12 ) );
		$this->assertTrue( $adapter->is_visitor_public( 12 ) );

		$post->post_status = 'draft';
		$this->assertFalse( $adapter->is_visitor_public( 12 ) );
	}

	public function test_user_can_edit_source_uses_wp_caps(): void {
		$GLOBALS['aiml_unit_user_can'][5]['edit_post'] = true;
		$adapter                                       = new PostSurfaceAdapter();

		$this->assertTrue( $adapter->user_can_edit_source( 5, 12 ) );
		$this->assertFalse( $adapter->user_can_edit_source( 6, 12 ) );
	}

	public function test_is_admitted_post_type_matches_workspace_or_overlay(): void {
		$adapter = new PostSurfaceAdapter();

		$this->assertTrue( $adapter->is_admitted_post_type( 'post' ) );
		$this->assertTrue( $adapter->is_admitted_post_type( 'nav_menu_item' ) );
		$this->assertFalse( $adapter->is_admitted_post_type( 'attachment' ) );
	}

	public function test_register_invalidation_events_accepts_coordinator(): void {
		$coordinator = new RequestLocalInvalidationCoordinator(
			new Store( new Cache() ),
			new Extractor()
		);
		$adapter     = new PostSurfaceAdapter( new Settings( array() ) );

		$adapter->register_invalidation_events( $coordinator );
		$this->assertSame( 0, $coordinator->dirty_count() );
	}
}
