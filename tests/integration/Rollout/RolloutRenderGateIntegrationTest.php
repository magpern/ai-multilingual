<?php
/**
 * Rollout render gate integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration\Rollout;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\BlockRenderLogger;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\FeatureFlags;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Rollout\RolloutConfigurationRepository;
use AIMultilingual\Rollout\RolloutPolicyService;
use AIMultilingual\Rollout\RolloutReasonCodes;
use AIMultilingual\Rollout\RolloutRenderGateBridge;
use AIMultilingual\Settings;
use AIMultilingual\Tests\Integration\AimlTestCase;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\BlockFrontendRenderLogger;
use AIMultilingual\Translation\BlockFrontendRenderer;
use AIMultilingual\Translation\BlockRenderGate;
use AIMultilingual\Translation\BlockRenderer;
use AIMultilingual\Translation\BlockTranslationLookup;
use AIMultilingual\Translation\BlockTranslationSanitizer;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\RenderGateContext;
use AIMultilingual\Translation\Store;
use WP_Post;

/**
 * @covers \AIMultilingual\Rollout\RolloutRenderGateBridge
 */
final class RolloutRenderGateIntegrationTest extends AimlTestCase {

	private const UUID = '550e8400-e29b-41d4-a716-446655440001';

	protected function setUp(): void {
		parent::setUp();

		delete_option( RolloutConfigurationRepository::OPTION );
		delete_option( 'aiml_rollout_snapshots' );
	}

	public function test_rollout_disabled_returns_source_path(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID, 'Hello' );
		$this->save_block_translation( $post, $swedish, self::UUID, 'Hej' );

		$repo = new RolloutConfigurationRepository();
		$repo->apply_change(
			array(
				'rollout_render_enabled' => false,
				'rollout_stage'          => 0,
			),
			1
		);

		$output = $this->render( $post, $swedish, $this->enabled_settings() );

		$this->assertStringContainsString( 'Hello', $output );
		$this->assertStringNotContainsString( 'Hej', $output );
	}

	public function test_outside_cohort_returns_source(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID, 'Hello' );
		$this->save_block_translation( $post, $swedish, self::UUID, 'Hej' );

		$repo = new RolloutConfigurationRepository();
		$repo->apply_change(
			array(
				'rollout_render_enabled' => true,
				'rollout_stage'          => 2,
				'allowed_post_ids'       => array( 99999 ),
				'allowed_language_codes' => array( 'sv' ),
			),
			1
		);

		$output = $this->render( $post, $swedish, $this->enabled_settings() );

		$this->assertStringContainsString( 'Hello', $output );
		$this->assertStringNotContainsString( 'Hej', $output );
	}

	public function test_inside_cohort_renders_translation(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID, 'Hello' );
		$this->save_block_translation( $post, $swedish, self::UUID, 'Hej' );

		$repo = new RolloutConfigurationRepository();
		$repo->apply_change(
			array(
				'rollout_render_enabled' => true,
				'rollout_stage'          => 2,
				'allowed_post_ids'       => array( (int) $post->ID ),
				'allowed_language_codes' => array( 'sv' ),
			),
			1
		);

		$output = $this->render( $post, $swedish, $this->enabled_settings() );

		$this->assertStringContainsString( 'Hej', $output );
	}

	public function test_shadow_stage_always_returns_source(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID, 'Hello' );
		$this->save_block_translation( $post, $swedish, self::UUID, 'Hej' );

		$repo = new RolloutConfigurationRepository();
		$repo->apply_change(
			array(
				'rollout_render_enabled' => true,
				'rollout_stage'          => 1,
				'allowed_post_ids'       => array( (int) $post->ID ),
			),
			1
		);

		$output = $this->render( $post, $swedish, $this->enabled_settings() );

		$this->assertStringContainsString( 'Hello', $output );
		$this->assertStringNotContainsString( 'Hej', $output );
	}

	public function test_bridge_denies_with_rollout_reason(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID, 'Hello' );

		$repo = new RolloutConfigurationRepository();
		$repo->apply_change(
			array(
				'rollout_render_enabled' => true,
				'rollout_stage'          => 2,
				'allowed_post_ids'       => array( 99999 ),
			),
			1
		);

		$bridge = new RolloutRenderGateBridge( new RolloutPolicyService(), $repo );
		$gate     = new BlockRenderGate( $bridge );
		$settings  = new Settings( $this->enabled_settings() );
		$extractor = new Extractor(
			$settings,
			new BlockExtractor( new AdapterRegistry(), new BlockRegistry( new AdapterRegistry() ), new BlockExtractionLogger() )
		);

		$this->context->set_current( $swedish );
		$decision = $gate->evaluate(
			RenderGateContext::from_request(
				$settings,
				$this->context,
				$extractor,
				$post,
				(string) $post->post_content,
				false
			)
		);

		$this->assertFalse( $decision->allowed );
		$this->assertSame( RolloutReasonCodes::POST_NOT_ALLOWLISTED, $decision->reason );
		$this->assertNotNull( $decision->rollout );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function enabled_settings(): array {
		return array(
			'block_attr_registration_enabled'  => true,
			'block_uuid_injection_enabled'     => true,
			'block_extraction_enabled'         => true,
			'block_frontend_rendering_enabled' => true,
		);
	}

	/**
	 * @param array<string, mixed> $settings_data Settings override.
	 */
	private function render( WP_Post $post, object $language, array $settings_data ): string {
		$settings  = new Settings( $settings_data );
		$extractor = new Extractor(
			$settings,
			new BlockExtractor(
				new AdapterRegistry(),
				new BlockRegistry( new AdapterRegistry() ),
				new BlockExtractionLogger()
			)
		);

		$bridge = new RolloutRenderGateBridge(
			new RolloutPolicyService(),
			new RolloutConfigurationRepository()
		);

		$frontend = new BlockFrontendRenderer(
			new BlockRenderGate( $bridge ),
			new BlockTranslationLookup( $this->store ),
			new BlockTranslationSanitizer(),
			new BlockRenderer( new AdapterRegistry(), new BlockRenderLogger() ),
			new BlockFrontendRenderLogger(),
			$settings,
			$this->context,
			$extractor
		);

		$this->context->set_current( $language );

		return $frontend->render( $post, (string) $post->post_content );
	}

	private function create_block_page( string $uuid, string $text ): WP_Post {
		$block = '<!-- wp:paragraph {"aimlBlockId":"' . $uuid . '"} -->'
			. '<p>' . $text . '</p>'
			. '<!-- /wp:paragraph -->';

		return self::factory()->post->create_and_get(
			array(
				'post_type'    => 'page',
				'post_content' => $block,
				'post_status'  => 'publish',
			)
		);
	}

	private function save_block_translation( \WP_Post $post, object $language, string $uuid, string $text ): void {
		$key = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => Extractor::FIELD_CONTENT,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_BLOCK,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => '<p>Hello</p>',
				'translated_text' => $text,
				'status'          => Store::STATUS_REVIEWED,
			)
		);
	}
}
