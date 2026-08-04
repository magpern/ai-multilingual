<?php
/**
 * Workspace save path TM write-back tests (F11.1 D1).
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
 * Confirms eligible workspace saves populate TM; machine persist does not.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WorkspaceTmWriteBackTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_human_save_writes_tm_entry(): void {
		$language = $this->add_language();
		$default  = $this->languages->default();
		$this->assertNotNull( $default );

		$uuid = '550e8400-e29b-41d4-a716-446655440000';
		$key  = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
		$body = 'Unique product warranty terms for Nordic markets';
		$post = $this->create_page(
			'TM write-back human',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>%3$s</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuid,
				$body
			)
		);

		wp_set_current_user( $this->create_translator() );

		$get = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/segments'
		);
		$get->set_param( 'language', 'sv' );
		$get_response = rest_do_request( $get );
		$this->assertSame( 200, $get_response->get_status() );
		$data    = $get_response->get_data();
		$segment = $this->first_block_segment( $data['segments'] ?? array() );
		$this->assertNotNull( $segment );

		$request = new WP_REST_Request(
			'POST',
			'/aiml/v1/workspace/' . (int) $post->ID . '/segments/' . rawurlencode( $key )
		);
		$request->set_param( 'language', 'sv' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'translated_text' => 'Unika produktgarantivillkor för nordiska marknader',
					'source_hash'     => (string) $segment['source_hash'],
				)
			)
		);

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );

		$tm  = new TranslationMemoryService( new TMRepository() );
		$hit = $tm->lookup_exact(
			(string) $segment['source_text'],
			(int) $default->language_id,
			(int) $language->language_id,
			'block:core/paragraph'
		);

		$this->assertNotNull( $hit );
		$this->assertSame( 'Unika produktgarantivillkor för nordiska marknader', $hit['target_text'] );
		$this->assertSame( TMRepository::ORIGIN_HUMAN, $hit['origin'] );
	}

	public function test_accept_tm_exact_records_usage_without_new_origin(): void {
		$language = $this->add_language();
		$default  = $this->languages->default();
		$this->assertNotNull( $default );

		$uuid = '550e8400-e29b-41d4-a716-446655440000';
		$key  = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
		$body = 'Welcome to our online store for quality products';
		$text = '<p>' . $body . '</p>';

		$tm  = new TranslationMemoryService( new TMRepository() );
		$row = $tm->repository()->upsert(
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
		$this->assertNotInstanceOf( \WP_Error::class, $row );
		$tm_id      = (int) $row->tm_id;
		$use_before = (int) $row->use_count;

		$post = $this->create_page(
			'Accept TM usage',
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

		$refreshed = $tm->repository()->find( $tm_id );
		$this->assertNotNull( $refreshed );
		$this->assertSame( TMRepository::ORIGIN_HUMAN, (string) $refreshed->origin );
		$this->assertGreaterThan( $use_before, (int) $refreshed->use_count );
	}

	public function test_machine_translate_does_not_write_tm(): void {
		$language = $this->add_language();
		$default  = $this->languages->default();
		$this->assertNotNull( $default );

		$uuid = '550e8400-e29b-41d4-a716-446655440000';
		$key  = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
		$body = 'Machine path must never pollute translation memory banks';
		$post = $this->create_page(
			'Machine no TM',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>%3$s</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuid,
				$body
			)
		);

		$source_html = '<p>' . $body . '</p>';

		// Simulate machine persist the way TranslationService does (bypasses save_segment).
		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => Contract::FIELD_CONTENT,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_BLOCK,
				'segment_order'   => 0,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => $source_html,
				'translated_text' => 'Maskinöversättning ska inte skriva till TM',
				'status'          => Store::STATUS_MACHINE_TRANSLATED,
			)
		);

		$tm  = new TranslationMemoryService( new TMRepository() );
		$hit = $tm->lookup_exact(
			$source_html,
			(int) $default->language_id,
			(int) $language->language_id,
			'block:core/paragraph'
		);

		$this->assertNull( $hit );
	}
}
