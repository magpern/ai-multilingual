<?php
/**
 * Workspace batch save integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\Store;
use WP_REST_Request;

/**
 * Batch save partial success and ordering.
 */
final class WorkspaceBatchTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_batch_save_partial_success_preserves_per_item_results(): void {
		$language = $this->add_language();
		$post     = $this->create_two_block_page();
		wp_set_current_user( $this->create_translator() );

		$load = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/segments'
		);
		$load->set_param( 'language', 'sv' );
		$segments = rest_do_request( $load )->get_data()['segments'];

		$block_segments = array_values(
			array_filter(
				$segments,
				static fn( array $row ): bool => str_starts_with( (string) ( $row['segment_key'] ?? '' ), 'b:' )
			)
		);
		$this->assertCount( 2, $block_segments );

		usort(
			$block_segments,
			static fn( array $left, array $right ): int =>
				( (int) ( $left['segment_order'] ?? 0 ) ) <=> ( (int) ( $right['segment_order'] ?? 0 ) )
		);

		$request = $this->workspace_batch_save_request(
			(int) $post->ID,
			array(
				array(
					'segment_key'     => $block_segments[0]['segment_key'],
					'translated_text' => 'First saved',
					'source_hash'     => $block_segments[0]['source_hash'],
					'status'          => Store::STATUS_MANUALLY_EDITED,
				),
				array(
					'segment_key'     => $block_segments[1]['segment_key'],
					'translated_text' => 'Stale attempt',
					'source_hash'     => 'deadbeef',
					'status'          => Store::STATUS_MANUALLY_EDITED,
				),
			)
		);

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'partial', $data['status'] );
		$this->assertCount( 1, $data['segments'] );
		$this->assertSame( 'First saved', $data['segments'][0]['translated_text'] );
		$this->assertCount( 1, $data['errors'] );
		$this->assertSame( 'aiml_source_hash_mismatch', $data['errors'][0]['code'] );
		$this->assertArrayHasKey( 'segments', $data['errors'][0] );
	}
}
