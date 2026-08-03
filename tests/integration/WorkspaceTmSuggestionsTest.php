<?php
/**
 * TM suggestions on GET workspace segments (F11 WP3).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Translation\Memory\TMRepository;
use AIMultilingual\Translation\Memory\TranslationMemoryService;
use AIMultilingual\Translation\Store;
use WP_REST_Request;

/**
 * Verifies meta.suggestions via TranslationSuggestionService (not TMS in WorkspaceService).
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WorkspaceTmSuggestionsTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_get_segments_includes_ranked_tm_suggestions(): void {
		$language = $this->add_language();
		$default  = $this->languages->default();
		$this->assertNotNull( $default );

		$uuid = '550e8400-e29b-41d4-a716-446655440000';
		$key  = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
		$body = 'Welcome to our online store for quality products';
		$text = '<p>' . $body . '</p>';

		$tm = new TranslationMemoryService( new TMRepository() );
		$tm->repository()->upsert(
			array(
				'source_lang_id' => (int) $default->language_id,
				'target_lang_id' => (int) $language->language_id,
				'source_hash'    => Store::source_hash( $text, Store::FORMAT_HTML ),
				'source_text'    => $text,
				'target_text'    => 'Välkommen till vår webbutik för kvalitetsprodukter',
				'text_format'    => Store::FORMAT_HTML,
				'context'        => 'block:core/paragraph',
				'origin'         => TMRepository::ORIGIN_HUMAN,
			)
		);

		$post = $this->create_page(
			'TM suggest',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>%3$s</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuid,
				$body
			)
		);

		wp_set_current_user( $this->create_translator() );

		$request = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/segments'
		);
		$request->set_param( 'language', 'sv' );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );

		$segments = $response->get_data()['segments'];
		$segment  = null;
		foreach ( $segments as $row ) {
			if ( (string) ( $row['segment_key'] ?? '' ) === $key ) {
				$segment = $row;
				break;
			}
		}

		$this->assertNotNull( $segment );
		$this->assertArrayHasKey( 'meta', $segment );
		$this->assertArrayHasKey( 'suggestions', $segment['meta'] );
		$this->assertNotEmpty( $segment['meta']['suggestions'] );

		$top = $segment['meta']['suggestions'][0];
		$this->assertSame( 'tm', $top['provider_id'] );
		$this->assertSame( 'Välkommen till vår webbutik för kvalitetsprodukter', $top['target_text'] );
		$this->assertSame( 1, $top['rank_tier'] );
		$this->assertSame( 100.0, $top['confidence'] );
	}
}
