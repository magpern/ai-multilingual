<?php
/**
 * Workspace optimistic locking tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\Store;
use WP_REST_Request;

/**
 * HTTP 409 conflict handling for workspace saves.
 */
final class WorkspaceConflictTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_stale_source_hash_returns_409_with_refreshed_segment(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$load = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/segments'
		);
		$load->set_param( 'language', 'sv' );
		$segment = $this->first_block_segment( rest_do_request( $load )->get_data()['segments'] );

		$save = $this->workspace_save_request(
			(int) $post->ID,
			$segment,
			array(
				'translated_text' => 'Stale attempt',
				'source_hash'     => 'deadbeef',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$response = rest_do_request( $save );
		$this->assertSame( 409, $response->get_status() );
		$this->assertArrayHasKey( 'segments', $response->get_data() );
		$this->assertNotSame( 'deadbeef', $response->get_data()['segments'][0]['source_hash'] );
	}
}
