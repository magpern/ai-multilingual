<?php
/**
 * Admin REST for AI provider connectivity (F11 WP5).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rest;

use AIMultilingual\Translation\AI\ProviderRegistry;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exposes test_connection and list_models for the active provider.
 *
 * Never returns API keys.
 */
final class ProviderController {

	public const NAMESPACE = 'aiml/v1';

	/**
	 * Provider registry.
	 *
	 * @var ProviderRegistry
	 */
	private ProviderRegistry $registry;

	/**
	 * Builds the controller.
	 *
	 * @param ProviderRegistry $registry Provider registry.
	 */
	public function __construct( ProviderRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Registers REST routes.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Route registration callback.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/providers/active',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_active' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/providers/active/test-connection',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/providers/active/models',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_models' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Whether the current user may manage provider settings.
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Returns active provider id and capabilities (no secrets).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_active( WP_REST_Request $request ) {
		unset( $request );
		$provider = $this->registry->active();

		return new WP_REST_Response(
			array(
				'provider_id'  => $provider->get_id(),
				'capabilities' => $provider->get_capabilities()->to_array(),
			),
			200
		);
	}

	/**
	 * Tests the active provider connection.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function test_connection( WP_REST_Request $request ) {
		unset( $request );
		$result = $this->registry->active()->test_connection();
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Lists models for the active provider.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_models( WP_REST_Request $request ) {
		unset( $request );
		$models = $this->registry->active()->list_models();
		if ( $models instanceof WP_Error ) {
			return $models;
		}

		return new WP_REST_Response( array( 'models' => $models ), 200 );
	}
}
