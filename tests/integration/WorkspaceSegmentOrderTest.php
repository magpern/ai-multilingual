<?php
/**
 * Workspace segment ordering tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\Contract;
use WP_REST_Request;

/**
 * BlockTreeWalker order preserved in workspace responses.
 */
final class WorkspaceSegmentOrderTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_segments_return_in_document_order(): void {
		$this->add_language();
		$post = $this->create_page(
			'Order',
			sprintf(
				'<!-- wp:heading {"%1$s":"11111111-1111-4111-8111-111111111111"} --><h2>First</h2><!-- /wp:heading -->'
				. '<!-- wp:paragraph {"%1$s":"22222222-2222-4222-8222-222222222222"} --><p>Second</p><!-- /wp:paragraph -->'
				. '<!-- wp:button {"%1$s":"33333333-3333-4333-8333-333333333333"} --><div class="wp-block-button"><a class="wp-block-button__link">Third</a></div><!-- /wp:button -->',
				Contract::ATTR_NAME
			)
		);

		wp_set_current_user( $this->create_translator() );

		$request = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/segments'
		);
		$request->set_param( 'language', 'sv' );

		$response = rest_do_request( $request );
		$segments = array_values(
			array_filter(
				$response->get_data()['segments'],
				static fn( array $row ): bool => str_starts_with( (string) ( $row['segment_key'] ?? '' ), 'b:' )
			)
		);
		$orders   = array_column( $segments, 'segment_order' );
		$sorted   = $orders;
		sort( $sorted, SORT_NUMERIC );

		$this->assertSame( $sorted, $orders );
		$this->assertSame( array( 0, 1, 2 ), $orders );
	}
}
