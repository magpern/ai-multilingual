<?php
/**
 * Strategy F UUID persistence integration.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\BlockIdentityLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SavePipeline;
use AIMultilingual\Block\UuidInjector;
use AIMultilingual\Block\UuidValidator;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Extractor;

/**
 * End-to-end UUID persistence through the save pipeline.
 */
final class UuidPersistenceTest extends AimlTestCase {

	protected function setUp(): void {
		parent::setUp();

		wp_set_current_user( 1 );
	}

	protected function tearDown(): void {
		SavePipeline::reset_guard_for_tests();

		parent::tearDown();
	}

	public function test_enabled_settings_expose_injection_flag(): void {
		$settings = $this->enabled_settings();

		$this->assertTrue( $settings->block_attr_registration_enabled() );
		$this->assertTrue( $settings->block_uuid_injection_enabled() );
		$this->assertTrue( has_blocks( '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->' ) );
	}

	public function test_should_inject_when_flags_and_content_are_valid(): void {
		$pipeline = $this->enabled_pipeline();
		$post_id  = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);

		$data    = array(
			'post_content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
			'post_type'    => 'page',
		);
		$postarr = array(
			'ID'        => $post_id,
			'post_type' => 'page',
		);

		$post = get_post( $post_id );
		$this->assertInstanceOf( \WP_Post::class, $post );
		$this->assertNotSame( Extractor::BODY_ELEMENTOR, $this->extractor->body_status( $post ) );
		$this->assertTrue( current_user_can( 'edit_post', $post_id ) );
		$this->assertFalse( wp_is_post_revision( $post_id ) );
		$this->assertFalse( wp_is_post_autosave( $post_id ) );

		$this->assertTrue( $pipeline->should_inject( $data, $postarr ) );
	}

	public function test_save_pipeline_injects_uuid_on_eligible_block(): void {
		$pipeline = $this->enabled_pipeline();
		$post_id  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);

		$content = '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->';
		$data    = array(
			'post_content' => $content,
			'post_type'    => 'page',
		);

		$filtered = $pipeline->apply_with_guard(
			$data,
			array(
				'ID'        => $post_id,
				'post_type' => 'page',
			)
		);

		$this->assertNotSame( $content, $filtered['post_content'] );
		$this->assertStringContainsString( '"aimlBlockId"', $filtered['post_content'] );
	}

	public function test_second_save_is_byte_identical(): void {
		$pipeline = $this->enabled_pipeline();
		$pipeline->register();

		$content = '<!-- wp:paragraph --><p>Stable</p><!-- /wp:paragraph -->';
		$data    = array(
			'post_content' => $content,
			'post_type'    => 'page',
		);

		$first  = $pipeline->apply_with_guard( $data, array( 'ID' => 0 ) );
		$second = $pipeline->apply_with_guard(
			array(
				'post_content' => $first['post_content'],
				'post_type'    => 'page',
			),
			array( 'ID' => 0 )
		);

		$this->assertSame( $first['post_content'], $second['post_content'] );
	}

	public function test_malformed_uuid_is_replaced_on_save(): void {
		$injector = new UuidInjector( new BlockRegistry(), new BlockIdentityLogger() );
		$content  = '<!-- wp:paragraph {"aimlBlockId":"bad"} --><p>Fix me</p><!-- /wp:paragraph -->';
		$result   = $injector->inject_content( $content );

		$this->assertTrue( $result->changed );
		$this->assertStringNotContainsString( '"aimlBlockId":"bad"', $result->content );
		$this->assertMatchesRegularExpression(
			'/\"aimlBlockId\":\"[0-9a-f-]{36}\"/',
			$result->content
		);
	}

	public function test_valid_uuid_survives_reinjection(): void {
		$uuid     = '550e8400-e29b-41d4-a716-446655440000';
		$injector = new UuidInjector( new BlockRegistry(), new BlockIdentityLogger() );
		$content  = sprintf(
			'<!-- wp:paragraph {"aimlBlockId":"%s"} --><p>Keep</p><!-- /wp:paragraph -->',
			$uuid
		);

		$result = $injector->inject_content( $content );

		$this->assertFalse( $result->changed );
		$this->assertStringContainsString( $uuid, $result->content );
	}

	public function test_injection_disabled_when_flags_off(): void {
		$pipeline = new SavePipeline(
			new Settings(
				array(
					'block_attr_registration_enabled' => true,
					'block_uuid_injection_enabled'    => false,
				)
			),
			new UuidInjector( new BlockRegistry(), new BlockIdentityLogger() ),
			$this->extractor
		);

		$this->assertFalse(
			$pipeline->should_inject(
				array( 'post_content' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' ),
				array( 'ID' => 1 )
			)
		);
	}

	public function test_unsupported_block_leaves_group_comment_untagged(): void {
		$injector = new UuidInjector( new BlockRegistry(), new BlockIdentityLogger() );
		$content  = '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>Inside</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
		$result   = $injector->inject_content( $content );

		$this->assertTrue( $result->changed );
		$this->assertSame( 1, substr_count( $result->content, Contract::ATTR_NAME ) );
		$this->assertStringContainsString( '"aimlBlockId"', $result->content );
	}

	public function test_elementor_post_is_skipped(): void {
		$post = $this->create_page( 'Elementor', '<!-- wp:paragraph --><p>Body</p><!-- /wp:paragraph -->' );
		update_post_meta( $post->ID, '_elementor_data', '[{"id":"abc"}]' );

		$pipeline = $this->enabled_pipeline();

		$this->assertFalse(
			$pipeline->should_inject(
				array( 'post_content' => $post->post_content ),
				array(
					'ID'        => $post->ID,
					'post_type' => 'page',
				)
			)
		);
	}

	public function test_wp_update_post_injects_uuid_when_flags_enabled(): void {
		$pipeline = $this->enabled_pipeline();
		$pipeline->register();

		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '<!-- wp:paragraph --><p>Editor save</p><!-- /wp:paragraph -->',
				'post_status'  => 'publish',
			)
		);

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '<!-- wp:paragraph --><p>Editor save</p><!-- /wp:paragraph -->',
			)
		);

		$saved = get_post( $post_id );
		$this->assertInstanceOf( \WP_Post::class, $saved );
		$this->assertStringContainsString( Contract::ATTR_NAME, $saved->post_content );

		preg_match(
			'/\"' . preg_quote( Contract::ATTR_NAME, '/' ) . '\":\"([^\"]+)\"/',
			$saved->post_content,
			$matches
		);
		$this->assertNotEmpty( $matches[1] );
		$this->assertTrue( UuidValidator::is_valid_non_empty( $matches[1] ) );
	}

	public function test_nested_blocks_receive_distinct_uuids(): void {
		$injector = new UuidInjector( new BlockRegistry(), new BlockIdentityLogger() );
		$content  = '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>One</p><!-- /wp:paragraph --><!-- wp:heading --><h2>Two</h2><!-- /wp:heading --></div><!-- /wp:group -->';
		$result   = $injector->inject_content( $content );

		preg_match_all(
			'/\"' . preg_quote( Contract::ATTR_NAME, '/' ) . '\":\"([^\"]+)\"/',
			$result->content,
			$matches
		);

		$this->assertCount( 2, $matches[1] );
		$this->assertNotSame( $matches[1][0], $matches[1][1] );
	}

	public function test_duplicate_uuids_are_repaired_on_canonical_save(): void {
		$uuid    = '550e8400-e29b-41d4-a716-446655440000';
		$content = sprintf(
			'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>First</p><!-- /wp:paragraph -->' . "\n\n" .
			'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>Second</p><!-- /wp:paragraph -->',
			Contract::ATTR_NAME,
			$uuid
		);

		$injector = new UuidInjector( new BlockRegistry(), new BlockIdentityLogger() );
		$result   = $injector->inject_content( $content );

		$this->assertTrue( $result->successful );
		preg_match_all(
			'/\"' . preg_quote( Contract::ATTR_NAME, '/' ) . '\":\"([^\"]+)\"/',
			$result->content,
			$matches
		);
		$this->assertCount( 2, $matches[1] );
		$this->assertSame( $uuid, $matches[1][0] );
		$this->assertNotSame( $uuid, $matches[1][1] );
	}

	public function test_recursion_guard_prevents_reentry(): void {
		$pipeline = $this->enabled_pipeline();
		SavePipeline::reset_guard_for_tests();

		$data = array(
			'post_content' => '<!-- wp:paragraph --><p>Loop</p><!-- /wp:paragraph -->',
			'post_type'    => 'page',
		);

		$reflection = new \ReflectionClass( SavePipeline::class );
		$property   = $reflection->getProperty( 'injecting' );
		$property->setAccessible( true );
		$property->setValue( null, true );

		$this->assertSame( $data, $pipeline->apply_with_guard( $data, array( 'ID' => 0 ) ) );
	}

	private function enabled_settings(): Settings {
		return new Settings(
			array(
				'block_attr_registration_enabled' => true,
				'block_uuid_injection_enabled'    => true,
			)
		);
	}

	private function enabled_pipeline(): SavePipeline {
		return new SavePipeline(
			$this->enabled_settings(),
			new UuidInjector( new BlockRegistry(), new BlockIdentityLogger() ),
			$this->extractor
		);
	}
}
