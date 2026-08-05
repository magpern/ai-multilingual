<?php
/**
 * Glossary lexicon REST API (ADR-0014).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Rest;

use AIMultilingual\Glossary\GlossaryAuditEvents;
use AIMultilingual\Glossary\GlossaryAuditLogger;
use AIMultilingual\Glossary\GlossaryCapabilities;
use AIMultilingual\Glossary\GlossaryService;
use AIMultilingual\Rest\ViewModel\GlossaryTermSerializer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Operator CRUD for platform glossary terms.
 */
final class GlossaryController {

	public const NAMESPACE   = 'aiml/v1';
	public const API_HEADER  = 'X-AIML-Glossary-Api-Version';
	public const API_VERSION = '1';

	/**
	 * Glossary application service.
	 *
	 * @var GlossaryService
	 */
	private GlossaryService $glossary;

	/**
	 * Term serializer.
	 *
	 * @var GlossaryTermSerializer
	 */
	private GlossaryTermSerializer $serializer;

	/**
	 * Audit logger.
	 *
	 * @var GlossaryAuditLogger
	 */
	private GlossaryAuditLogger $audit;

	/**
	 * Builds the controller.
	 *
	 * @param GlossaryService             $glossary   Glossary service.
	 * @param GlossaryTermSerializer|null $serializer Serializer.
	 * @param GlossaryAuditLogger|null    $audit      Audit logger.
	 */
	public function __construct(
		GlossaryService $glossary,
		?GlossaryTermSerializer $serializer = null,
		?GlossaryAuditLogger $audit = null
	) {
		$this->glossary   = $glossary;
		$this->serializer = $serializer ?? new GlossaryTermSerializer();
		$this->audit      = $audit ?? new GlossaryAuditLogger();
	}

	/**
	 * Registers REST routes.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		if ( did_action( 'rest_api_init' ) ) {
			$this->register_routes();
		}
	}

	/**
	 * Route registration callback.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/glossary',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_terms' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_term' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/glossary/diagnostics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'diagnostics' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/glossary/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_term' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => array( 'PUT', 'PATCH' ),
					'callback'            => array( $this, 'update_term' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_term' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/glossary/(?P<id>\d+)/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'activate_term' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/glossary/(?P<id>\d+)/deactivate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'deactivate_term' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Whether the current user may manage the glossary.
	 *
	 * @return true|WP_Error
	 */
	public function can_manage() {
		if ( current_user_can( GlossaryCapabilities::MANAGE_GLOSSARY ) ) {
			return true;
		}

		return new WP_Error(
			'aiml_forbidden',
			__( 'You do not have permission to manage the glossary.', 'ai-multilingual' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Lists glossary terms.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_terms( WP_REST_Request $request ): WP_REST_Response {
		$per_page_param = $request->get_param( 'per_page' );
		$args           = array(
			'page'     => max( 1, (int) $request->get_param( 'page' ) ),
			'per_page' => max( 1, min( 100, (int) ( null !== $per_page_param && '' !== $per_page_param ? $per_page_param : 20 ) ) ),
		);

		$source = $request->get_param( 'source_lang_id' );
		if ( null !== $source && '' !== $source ) {
			$args['source_lang_id'] = (int) $source;
		}
		$target = $request->get_param( 'target_lang_id' );
		if ( null !== $target && '' !== $target ) {
			$args['target_lang_id'] = (int) $target;
		}
		if ( null !== $request->get_param( 'active' ) && '' !== $request->get_param( 'active' ) ) {
			$args['is_active'] = filter_var( $request->get_param( 'active' ), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		}
		$q = (string) ( $request->get_param( 'q' ) ?? '' );
		if ( '' !== $q ) {
			$args['q'] = $q;
		}

		$result = $this->glossary->query( $args );

		return $this->respond(
			array(
				'items'    => $this->serializer->many_to_arrays( $result['items'] ),
				'total'    => $result['total'],
				'page'     => $args['page'],
				'per_page' => $args['per_page'],
				'version'  => $this->glossary->current_version(),
			)
		);
	}

	/**
	 * Reads one glossary term.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_term( WP_REST_Request $request ) {
		$row = $this->glossary->find( (int) $request['id'] );
		if ( null === $row ) {
			return new WP_Error( 'glossary_not_found', 'Glossary term not found.', array( 'status' => 404 ) );
		}

		return $this->respond( $this->serializer->from_row( $row )->to_array() );
	}

	/**
	 * Creates a glossary term.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_term( WP_REST_Request $request ) {
		$result = $this->glossary->create( $this->body_params( $request ) );
		if ( $result instanceof WP_Error ) {
			return $this->map_error( $result, 422 );
		}

		$this->audit->log(
			GlossaryAuditEvents::TERM_CREATED,
			$this->audit_payload( $result, 'rest' )
		);

		return $this->respond( $this->serializer->from_row( $result )->to_array(), 201 );
	}

	/**
	 * Updates a glossary term.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_term( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$before = $this->glossary->find( $id );
		$result = $this->glossary->update( $id, $this->body_params( $request ) );
		if ( $result instanceof WP_Error ) {
			$status = 'glossary_not_found' === $result->get_error_code() ? 404 : 422;

			return $this->map_error( $result, $status );
		}

		$row = true === $result ? $before : $result;
		if ( null === $row ) {
			return new WP_Error( 'glossary_not_found', 'Glossary term not found.', array( 'status' => 404 ) );
		}

		$this->audit->log(
			GlossaryAuditEvents::TERM_UPDATED,
			$this->audit_payload( $row, 'rest' )
		);

		return $this->respond( $this->serializer->from_row( $row )->to_array() );
	}

	/**
	 * Activates a glossary term.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function activate_term( WP_REST_Request $request ) {
		return $this->toggle_active( (int) $request['id'], true );
	}

	/**
	 * Deactivates a glossary term.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function deactivate_term( WP_REST_Request $request ) {
		return $this->toggle_active( (int) $request['id'], false );
	}

	/**
	 * Deletes a glossary term.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_term( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$before = $this->glossary->find( $id );
		$result = $this->glossary->delete( $id );
		if ( $result instanceof WP_Error ) {
			$status = 'glossary_not_found' === $result->get_error_code() ? 404 : 500;

			return $this->map_error( $result, $status );
		}

		if ( null !== $before ) {
			$this->audit->log(
				GlossaryAuditEvents::TERM_DELETED,
				$this->audit_payload( $before, 'rest' )
			);
		}

		return $this->respond(
			array(
				'deleted'     => true,
				'glossary_id' => $id,
			)
		);
	}

	/**
	 * Returns low-cardinality glossary diagnostics.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function diagnostics( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		return $this->respond( $this->glossary->diagnostics() );
	}

	/**
	 * Activate/deactivate helper.
	 *
	 * @param int  $id     Glossary id.
	 * @param bool $active Desired state.
	 * @return WP_REST_Response|WP_Error
	 */
	private function toggle_active( int $id, bool $active ) {
		$before = $this->glossary->find( $id );
		$result = $this->glossary->set_active( $id, $active );
		if ( $result instanceof WP_Error ) {
			$status = 'glossary_not_found' === $result->get_error_code() ? 404 : 422;

			return $this->map_error( $result, $status );
		}

		$row = true === $result ? $before : $result;
		if ( null === $row ) {
			return new WP_Error( 'glossary_not_found', 'Glossary term not found.', array( 'status' => 404 ) );
		}

		$this->audit->log(
			$active ? GlossaryAuditEvents::TERM_ACTIVATED : GlossaryAuditEvents::TERM_DEACTIVATED,
			array_merge(
				$this->audit_payload( $row, 'rest' ),
				array(
					'was_active' => $before ? (bool) $before->is_active : null,
					'is_active'  => $active,
				)
			)
		);

		return $this->respond( $this->serializer->from_row( $row )->to_array() );
	}

	/**
	 * Extracts create/update body fields.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private function body_params( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		if ( ! is_array( $json ) ) {
			$json = array();
		}

		$out = array();
		foreach ( array( 'source_lang_id', 'target_lang_id', 'source_term', 'target_term', 'context', 'description', 'is_active' ) as $key ) {
			if ( array_key_exists( $key, $json ) ) {
				$out[ $key ] = $json[ $key ];
			} elseif ( null !== $request->get_param( $key ) ) {
				$out[ $key ] = $request->get_param( $key );
			}
		}

		return $out;
	}

	/**
	 * Builds a privacy-safe audit payload.
	 *
	 * @param object $row           Glossary row.
	 * @param string $source_surface rest|cli|….
	 * @return array<string, mixed>
	 */
	private function audit_payload( object $row, string $source_surface ): array {
		return array(
			'glossary_id'      => (int) $row->glossary_id,
			'source_lang_id'   => (int) $row->source_lang_id,
			'target_lang_id'   => (int) $row->target_lang_id,
			'is_active'        => (bool) $row->is_active,
			'glossary_version' => $this->glossary->current_version(),
			'user_id'          => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
			'source_surface'   => $source_surface,
		);
	}

	/**
	 * Ensures WP_Error has an HTTP status.
	 *
	 * @param WP_Error $error  Error.
	 * @param int      $status Status.
	 */
	private function map_error( WP_Error $error, int $status ): WP_Error {
		$data = $error->get_error_data();
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		if ( ! isset( $data['status'] ) ) {
			$data['status'] = $status;
			$error->add_data( $data );
		}

		return $error;
	}

	/**
	 * Versioned glossary response.
	 *
	 * @param array<string, mixed> $payload Body.
	 * @param int                  $status  HTTP status.
	 */
	private function respond( array $payload, int $status = 200 ): WP_REST_Response {
		$response = new WP_REST_Response( $payload, $status );
		$response->header( self::API_HEADER, self::API_VERSION );

		return $response;
	}
}
