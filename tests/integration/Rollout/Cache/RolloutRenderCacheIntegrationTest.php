<?php
/**
 * Render cache integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration\Rollout\Cache;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\BlockRenderLogger;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Cache\Cache;
use AIMultilingual\Rollout\Cache\RenderCacheKeyFactory;
use AIMultilingual\Rollout\Cache\RenderCacheInvalidationService;
use AIMultilingual\Rollout\Cache\RenderCacheService;
use AIMultilingual\Rollout\Cache\RolloutRenderCacheBridge;
use AIMultilingual\Rollout\RolloutConfigurationRepository;
use AIMultilingual\Rollout\RolloutPolicyService;
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
use AIMultilingual\Translation\Store;
use WP_Post;

/**
 * @covers \AIMultilingual\Rollout\Cache\RolloutRenderCacheBridge
 */
final class RolloutRenderCacheIntegrationTest extends AimlTestCase {

	private const UUID = '550e8400-e29b-41d4-a716-446655440099';

	protected function setUp(): void {
		parent::setUp();

		delete_option( RolloutConfigurationRepository::OPTION );
	}

	public function test_disabled_cache_does_not_alter_output(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID, 'Hello' );
		$this->save_block_translation( $post, $swedish, self::UUID, 'Hej' );

		$repo = new RolloutConfigurationRepository();
		$repo->apply_change(
			array(
				'rollout_render_enabled' => true,
				'rollout_stage'          => 2,
				'allowed_post_ids'       => array( (int) $post->ID ),
				'allowed_languages'      => array( 'sv' ),
				'render_cache_enabled'   => false,
			),
			1
		);

		$output = $this->render( $post, $swedish, $this->enabled_settings(), true );

		$this->assertStringContainsString( 'Hej', $output );
	}

	public function test_cache_hit_returns_cached_html(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID, 'Hello' );
		$this->save_block_translation( $post, $swedish, self::UUID, 'Hej' );

		$content = (string) $post->post_content;
		$repo    = new RolloutConfigurationRepository();
		$repo->apply_change(
			array(
				'rollout_render_enabled' => true,
				'rollout_stage'          => 2,
				'allowed_post_ids'       => array( (int) $post->ID ),
				'allowed_languages'      => array( 'sv' ),
				'render_cache_enabled'   => true,
			),
			1
		);

		$config  = $repo->get();
		$factory = new RenderCacheKeyFactory();
		$rows    = array_values(
			$this->store->load_object( Store::SOURCE_POST, (int) $post->ID, (int) $swedish->language_id )
		);
		$key     = $factory->build(
			(int) $post->ID,
			$factory->source_content_hash( $content ),
			(int) $swedish->language_id,
			$rows,
			$config
		);

		( new RenderCacheService( $this->cache ) )->set(
			$key,
			(int) $swedish->language_id,
			'<p>Cached only</p>',
			$config
		);

		$output = $this->render( $post, $swedish, $this->enabled_settings(), true );

		$this->assertStringContainsString( 'Cached only', $output );
		$this->assertStringNotContainsString( 'Hej', $output );
	}

	public function test_invalidation_after_language_flush_clears_entry(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID, 'Hello' );
		$this->save_block_translation( $post, $swedish, self::UUID, 'Hej' );

		$content = (string) $post->post_content;
		$repo    = new RolloutConfigurationRepository();
		$repo->apply_change(
			array(
				'rollout_render_enabled' => true,
				'rollout_stage'          => 2,
				'allowed_post_ids'       => array( (int) $post->ID ),
				'allowed_languages'      => array( 'sv' ),
				'render_cache_enabled'   => true,
			),
			1
		);

		$config  = $repo->get();
		$factory = new RenderCacheKeyFactory();
		$rows    = array_values(
			$this->store->load_object( Store::SOURCE_POST, (int) $post->ID, (int) $swedish->language_id )
		);
		$key     = $factory->build(
			(int) $post->ID,
			$factory->source_content_hash( $content ),
			(int) $swedish->language_id,
			$rows,
			$config
		);

		$render_cache = new RenderCacheService( $this->cache );
		$render_cache->set( $key, (int) $swedish->language_id, '<p>Stale</p>', $config );

		( new RenderCacheInvalidationService( null, $this->cache ) )->invalidate_language(
			(int) $swedish->language_id,
			'translation_save'
		);

		$this->assertNull( $render_cache->get( $key, (int) $swedish->language_id, $config ) );
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
	 * @param WP_Post              $post          Source post.
	 * @param object               $language      Target language object.
	 * @param array<string, mixed> $settings_data Settings override.
	 * @param bool                 $with_cache    Whether render cache is enabled.
	 */
	private function render( WP_Post $post, object $language, array $settings_data, bool $with_cache ): string {
		$settings  = new Settings( $settings_data );
		$extractor = new Extractor(
			$settings,
			new BlockExtractor(
				new AdapterRegistry(),
				new BlockRegistry( new AdapterRegistry() ),
				new BlockExtractionLogger()
			)
		);

		$config_repo  = new RolloutConfigurationRepository();
		$bridge       = new RolloutRenderGateBridge( new RolloutPolicyService(), $config_repo );
		$render_cache = $with_cache
			? new RolloutRenderCacheBridge(
				new RenderCacheService( $this->cache ),
				new RenderCacheKeyFactory(),
				$this->store,
				$config_repo
			)
			: null;

		$frontend = new BlockFrontendRenderer(
			new BlockRenderGate( $bridge ),
			new BlockTranslationLookup( $this->store ),
			new BlockTranslationSanitizer(),
			new BlockRenderer( new AdapterRegistry(), new BlockRenderLogger() ),
			new BlockFrontendRenderLogger(),
			$settings,
			$this->context,
			$extractor,
			$render_cache
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

	private function save_block_translation( WP_Post $post, object $language, string $uuid, string $text ): void {
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
