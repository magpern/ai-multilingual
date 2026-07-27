<?php
/**
 * Strategy F block metrics integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\BlockIdentityLogger;
use AIMultilingual\Block\BlockMetricsAggregator;
use AIMultilingual\Block\Contract;
use AIMultilingual\Settings;
use AIMultilingual\Translation\BlockFrontendRenderLogger;

/**
 * Metrics aggregation through live hooks and frontend rendering.
 */
final class BlockMetricsIntegrationTest extends AimlTestCase {

	private BlockMetricsAggregator $metrics;

	protected function setUp(): void {
		parent::setUp();

		$this->metrics = new BlockMetricsAggregator();
		$this->metrics->register();
	}

	protected function tearDown(): void {
		$this->metrics->reset();

		parent::tearDown();
	}

	public function test_hook_registration_accumulates_identity_events(): void {
		do_action(
			'aiml_block_identity_log',
			BlockIdentityLogger::EVENT_UUID_CREATED,
			array( 'block_name' => 'core/paragraph' )
		);

		$this->assertSame(
			1,
			$this->metrics->snapshot()->counters[ BlockMetricsAggregator::COUNTER_UUID_CREATED ]
		);
	}

	public function test_frontend_render_complete_includes_elapsed_ms(): void {
		$events = array();

		add_action(
			'aiml_block_frontend_render_log',
			static function ( string $event, array $context ) use ( &$events ): void {
				$events[] = array(
					'event'   => $event,
					'context' => $context,
				);
			},
			5,
			2
		);

		$swedish = $this->add_language();
		$post    = $this->create_block_page( '550e8400-e29b-41d4-a716-446655440000', 'Hello' );
		$this->save_block_translation( $post, $swedish, '550e8400-e29b-41d4-a716-446655440000', 'Hej' );
		$this->register_block_renderer();

		$rendered = $this->render_content( $post, $swedish );

		$this->assertStringContainsString( 'Hej', $rendered );

		$complete = array_values(
			array_filter(
				$events,
				static fn( array $row ): bool => BlockFrontendRenderLogger::EVENT_RENDER_COMPLETE === $row['event']
			)
		);

		$this->assertNotEmpty( $complete );
		$this->assertArrayHasKey( 'elapsed_ms', $complete[0]['context'] );
		$this->assertIsInt( $complete[0]['context']['elapsed_ms'] );
		$this->assertGreaterThanOrEqual( 0, $complete[0]['context']['elapsed_ms'] );

		$snapshot = $this->metrics->snapshot();
		$this->assertGreaterThanOrEqual( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_RENDER_COMPLETED ] );
		$this->assertGreaterThanOrEqual( 1, $snapshot->render_count );
	}

	public function test_plugin_source_wires_metrics_aggregator(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/src/Plugin.php'
		);

		$this->assertStringContainsString( 'new BlockMetricsAggregator', $source );
		$this->assertStringContainsString( '$metrics->register()', $source );
		$this->assertDoesNotMatchRegularExpression(
			'/BlockMetricsAggregator.*->snapshot\s*\(\).*register_stale_detection/s',
			$source
		);
	}

	/**
	 * @param string $uuid Block UUID.
	 * @param string $text Paragraph text.
	 */
	private function create_block_page( string $uuid, string $text ): \WP_Post {
		return $this->create_page(
			'Metrics ' . $text,
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>%3$s</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuid,
				$text
			)
		);
	}

	private function register_block_renderer(): void {
		$settings = new Settings(
			array(
				'block_attr_registration_enabled'  => true,
				'block_uuid_injection_enabled'     => true,
				'block_extraction_enabled'         => true,
				'block_frontend_rendering_enabled' => true,
			)
		);

		$registry  = new \AIMultilingual\Block\AdapterRegistry();
		$extractor = new \AIMultilingual\Translation\Extractor(
			$settings,
			new \AIMultilingual\Translation\BlockExtractor(
				$registry,
				new \AIMultilingual\Block\BlockRegistry( $registry ),
				new \AIMultilingual\Block\BlockExtractionLogger()
			)
		);

		$frontend = new \AIMultilingual\Translation\BlockFrontendRenderer(
			new \AIMultilingual\Translation\BlockRenderGate(),
			new \AIMultilingual\Translation\BlockTranslationLookup( $this->store ),
			new \AIMultilingual\Translation\BlockTranslationSanitizer(),
			new \AIMultilingual\Translation\BlockRenderer( $registry, new \AIMultilingual\Block\BlockRenderLogger() ),
			new \AIMultilingual\Translation\BlockFrontendRenderLogger(),
			$settings,
			$this->context,
			$extractor
		);

		$renderer = new \AIMultilingual\Translation\Renderer(
			$this->context,
			$this->store,
			$extractor,
			$frontend
		);
		$renderer->register();
	}

	private function save_block_translation( \WP_Post $post, object $language, string $uuid, string $text ): void {
		$key = \AIMultilingual\Block\SegmentKey::build( $uuid, Contract::FIELD_CONTENT );

		$source_html = '<p>Hello</p>';
		if ( preg_match( '/<p>(.*?)<\/p>/s', $post->post_content, $matches ) ) {
			$source_html = '<p>' . $matches[1] . '</p>';
		}

		$this->store->save_translation(
			array(
				'source_type'     => \AIMultilingual\Translation\Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => \AIMultilingual\Translation\Extractor::FIELD_CONTENT,
				'segment_key'     => $key,
				'segment_kind'    => \AIMultilingual\Translation\Store::KIND_BLOCK,
				'text_format'     => \AIMultilingual\Translation\Store::FORMAT_HTML,
				'source_text'     => $source_html,
				'translated_text' => $text,
				'status'          => \AIMultilingual\Translation\Store::STATUS_REVIEWED,
			)
		);
	}

	private function render_content( \WP_Post $post, object $language ): string {
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );
		$this->context->set_current( $language );

		$output = trim( (string) apply_filters( 'the_content', $post->post_content ) );
		wp_reset_postdata();

		return $output;
	}
}
