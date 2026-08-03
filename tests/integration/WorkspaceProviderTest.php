<?php
/**
 * AI provider boundary integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Rest\WorkspaceController;
use AIMultilingual\Translation\AI\NullAIProvider;
use AIMultilingual\Translation\AI\ProviderSegment;
use AIMultilingual\Translation\AI\TranslationBatch;
use ReflectionClass;
use WP_REST_Request;

/**
 * Provider injection and REST delegation for workspace auto-translate.
 */
final class WorkspaceProviderTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_null_provider_returns_stable_not_configured_code(): void {
		$provider = new NullAIProvider();
		$result   = $provider->translate_batch(
			new TranslationBatch(
				'en_US',
				'sv_SE',
				'workspace',
				'1',
				'',
				array(
					new ProviderSegment(
						'b:test:content',
						'Hello',
						'html'
					),
				)
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( NullAIProvider::ERROR_CODE, $result->get_error_code() );
	}

	public function test_workspace_controller_does_not_reference_provider_types(): void {
		$source = (string) file_get_contents(
			( new ReflectionClass( WorkspaceController::class ) )->getFileName()
		);

		$this->assertStringNotContainsString( 'AIProviderInterface', $source );
		$this->assertStringNotContainsString( 'NullAIProvider', $source );
	}

	public function test_translate_rest_returns_provider_not_configured(): void {
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
		$this->assertSame(
			NullAIProvider::ERROR_CODE,
			$response->get_data()['errors'][0]['code'] ?? ''
		);
	}
}
