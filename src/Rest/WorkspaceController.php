<?php
/**
 * REST transport layer for the translator workspace.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rest;

use AIMultilingual\Plugin;
use AIMultilingual\Rest\ViewModel\WorkspacePageSummarySerializer;
use AIMultilingual\Rest\ViewModel\WorkspaceSegmentSerializer;
use AIMultilingual\Rest\ViewModel\WorkspaceTranslationStatusSerializer;
use AIMultilingual\Workspace\WorkspaceConflictException;
use AIMultilingual\Workspace\WorkspaceService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Thin REST controller — validates, authorizes, delegates, serializes.
 */
final class WorkspaceController {

	public const REST_NAMESPACE = 'aiml/v1';

	public const REST_BASE = 'workspace';

	/**
	 * Application facade.
	 *
	 * @var WorkspaceService
	 */
	private WorkspaceService $workspace;

	/**
	 * Segment row serializer.
	 *
	 * @var WorkspaceSegmentSerializer
	 */
	private WorkspaceSegmentSerializer $segment_serializer;

	/**
	 * Page list serializer.
	 *
	 * @var WorkspacePageSummarySerializer
	 */
	private WorkspacePageSummarySerializer $page_serializer;

	/**
	 * Page status serializer.
	 *
	 * @var WorkspaceTranslationStatusSerializer
	 */
	private WorkspaceTranslationStatusSerializer $status_serializer;

	/**
	 * Builds the workspace REST controller.
	 *
	 * @param WorkspaceService                     $workspace          Application facade.
	 * @param WorkspaceSegmentSerializer           $segment_serializer Segment serializer.
	 * @param WorkspacePageSummarySerializer       $page_serializer    Page serializer.
	 * @param WorkspaceTranslationStatusSerializer $status_serializer  Status serializer.
	 */
	public function __construct(
		WorkspaceService $workspace,
		WorkspaceSegmentSerializer $segment_serializer,
		WorkspacePageSummarySerializer $page_serializer,
		WorkspaceTranslationStatusSerializer $status_serializer
	) {
		$this->workspace          = $workspace;
		$this->segment_serializer = $segment_serializer;
		$this->page_serializer    = $page_serializer;
		$this->status_serializer  = $status_serializer;
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
	 * Registers workspace routes under aiml/v1.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/posts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_posts' ),
				'permission_callback' => array( $this, 'can_translate' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<post_id>\d+)/segments',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_segments' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
				'args'                => array(
					'post_id'  => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'language' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<post_id>\d+)/preview-url',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_preview_url' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
				'args'                => array(
					'post_id'  => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'language' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<post_id>\d+)/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
				'args'                => array(
					'post_id'  => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'language' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<post_id>\d+)/segments/batch',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_batch' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
				'args'                => array(
					'post_id'  => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'language' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<post_id>\d+)/segments/(?P<segment_key>.+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_segment' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
				'args'                => array(
					'post_id'     => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'segment_key' => array(
						'type'     => 'string',
						'required' => true,
					),
					'language'    => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<post_id>\d+)/translate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'translate' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
				'args'                => array(
					'post_id'  => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'language' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Lists posts for the workspace picker.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_posts( WP_REST_Request $request ) {
		$result = $this->workspace->list_pages(
			array(
				'search'   => (string) $request->get_param( 'search' ),
				'page'     => (int) $request->get_param( 'page' ),
				'per_page' => (int) $request->get_param( 'per_page' ),
				'language' => (string) $request->get_param( 'language' ),
			)
		);

		return $this->respond(
			array(
				'items'       => $this->page_serializer->many_to_arrays( $result['items'] ),
				'page'        => $result['page'],
				'per_page'    => $result['per_page'],
				'total'       => $result['total'],
				'total_pages' => $result['total_pages'],
			)
		);
	}

	/**
	 * Returns workspace segments for one post.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_segments( WP_REST_Request $request ) {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$language = $this->resolve_language_param( $request );
		if ( $language instanceof WP_Error ) {
			return $language;
		}

		$segments = $this->workspace->load_segments( $post, (int) $language->language_id );

		return $this->respond(
			array(
				'post_id'     => (int) $post->ID,
				'language_id' => (int) $language->language_id,
				'segments'    => $this->segment_serializer->many_to_arrays( $segments ),
				'status'      => $this->status_serializer->from_dto(
					$this->workspace->page_status_for_segments(
						$post,
						(int) $language->language_id,
						$segments
					)
				)->to_array(),
			)
		);
	}

	/**
	 * Returns page-level translation status.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_status( WP_REST_Request $request ) {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$language = $this->resolve_language_param( $request );
		if ( $language instanceof WP_Error ) {
			return $language;
		}

		return $this->respond(
			$this->status_serializer->from_dto(
				$this->workspace->page_status( $post, (int) $language->language_id )
			)->to_array()
		);
	}

	/**
	 * Returns the production preview URL.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_preview_url( WP_REST_Request $request ) {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$language_code = sanitize_key( (string) $request->get_param( 'language' ) );
		$url           = $this->workspace->preview_url( $post, $language_code );
		if ( $url instanceof WP_Error ) {
			return $url;
		}

		return $this->respond(
			array(
				'url' => $url,
			)
		);
	}

	/**
	 * Saves one workspace segment.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_segment( WP_REST_Request $request ) {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$language = $this->resolve_language_param( $request );
		if ( $language instanceof WP_Error ) {
			return $language;
		}

		$params = (array) $request->get_json_params();
		if ( array() === $params ) {
			$params = (array) $request->get_body_params();
		}
		$key    = rawurldecode( (string) $request->get_param( 'segment_key' ) );

		try {
			$dto = $this->workspace->save_segment(
				$post,
				(int) $language->language_id,
				$key,
				(string) ( $params['translated_text'] ?? '' ),
				(string) ( $params['source_hash'] ?? '' ),
				(string) ( $params['status'] ?? '' )
			);
		} catch ( WorkspaceConflictException $conflict ) {
			return new WP_REST_Response(
				array(
					'code'     => 'aiml_source_hash_mismatch',
					'message'  => $conflict->getMessage(),
					'segments' => $this->segment_serializer->many_to_arrays( $conflict->segments() ),
				),
				409
			);
		} catch ( \InvalidArgumentException $exception ) {
			return new WP_Error(
				'aiml_invalid_segment',
				$exception->getMessage(),
				array( 'status' => 422 )
			);
		}

		return $this->respond( $this->segment_serializer->from_dto( $dto )->to_array() );
	}

	/**
	 * Saves multiple workspace segments.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_batch( WP_REST_Request $request ) {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$language = $this->resolve_language_param( $request );
		if ( $language instanceof WP_Error ) {
			return $language;
		}

		$params = (array) $request->get_json_params();
		if ( array() === $params ) {
			$params = (array) $request->get_body_params();
		}
		$items  = is_array( $params['segments'] ?? null ) ? $params['segments'] : array();

		$result = $this->workspace->save_batch( $post, (int) $language->language_id, $items );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		return $this->respond(
			array(
				'status'   => $result['status'],
				'segments' => $this->segment_serializer->many_to_arrays( $result['segments'] ),
				'errors'   => $result['errors'],
			)
		);
	}

	/**
	 * Invokes auto-translate for selected segments.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function translate( WP_REST_Request $request ) {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$language = $this->resolve_language_param( $request );
		if ( $language instanceof WP_Error ) {
			return $language;
		}

		$params = (array) $request->get_json_params();
		if ( array() === $params ) {
			$params = (array) $request->get_body_params();
		}
		$keys   = is_array( $params['segment_keys'] ?? null ) ? array_map( 'strval', $params['segment_keys'] ) : array();
		$mode   = (string) ( $params['mode'] ?? 'sync' );

		$result = $this->workspace->translate( $post, (int) $language->language_id, $keys, $mode );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		return $this->respond(
			array(
				'status'   => $result['status'],
				'job_id'   => $result['job_id'] ?? null,
				'segments' => $this->segment_serializer->many_to_arrays( $result['segments'] ),
				'errors'   => $result['errors'],
			)
		);
	}

	/**
	 * Wraps a ViewModel payload in a versioned REST response.
	 *
	 * @param array<string, mixed> $payload Response payload.
	 * @return WP_REST_Response
	 */
	private function respond( array $payload ): WP_REST_Response {
		$response = new WP_REST_Response( $payload, 200 );
		$response->header( 'X-AIML-Workspace-Api-Version', '1' );

		return $response;
	}

	/**
	 * Checks the workspace capability.
	 *
	 * @return bool|WP_Error
	 */
	public function can_translate() {
		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			return new WP_Error(
				'aiml_forbidden',
				__( 'You do not have permission to access the translator workspace.', 'ai-multilingual' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Checks workspace and edit_post capabilities.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|WP_Error
	 */
	public function can_edit_post( WP_REST_Request $request ) {
		$allowed = $this->can_translate();
		if ( true !== $allowed ) {
			return $allowed;
		}

		$post_id = (int) $request->get_param( 'post_id' );
		if ( $post_id <= 0 ) {
			return new WP_Error(
				'aiml_invalid_post',
				__( 'Invalid post id.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'aiml_forbidden',
				__( 'You do not have permission to edit this post.', 'ai-multilingual' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Resolves the target post for a REST request.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return \WP_Post|WP_Error
	 */
	private function resolve_post( WP_REST_Request $request ) {
		return $this->workspace->get_post( (int) $request->get_param( 'post_id' ) );
	}

	/**
	 * Resolves the target language for a REST request.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return object|WP_Error
	 */
	private function resolve_language_param( WP_REST_Request $request ) {
		$code     = sanitize_key( (string) $request->get_param( 'language' ) );
		$language = $this->workspace->resolve_language( $code );
		if ( null === $language ) {
			return new WP_Error(
				'aiml_invalid_language',
				__( 'Unknown language code.', 'ai-multilingual' ),
				array( 'status' => 404 )
			);
		}

		return $language;
	}
}
