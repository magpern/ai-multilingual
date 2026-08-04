<?php
/**
 * Workspace QA REST + save gate tests (F11 WP8).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use WP_REST_Request;

/**
 * Asserts meta.qa on GET and that saves are blocked on QA errors.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WorkspaceQARestTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_get_segments_includes_meta_qa(): void {
		$this->add_language();
		$uuid = '550e8400-e29b-41d4-a716-446655440000';
		$key  = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
		$post = $this->create_page(
			'QA meta',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>Hello QA world</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuid
			)
		);

		wp_set_current_user( $this->create_translator() );
		$request = new WP_REST_Request( 'GET', '/aiml/v1/workspace/' . (int) $post->ID . '/segments' );
		$request->set_param( 'language', 'sv' );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );

		$segment = null;
		foreach ( $response->get_data()['segments'] as $row ) {
			if ( (string) ( $row['segment_key'] ?? '' ) === $key ) {
				$segment = $row;
				break;
			}
		}

		$this->assertNotNull( $segment );
		$this->assertArrayHasKey( 'qa', $segment['meta'] );
		$this->assertArrayHasKey( 'issues', $segment['meta']['qa'] );
		$this->assertArrayHasKey( 'summary', $segment['meta']['qa'] );
	}

	public function test_save_blocked_on_placeholder_error(): void {
		$this->add_language();
		$uuid = '550e8400-e29b-41d4-a716-446655440001';
		$key  = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
		$post = $this->create_page(
			'QA block',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>Hello {name}</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuid
			)
		);

		wp_set_current_user( $this->create_translator() );

		$get = new WP_REST_Request( 'GET', '/aiml/v1/workspace/' . (int) $post->ID . '/segments' );
		$get->set_param( 'language', 'sv' );
		$segments = rest_do_request( $get )->get_data()['segments'];
		$hash     = '';
		foreach ( $segments as $row ) {
			if ( (string) ( $row['segment_key'] ?? '' ) === $key ) {
				$hash = (string) $row['source_hash'];
				break;
			}
		}

		$request = $this->workspace_save_request(
			(int) $post->ID,
			array( 'segment_key' => $key ),
			array(
				'translated_text' => 'Hej utan placeholder',
				'source_hash'     => $hash,
			)
		);

		$response = rest_do_request( $request );
		$this->assertSame( 422, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'aiml_qa_blocked', $data['code'] ?? '' );
		$this->assertGreaterThan( 0, $data['qa']['summary']['errors'] ?? 0 );
	}
}
