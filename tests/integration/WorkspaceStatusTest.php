<?php
/**
 * Workspace page status integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Rest\ViewModel\WorkspaceTranslationStatusSerializer;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use WP_REST_Request;

/**
 * WorkspaceTranslationStatusViewModel aggregation and REST serialization.
 */
final class WorkspaceStatusTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_segments_response_includes_status_view_model(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$request = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/segments'
		);
		$request->set_param( 'language', 'sv' );

		$data = rest_do_request( $request )->get_data();
		$this->assertArrayHasKey( 'status', $data );
		foreach ( array(
			'post_id',
			'post_title',
			'post_status',
			'post_type',
			'language_id',
			'total_segments',
			'missing_count',
			'stale_count',
			'translated_count',
			'reviewed_count',
			'overall_state',
			'is_published',
			'edit_link',
		) as $field ) {
			$this->assertArrayHasKey( $field, $data['status'], "Missing status field {$field}" );
		}
	}

	public function test_status_counts_match_loaded_segments(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$request = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/segments'
		);
		$request->set_param( 'language', 'sv' );
		$data     = rest_do_request( $request )->get_data();
		$segments = $data['segments'];
		$status   = $data['status'];

		$this->assertSame( count( $segments ), $status['total_segments'] );
		$this->assertSame(
			count(
				array_filter(
					$segments,
					static fn( array $row ): bool => Store::STATUS_MISSING === ( $row['status'] ?? '' )
				)
			),
			$status['missing_count']
		);
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_stale_overall_state_takes_precedence(): void {
		$language = $this->add_language();
		$uuid     = '8ba7b810-9dad-41d1-80b4-00c04fd430c8';
		$key      = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
		$post     = $this->create_page(
			'Stale status',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>Changed text</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuid
			)
		);

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => Extractor::FIELD_CONTENT,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_BLOCK,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => '<p>Original text</p>',
				'translated_text' => 'Hej',
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		wp_set_current_user( $this->create_translator() );

		$segments_request = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/segments'
		);
		$segments_request->set_param( 'language', 'sv' );
		$loaded = rest_do_request( $segments_request )->get_data();
		$this->assertSame( 'stale', $loaded['status']['overall_state'] );

		$request = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/status'
		);
		$request->set_param( 'language', 'sv' );
		$status = rest_do_request( $request )->get_data();

		$this->assertSame( 'stale', $status['overall_state'] );
		$this->assertGreaterThan( 0, $status['stale_count'] );
	}

	public function test_status_includes_wordpress_edit_link(): void {
		$this->add_language();
		$post = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$request = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/status'
		);
		$request->set_param( 'language', 'sv' );
		$status = rest_do_request( $request )->get_data();

		$this->assertNotSame( '', $status['edit_link'] );
		$this->assertStringContainsString( 'post.php', $status['edit_link'] );
	}

	public function test_complete_when_all_segments_reviewed(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$load = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/segments'
		);
		$load->set_param( 'language', 'sv' );
		$segments = rest_do_request( $load )->get_data()['segments'];

		foreach ( $segments as $segment ) {
			if ( empty( $segment['can_edit'] ) ) {
				continue;
			}

			$save = $this->workspace_save_request(
				(int) $post->ID,
				$segment,
				array(
					'translated_text' => 'Reviewed ' . $segment['segment_key'],
					'source_hash'     => $segment['source_hash'],
					'status'          => Store::STATUS_REVIEWED,
				)
			);
			rest_do_request( $save );
		}

		$status_request = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/status'
		);
		$status_request->set_param( 'language', 'sv' );
		$status = rest_do_request( $status_request )->get_data();

		$this->assertSame( 'complete', $status['overall_state'] );
		$this->assertSame( 0, $status['missing_count'] );
		$this->assertSame( $status['total_segments'], $status['reviewed_count'] );
	}

	public function test_in_progress_when_some_segments_missing(): void {
		$this->add_language();
		$post = $this->create_two_block_page();
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
				'translated_text' => 'Partial SV',
				'source_hash'     => $segment['source_hash'],
			)
		);
		rest_do_request( $save );

		$status_request = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/status'
		);
		$status_request->set_param( 'language', 'sv' );
		$status = rest_do_request( $status_request )->get_data();

		$this->assertSame( 'in_progress', $status['overall_state'] );
		$this->assertGreaterThan( 0, $status['missing_count'] );
	}

	public function test_status_serializer_exposes_view_model_fields_only(): void {
		$serializer = new WorkspaceTranslationStatusSerializer();
		$row        = $serializer->from_dto(
			array(
				'post_id'          => 1,
				'post_title'       => 'Title',
				'post_status'      => 'publish',
				'post_type'        => 'page',
				'language_id'      => 2,
				'total_segments'   => 3,
				'missing_count'    => 1,
				'stale_count'      => 0,
				'translated_count' => 1,
				'reviewed_count'   => 1,
				'overall_state'    => 'in_progress',
				'is_published'     => true,
				'edit_link'        => 'https://example.org/wp-admin/post.php?post=1&action=edit',
			)
		)->to_array();

		$this->assertSame( 'in_progress', $row['overall_state'] );
		$this->assertArrayNotHasKey( 'translation_id', $row );
	}
}
