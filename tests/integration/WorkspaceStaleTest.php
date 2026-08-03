<?php
/**
 * Workspace stale detection tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use WP_REST_Request;

/**
 * sync_source stale marking via workspace load path.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WorkspaceStaleTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_load_segments_marks_stale_after_source_change(): void {
		$language = $this->add_language();
		$uuid     = '6ba7b810-9dad-41d1-80b4-00c04fd430c8';
		$key      = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
		$post     = $this->create_page(
			'Stale load',
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

		$request = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/segments'
		);
		$request->set_param( 'language', 'sv' );

		$response = rest_do_request( $request );
		$segment  = $this->segment_by_key( $response->get_data()['segments'], $key );
		$this->assertNotNull( $segment );
		$this->assertTrue( (bool) $segment['is_stale'] );
	}

	public function test_save_clears_stale_flag(): void {
		$language = $this->add_language();
		$uuid     = '7c9e6679-7425-40de-944b-e07fc1f90ae7';
		$key      = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
		$post     = $this->create_page(
			'Stale save',
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

		$load = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/segments'
		);
		$load->set_param( 'language', 'sv' );
		$segment = $this->segment_by_key(
			rest_do_request( $load )->get_data()['segments'],
			$key
		);

		$this->assertNotNull( $segment );
		$this->assertTrue( (bool) $segment['is_stale'] );

		$save = $this->workspace_save_request(
			(int) $post->ID,
			$segment,
			array(
				'translated_text' => 'Updated SV',
				'source_hash'     => $segment['source_hash'],
			)
		);

		$response = rest_do_request( $save );
		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( (bool) $response->get_data()['is_stale'] );
	}

	/**
	 * @param array<int, array<string, mixed>> $segments Segment rows.
	 * @param string                            $key      Segment key.
	 * @return array<string, mixed>|null
	 */
	private function segment_by_key( array $segments, string $key ): ?array {
		foreach ( $segments as $segment ) {
			if ( (string) ( $segment['segment_key'] ?? '' ) === $key ) {
				return $segment;
			}
		}

		return null;
	}
}
