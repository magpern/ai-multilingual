<?php
/**
 * Shared helpers for workspace integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Plugin;
use AIMultilingual\Settings;
use WP_REST_Request;

/**
 * Workspace REST test helpers.
 */
trait WorkspaceTestHelpers {

	/**
	 * Enables Strategy F extraction flags for workspace tests.
	 */
	protected function enable_strategy_f_flags(): void {
		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array(
					'block_attr_registration_enabled' => true,
					'block_uuid_injection_enabled'    => true,
					'block_extraction_enabled'        => true,
					'block_frontend_rendering_enabled' => true,
				)
			)
		);

		Plugin::instance()->init();
		Plugin::instance()->reload_settings();
		$this->cache->flush_all();
	}

	/**
	 * @return int Editor user id with aiml_translate.
	 */
	protected function create_translator(): int {
		return (int) self::factory()->user->create( array( 'role' => 'editor' ) );
	}

	/**
	 * @param string $uuid Block UUID.
	 * @return \WP_Post
	 */
	protected function create_block_page( string $uuid = '550e8400-e29b-41d4-a716-446655440000' ): \WP_Post {
		return $this->create_page(
			'Workspace blocks',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>Hello workspace</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuid
			)
		);
	}

	/**
	 * @return string Segment key for default paragraph fixture.
	 */
	protected function default_segment_key(): string {
		return SegmentKey::build( '550e8400-e29b-41d4-a716-446655440000', Contract::FIELD_CONTENT );
	}

	/**
	 * Returns the first block segment from a workspace segments response.
	 *
	 * @param array<int, array<string, mixed>> $segments Segment rows.
	 * @return array<string, mixed>
	 */
	protected function first_block_segment( array $segments ): array {
		foreach ( $segments as $segment ) {
			if ( str_starts_with( (string) ( $segment['segment_key'] ?? '' ), 'b:' ) ) {
				return $segment;
			}
		}

		$this->fail( 'Expected at least one block segment in the workspace response.' );
	}

	/**
	 * Builds a POST request for a single segment save.
	 *
	 * @param int                  $post_id     Post id.
	 * @param array<string, mixed> $segment     Segment row.
	 * @param array<string, mixed> $body        Request body.
	 * @return WP_REST_Request
	 */
	protected function workspace_save_request( int $post_id, array $segment, array $body ): WP_REST_Request {
		$key     = (string) $segment['segment_key'];
		$request = new WP_REST_Request(
			'POST',
			sprintf( '/aiml/v1/workspace/%d/segments/%s', $post_id, $key )
		);
		$request->set_url_params(
			array(
				'post_id'     => $post_id,
				'segment_key' => $key,
			)
		);
		$request->set_param( 'language', 'sv' );
		foreach ( $body as $key => $value ) {
			$request->set_param( (string) $key, $value );
		}

		return $request;
	}

	protected function tearDown(): void {
		update_option( Settings::OPTION, Settings::sanitize( Settings::defaults() ) );
		Plugin::instance()->reload_settings();
		$this->cache->flush_all();
		$this->languages->flush();

		parent::tearDown();
	}
}
