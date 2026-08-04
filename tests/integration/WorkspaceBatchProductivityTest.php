<?php
/**
 * Batch TM accept and QA productivity tests (F11 WP10).
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
 * Accept TM exact and batch QA endpoints.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WorkspaceBatchProductivityTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_accept_tm_exact_saves_match(): void {
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
			'Accept TM',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>%3$s</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuid,
				$body
			)
		);

		wp_set_current_user( $this->create_translator() );

		$request = new WP_REST_Request(
			'POST',
			'/aiml/v1/workspace/' . (int) $post->ID . '/suggestions/accept'
		);
		$request->set_param( 'language', 'sv' );
		$request->set_param( 'segment_keys', array( $key ) );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'completed', $data['status'] );
		$this->assertNotEmpty( $data['segments'] );
		$this->assertSame(
			'Välkommen till vår webbutik för kvalitetsprodukter',
			$data['segments'][0]['translated_text']
		);
	}

	public function test_qa_batch_returns_summary(): void {
		$this->add_language();
		$post = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$request = new WP_REST_Request(
			'POST',
			'/aiml/v1/workspace/' . (int) $post->ID . '/qa'
		);
		$request->set_param( 'language', 'sv' );
		$request->set_param( 'segment_keys', array( $this->default_segment_key() ) );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'summary', $data );
		$this->assertArrayHasKey( 'errors', $data['summary'] );
		$this->assertNotEmpty( $data['segments'] );
	}
}
