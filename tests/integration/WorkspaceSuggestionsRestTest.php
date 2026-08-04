<?php
/**
 * Workspace AI suggest REST tests (F11 WP6).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Translation\Store;
use WP_REST_Request;

/**
 * Suggest is read-only; Null provider yields empty AI suggestions.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WorkspaceSuggestionsRestTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_suggest_endpoint_does_not_persist_translation(): void {
		$language = $this->add_language();
		$uuid     = '550e8400-e29b-41d4-a716-446655440000';
		$key      = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
		$post     = $this->create_page(
			'Suggest',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>Hello suggest world text</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuid
			)
		);

		wp_set_current_user( $this->create_translator() );

		$request = new WP_REST_Request(
			'POST',
			'/aiml/v1/workspace/' . (int) $post->ID . '/segments/' . rawurlencode( $key ) . '/suggest'
		);
		$request->set_param( 'language', 'sv' );
		$request->set_param( 'profile', 'improve' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'profile' => 'improve' ) ) );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'meta', $data );
		$this->assertArrayHasKey( 'suggestions', $data['meta'] );

		// Null provider → no AI suggestions; Store remains empty for this segment.
		$stored = $this->store->load_object( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id );
		$row    = $stored[ $key ] ?? null;
		if ( null !== $row ) {
			$this->assertSame( '', (string) ( $row->translated_text ?? '' ) );
		}
	}

	public function test_translate_still_attempts_persist_path(): void {
		$this->add_language();
		$post = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$request = new WP_REST_Request(
			'POST',
			'/aiml/v1/workspace/' . (int) $post->ID . '/translate'
		);
		$request->set_url_params( array( 'post_id' => (int) $post->ID ) );
		$request->set_param( 'language', 'sv' );
		$request->set_param( 'segment_keys', array( $this->default_segment_key() ) );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $response->get_data()['errors'] ?? array() );
	}
}
