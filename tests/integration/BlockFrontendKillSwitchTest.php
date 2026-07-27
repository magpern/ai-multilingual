<?php
/**
 * Strategy F frontend rendering kill-switch integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\BlockRenderLogger;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\FeatureFlags;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Database\Schema;
use AIMultilingual\Settings;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\BlockFrontendRenderLogger;
use AIMultilingual\Translation\BlockFrontendRenderer;
use AIMultilingual\Translation\BlockRenderGate;
use AIMultilingual\Translation\BlockRenderer;
use AIMultilingual\Translation\BlockTranslationLookup;
use AIMultilingual\Translation\BlockTranslationSanitizer;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Renderer;
use AIMultilingual\Translation\Store;

/**
 * Verifies block_frontend_rendering_enabled kill-switch behavior end-to-end.
 */
final class BlockFrontendKillSwitchTest extends AimlTestCase {

	private const UUID_A = '550e8400-e29b-41d4-a716-446655440000';

	private ?Renderer $renderer = null;

	public function test_enabled_renderer_returns_translated_content(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Hello' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Hej' );
		$this->register_block_renderer( $this->enabled_settings() );

		$this->assertStringContainsString( 'Hej', $this->render_content( $post, $swedish ) );
	}

	public function test_disabling_frontend_rendering_returns_original_content_immediately(): void {
		global $wpdb;

		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Hello' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Hej' );

		$this->register_block_renderer( $this->enabled_settings() );
		$this->assertStringContainsString( 'Hej', $this->render_content( $post, $swedish ) );

		$this->register_block_renderer( $this->settings_with_frontend( false ) );
		$rendered = $this->render_content( $post, $swedish );

		$this->assertStringContainsString( 'Hello', $rendered );
		$this->assertStringNotContainsString( 'Hej', $rendered );

		$stored = $wpdb->get_var( $wpdb->prepare( 'SELECT post_content FROM ' . $wpdb->posts . ' WHERE ID = %d', $post->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertSame( $post->post_content, $stored );
	}

	public function test_kill_switch_preserves_stored_translation_and_re_enable_restores_output(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Hello' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Hej' );

		$before = $this->store->load_object( Store::SOURCE_POST, (int) $post->ID, (int) $swedish->language_id );

		$this->register_block_renderer( $this->enabled_settings() );
		$this->assertStringContainsString( 'Hej', $this->render_content( $post, $swedish ) );

		$this->register_block_renderer( $this->settings_with_frontend( false ) );
		$this->assertStringContainsString( 'Hello', $this->render_content( $post, $swedish ) );

		$mid = $this->store->load_object( Store::SOURCE_POST, (int) $post->ID, (int) $swedish->language_id );
		$this->assertSame( $before, $mid );

		$this->register_block_renderer( $this->enabled_settings() );
		$this->assertStringContainsString( 'Hej', $this->render_content( $post, $swedish ) );
	}

	public function test_disabled_gate_short_circuits_before_store_lookup(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Hello' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Hej' );

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

		$this->register_block_renderer( $this->settings_with_frontend( false ) );
		$this->render_content( $post, $swedish );

		$this->assert_event_present( $events, BlockFrontendRenderLogger::EVENT_GATE_DENIED );
		$this->assert_event_absent( $events, BlockFrontendRenderLogger::EVENT_LOOKUP_COMPLETE );
		$this->assert_event_absent( $events, BlockFrontendRenderLogger::EVENT_RENDER_COMPLETE );
		$this->assert_event_absent( $events, BlockFrontendRenderLogger::EVENT_RENDER_FAILED );

		$denied = $this->first_event( $events, BlockFrontendRenderLogger::EVENT_GATE_DENIED );
		$this->assertSame( BlockRenderGate::REASON_FRONTEND_DISABLED, $denied['context']['denial_reason'] );
	}

	public function test_disabled_gate_bypasses_stale_orphan_and_malformed_paths(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Hello' );

		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Hej' );
		$this->mark_translation_stale( $post, $swedish, self::UUID_A );

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

		$this->register_block_renderer( $this->settings_with_frontend( false ) );
		$output = $this->render_content( $post, $swedish );

		$this->assertStringContainsString( 'Hello', $output );
		$this->assert_event_present( $events, BlockFrontendRenderLogger::EVENT_GATE_DENIED );
		$this->assert_event_absent( $events, BlockFrontendRenderLogger::EVENT_LOOKUP_FAILED );
	}

	public function test_disabled_gate_is_safe_with_duplicate_uuid_content(): void {
		$content = $this->paragraph_content( self::UUID_A, 'One' )
			. $this->paragraph_content( self::UUID_A, 'Two' );
		$post    = $this->create_page( 'Dup UUID', $content );
		$swedish = $this->add_language();

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

		$this->register_block_renderer( $this->settings_with_frontend( false ) );
		$output = $this->render_content( $post, $swedish );

		$this->assertStringContainsString( 'One', $output );
		$this->assert_event_present( $events, BlockFrontendRenderLogger::EVENT_GATE_DENIED );
		$this->assertSame(
			BlockRenderGate::REASON_FRONTEND_DISABLED,
			$this->first_event( $events, BlockFrontendRenderLogger::EVENT_GATE_DENIED )['context']['denial_reason']
		);
	}

	public function test_valid_frontend_disable_emits_flag_changed_only(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array(
					FeatureFlags::REGISTRATION    => true,
					FeatureFlags::INJECTION       => true,
					FeatureFlags::EXTRACTION      => true,
					FeatureFlags::FRONTEND_RENDER => true,
				)
			)
		);

		$flag_events        = array();
		$operational_events = array();

		add_action(
			'aiml_settings_flag_changed',
			static function ( array $payload ) use ( &$flag_events ): void {
				$flag_events[] = $payload;
			}
		);
		add_action(
			'aiml_settings_operational_log',
			static function ( string $event, array $context ) use ( &$operational_events ): void {
				$operational_events[] = array(
					'event'   => $event,
					'context' => $context,
				);
			},
			10,
			2
		);

		$page = new \AIMultilingual\Admin\SettingsPage( new Settings(), $this->languages );
		$page->register();

		$page->sanitize_settings(
			array(
				FeatureFlags::REGISTRATION => '1',
				FeatureFlags::INJECTION    => '1',
				FeatureFlags::EXTRACTION   => '1',
			)
		);

		$this->assertCount( 1, $flag_events );
		$this->assertSame( FeatureFlags::FRONTEND_RENDER, $flag_events[0]['flag'] );
		$this->assertTrue( $flag_events[0]['old'] );
		$this->assertFalse( $flag_events[0]['new'] );
		$this->assertSame( 'admin_settings', $flag_events[0]['source'] );
		$this->assertSame( array(), $operational_events );
	}

	public function test_feature_defaults_remain_false(): void {
		$defaults = Settings::defaults();

		foreach ( FeatureFlags::PRODUCTION_FLAGS as $flag ) {
			$this->assertFalse( $defaults[ $flag ], $flag );
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function enabled_settings(): Settings {
		return new Settings(
			array(
				FeatureFlags::REGISTRATION    => true,
				FeatureFlags::INJECTION       => true,
				FeatureFlags::EXTRACTION      => true,
				FeatureFlags::FRONTEND_RENDER => true,
			)
		);
	}

	private function settings_with_frontend( bool $enabled ): Settings {
		return new Settings(
			array(
				FeatureFlags::REGISTRATION    => true,
				FeatureFlags::INJECTION       => true,
				FeatureFlags::EXTRACTION      => true,
				FeatureFlags::FRONTEND_RENDER => $enabled,
			)
		);
	}

	private function register_block_renderer( Settings $settings ): void {
		if ( null !== $this->renderer ) {
			remove_filter( 'the_content', array( $this->renderer, 'filter_content' ), 1 );
			remove_filter( 'the_title', array( $this->renderer, 'filter_title' ), 10 );
			remove_filter( 'get_the_excerpt', array( $this->renderer, 'filter_excerpt' ), 10 );
			remove_filter( 'document_title_parts', array( $this->renderer, 'filter_document_title' ), 20 );
		}

		$registry  = new AdapterRegistry();
		$extractor = new Extractor(
			$settings,
			new BlockExtractor(
				$registry,
				new BlockRegistry( $registry ),
				new BlockExtractionLogger()
			)
		);
		$frontend  = new BlockFrontendRenderer(
			new BlockRenderGate(),
			new BlockTranslationLookup( $this->store ),
			new BlockTranslationSanitizer(),
			new BlockRenderer( $registry, new BlockRenderLogger() ),
			new BlockFrontendRenderLogger(),
			$settings,
			$this->context,
			$extractor
		);

		$this->renderer = new Renderer( $this->context, $this->store, $extractor, $frontend );
		$this->renderer->register();
	}

	private function create_block_page( string $uuid, string $text ): \WP_Post {
		return $this->create_page( 'Kill switch', $this->paragraph_content( $uuid, $text ) );
	}

	private function paragraph_content( string $uuid, string $text ): string {
		return sprintf(
			'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>%3$s</p><!-- /wp:paragraph -->',
			Contract::ATTR_NAME,
			$uuid,
			$text
		);
	}

	/**
	 * @param list<array{event: string, context: array<string, mixed>}> $events Events.
	 * @param string                                                    $name   Event name.
	 */
	private function assert_event_present( array $events, string $name ): void {
		$this->assertNotNull( $this->first_event( $events, $name ), "Expected event {$name}." );
	}

	/**
	 * @param list<array{event: string, context: array<string, mixed>}> $events Events.
	 * @param string                                                    $name   Event name.
	 */
	private function assert_event_absent( array $events, string $name ): void {
		$this->assertNull( $this->first_event( $events, $name ), "Did not expect event {$name}." );
	}

	/**
	 * @param list<array{event: string, context: array<string, mixed>}> $events Events.
	 * @param string                                                    $name   Event name.
	 * @return array{event: string, context: array<string, mixed>}|null
	 */
	private function first_event( array $events, string $name ): ?array {
		foreach ( $events as $event ) {
			if ( $name === $event['event'] ) {
				return $event;
			}
		}

		return null;
	}

	/**
	 * @param \WP_Post $post     Post.
	 * @param object   $language Language row.
	 * @param string   $uuid     Block UUID.
	 */
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

	/**
	 * @param \WP_Post $post     Post.
	 * @param object   $language Language row.
	 * @param string   $uuid     Block UUID.
	 */
	private function mark_translation_stale( \WP_Post $post, object $language, string $uuid ): void {
		global $wpdb;

		$key = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::translations(),
			array( 'is_stale' => 1 ),
			array(
				'source_type' => Store::SOURCE_POST,
				'source_id'   => (int) $post->ID,
				'language_id' => (int) $language->language_id,
				'segment_key' => $key,
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
