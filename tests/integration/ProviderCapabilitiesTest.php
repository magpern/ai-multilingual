<?php
/**
 * Provider capabilities REST integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Plugin;
use AIMultilingual\Settings;
use AIMultilingual\Translation\AI\NullAIProvider;
use WP_REST_Request;

/**
 * F11 WP5 — active provider capabilities without vendor branching in responses.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ProviderCapabilitiesTest extends AimlTestCase {

	protected function setUp(): void {
		parent::setUp();
		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array(
					'ai_enabled'  => false,
					'ai_provider' => '',
				)
			)
		);
		Plugin::instance()->reload_settings();
		Plugin::instance()->init();
	}

	public function test_active_provider_endpoint_returns_null_capabilities(): void {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		$request  = new WP_REST_Request( 'GET', '/aiml/v1/providers/active' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( NullAIProvider::ID, $data['provider_id'] );
		$this->assertFalse( $data['capabilities']['translate'] );
		$this->assertArrayNotHasKey( 'api_key', $data );
	}

	public function test_test_connection_returns_not_configured_when_null(): void {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		$request  = new WP_REST_Request( 'POST', '/aiml/v1/providers/active/test-connection' );
		$response = rest_do_request( $request );

		$this->assertSame( 503, $response->get_status() );
		$this->assertSame( NullAIProvider::ERROR_CODE, $response->get_data()['code'] ?? '' );
	}
}
