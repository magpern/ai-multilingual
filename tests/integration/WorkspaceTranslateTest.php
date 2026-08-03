<?php
/**
 * Workspace bulk translate integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Workspace\BatchOperationCoordinator;
use WP_REST_Request;

/**
 * Bulk translate and batch cap behavior.
 */
final class WorkspaceTranslateTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_translate_returns_not_configured_for_each_selected_segment(): void {
		$this->add_language();
		$post = $this->create_two_block_page();
		wp_set_current_user( $this->create_translator() );

		$load = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/segments'
		);
		$load->set_param( 'language', 'sv' );
		$segments = array_values(
			array_filter(
				rest_do_request( $load )->get_data()['segments'],
				static fn( array $row ): bool => ! empty( $row['can_edit'] )
			)
		);
		$this->assertNotEmpty( $segments );

		$request = new WP_REST_Request(
			'POST',
			'/aiml/v1/workspace/' . (int) $post->ID . '/translate'
		);
		$request->set_url_params( array( 'post_id' => (int) $post->ID ) );
		$request->set_param( 'language', 'sv' );
		$request->set_param(
			'segment_keys',
			array_column( $segments, 'segment_key' )
		);
		$request->set_param( 'mode', 'sync' );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'failed', $data['status'] );
		$this->assertCount( count( $segments ), $data['errors'] );
		$this->assertSame( 'aiml_ai_not_configured', $data['errors'][0]['code'] );
		$this->assertArrayHasKey( 'job_id', $data );
	}

	public function test_batch_translate_rejects_more_than_limit(): void {
		$this->add_language();
		$post = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$keys = array_fill( 0, BatchOperationCoordinator::BATCH_LIMIT + 1, 'b:test:content' );

		$request = new WP_REST_Request(
			'POST',
			'/aiml/v1/workspace/' . (int) $post->ID . '/translate'
		);
		$request->set_url_params( array( 'post_id' => (int) $post->ID ) );
		$request->set_param( 'language', 'sv' );
		$request->set_param( 'segment_keys', $keys );

		$response = rest_do_request( $request );
		$this->assertSame( 422, $response->get_status() );
	}
}
