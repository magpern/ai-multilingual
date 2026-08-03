<?php
/**
 * Workspace cross-post isolation tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use WP_REST_Request;

/**
 * Ensures segments from one post never leak into another.
 */
final class WorkspaceIsolationTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_segment_keys_are_scoped_to_requested_post(): void {
		$language = $this->add_language();
		$post_a   = $this->create_block_page( 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' );
		$post_b   = $this->create_block_page( 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb' );
		wp_set_current_user( $this->create_translator() );

		$request_a = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post_a->ID . '/segments'
		);
		$request_a->set_param( 'language', 'sv' );
		$keys_a = array_column(
			array_values(
				array_filter(
					rest_do_request( $request_a )->get_data()['segments'],
					static fn( array $row ): bool => str_starts_with( (string) ( $row['segment_key'] ?? '' ), 'b:' )
				)
			),
			'segment_key'
		);

		$request_b = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post_b->ID . '/segments'
		);
		$request_b->set_param( 'language', 'sv' );
		$keys_b = array_column(
			array_values(
				array_filter(
					rest_do_request( $request_b )->get_data()['segments'],
					static fn( array $row ): bool => str_starts_with( (string) ( $row['segment_key'] ?? '' ), 'b:' )
				)
			),
			'segment_key'
		);

		$this->assertNotEmpty( $keys_a );
		$this->assertNotEmpty( $keys_b );
		$this->assertSame( array(), array_intersect( $keys_a, $keys_b ) );
	}
}
