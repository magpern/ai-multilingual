<?php
/**
 * REST transport layer for the translator workspace.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rest;

use AIMultilingual\Plugin;
use AIMultilingual\Rest\ViewModel\ReviewQueueItemSerializer;
use AIMultilingual\Rest\ViewModel\WorkspacePageSummarySerializer;
use AIMultilingual\Rest\ViewModel\WorkspaceSegmentSerializer;
use AIMultilingual\Rest\ViewModel\WorkspaceTranslationStatusSerializer;
use AIMultilingual\Workspace\Review\ReviewBatchCoordinator;
use AIMultilingual\Workspace\Review\ReviewCapabilities;
use AIMultilingual\Workspace\Review\ReviewQAUnavailableException;
use AIMultilingual\Workspace\Review\ReviewWorkflowException;
use AIMultilingual\Workspace\Review\ReviewWorkflowService;
use AIMultilingual\Workspace\WorkspaceConflictException;
use AIMultilingual\Workspace\WorkspaceQAException;
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
	 * Review queue row serializer.
	 *
	 * @var ReviewQueueItemSerializer
	 */
	private ReviewQueueItemSerializer $review_queue_serializer;

	/**
	 * Builds the workspace REST controller.
	 *
	 * @param WorkspaceService                     $workspace                Application facade.
	 * @param WorkspaceSegmentSerializer           $segment_serializer       Segment serializer.
	 * @param WorkspacePageSummarySerializer       $page_serializer          Page serializer.
	 * @param WorkspaceTranslationStatusSerializer $status_serializer        Status serializer.
	 * @param ReviewQueueItemSerializer            $review_queue_serializer  Review queue serializer.
	 */
	public function __construct(
		WorkspaceService $workspace,
		WorkspaceSegmentSerializer $segment_serializer,
		WorkspacePageSummarySerializer $page_serializer,
		WorkspaceTranslationStatusSerializer $status_serializer,
		ReviewQueueItemSerializer $review_queue_serializer
	) {
		$this->workspace               = $workspace;
		$this->segment_serializer      = $segment_serializer;
		$this->page_serializer         = $page_serializer;
		$this->status_serializer       = $status_serializer;
		$this->review_queue_serializer = $review_queue_serializer;
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
			'/' . self::REST_BASE . '/review-queue',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_review_queue' ),
				'permission_callback' => array( $this, 'can_review_queue' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/review-diagnostics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_review_diagnostics' ),
				'permission_callback' => array( $this, 'can_review_queue' ),
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
			'/' . self::REST_BASE . '/(?P<post_id>\d+)/segments/(?P<segment_key>.+)/suggest',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'suggest_segment' ),
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
					'profile'     => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<post_id>\d+)/segments/batch-review',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'batch_review' ),
				'permission_callback' => array( $this, 'can_batch_review' ),
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
			'/' . self::REST_BASE . '/(?P<post_id>\d+)/segments/(?P<segment_key>.+)/submit-review',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit_review' ),
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
			'/' . self::REST_BASE . '/(?P<post_id>\d+)/segments/(?P<segment_key>.+)/approve',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'approve_review' ),
				'permission_callback' => array( $this, 'can_review' ),
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
			'/' . self::REST_BASE . '/(?P<post_id>\d+)/segments/(?P<segment_key>.+)/reject',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reject_review' ),
				'permission_callback' => array( $this, 'can_review' ),
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
			'/' . self::REST_BASE . '/(?P<post_id>\d+)/suggestions/accept',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'accept_suggestions' ),
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
			'/' . self::REST_BASE . '/(?P<post_id>\d+)/qa',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'run_qa_batch' ),
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
		$key = rawurldecode( (string) $request->get_param( 'segment_key' ) );

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
		} catch ( WorkspaceQAException $qa_exception ) {
			return new WP_REST_Response(
				array(
					'code'    => 'aiml_qa_blocked',
					'message' => $qa_exception->getMessage(),
					'qa'      => $qa_exception->qa()->to_array(),
				),
				422
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
	 * Requests AI/TM suggestions for one segment without persisting.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function suggest_segment( WP_REST_Request $request ) {
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

		$key     = rawurldecode( (string) $request->get_param( 'segment_key' ) );
		$profile = sanitize_key( (string) ( $params['profile'] ?? $request->get_param( 'profile' ) ?? '' ) );

		try {
			$dto = $this->workspace->request_suggestions(
				$post,
				(int) $language->language_id,
				$key,
				$profile
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
	 * Accepts exact TM suggestions for selected segments.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function accept_suggestions( WP_REST_Request $request ) {
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
		$keys = is_array( $params['segment_keys'] ?? null ) ? $params['segment_keys'] : array();

		$result = $this->workspace->accept_tm_exact_batch(
			$post,
			(int) $language->language_id,
			array_map( 'strval', $keys )
		);
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
	 * Runs read-only batch QA for selected segments.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function run_qa_batch( WP_REST_Request $request ) {
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
		$keys = is_array( $params['segment_keys'] ?? null ) ? $params['segment_keys'] : array();

		$result = $this->workspace->qa_batch(
			$post,
			(int) $language->language_id,
			array_map( 'strval', $keys )
		);

		return $this->respond(
			array(
				'segments' => $this->segment_serializer->many_to_arrays( $result['segments'] ),
				'summary'  => $result['summary'],
			)
		);
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
		$items = is_array( $params['segments'] ?? null ) ? $params['segments'] : array();

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
	 * Submits (or resubmits) one segment for review.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_review( WP_REST_Request $request ) {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$language = $this->resolve_language_param( $request );
		if ( $language instanceof WP_Error ) {
			return $language;
		}

		$params = $this->body_params( $request );
		$key    = rawurldecode( (string) $request->get_param( 'segment_key' ) );

		try {
			$dto = $this->workspace->submit_review(
				$post,
				(int) $language->language_id,
				$key,
				get_current_user_id(),
				$this->nullable_string( $params['expected_review_status'] ?? null )
			);
		} catch ( ReviewWorkflowException $exception ) {
			return $this->review_error_response( $exception );
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
	 * Approves one pending review.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve_review( WP_REST_Request $request ) {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$language = $this->resolve_language_param( $request );
		if ( $language instanceof WP_Error ) {
			return $language;
		}

		$params = $this->body_params( $request );
		$key    = rawurldecode( (string) $request->get_param( 'segment_key' ) );

		try {
			$dto = $this->workspace->approve_review(
				$post,
				(int) $language->language_id,
				$key,
				get_current_user_id(),
				$this->nullable_string( $params['expected_review_status'] ?? null ),
				$this->nullable_string( $params['submitted_translation_hash'] ?? null )
			);
		} catch ( ReviewWorkflowException $exception ) {
			return $this->review_error_response( $exception );
		} catch ( WorkspaceQAException $qa_exception ) {
			return new WP_REST_Response(
				array(
					'code'    => 'aiml_qa_blocked',
					'message' => $qa_exception->getMessage(),
					'qa'      => $qa_exception->qa()->to_array(),
				),
				422
			);
		} catch ( ReviewQAUnavailableException $exception ) {
			return new WP_Error(
				'aiml_review_qa_unavailable',
				$exception->getMessage(),
				array( 'status' => 503 )
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
	 * Rejects one pending review with a required reason.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reject_review( WP_REST_Request $request ) {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$language = $this->resolve_language_param( $request );
		if ( $language instanceof WP_Error ) {
			return $language;
		}

		$params = $this->body_params( $request );
		$key    = rawurldecode( (string) $request->get_param( 'segment_key' ) );

		try {
			$dto = $this->workspace->reject_review(
				$post,
				(int) $language->language_id,
				$key,
				get_current_user_id(),
				(string) ( $params['reason'] ?? '' ),
				$this->nullable_string( $params['expected_review_status'] ?? null ),
				$this->nullable_string( $params['submitted_translation_hash'] ?? null )
			);
		} catch ( ReviewWorkflowException $exception ) {
			return $this->review_error_response( $exception );
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
	 * Applies one review action to multiple segments (bounded, partial success).
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function batch_review( WP_REST_Request $request ) {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$language = $this->resolve_language_param( $request );
		if ( $language instanceof WP_Error ) {
			return $language;
		}

		$params = $this->body_params( $request );
		$action = sanitize_key( (string) ( $params['action'] ?? '' ) );
		$items  = is_array( $params['segments'] ?? null ) ? $params['segments'] : array();
		$reason = (string) ( $params['reason'] ?? '' );

		$result = $this->workspace->batch_review(
			$post,
			(int) $language->language_id,
			$action,
			$items,
			get_current_user_id(),
			$reason
		);
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
	 * Returns a filtered, paginated review queue (Store view; never persisted).
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_review_queue( WP_REST_Request $request ) {
		$args = array(
			'post_id'  => (int) $request->get_param( 'post_id' ),
			'language' => sanitize_key( (string) ( $request->get_param( 'language' ) ?? '' ) ),
			'page'     => (int) $request->get_param( 'page' ),
			'per_page' => (int) $request->get_param( 'per_page' ),
		);

		$review_status = $request->get_param( 'review_status' );
		if ( null !== $review_status && '' !== $review_status ) {
			$args['review_status'] = sanitize_key( (string) $review_status );
		}

		$result = $this->workspace->review_queue( $args );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		return $this->respond(
			array(
				'items'    => $this->review_queue_serializer->many_to_arrays( $result['items'] ),
				'total'    => $result['total'],
				'page'     => $result['page'],
				'per_page' => $result['per_page'],
			)
		);
	}

	/**
	 * Returns bounded, low-cardinality Review Workflow diagnostics
	 * (ADR-0015 §13). Never translation content — counts and timings only.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function get_review_diagnostics( WP_REST_Request $request ): WP_REST_Response {
		$args = array(
			'post_id'  => (int) $request->get_param( 'post_id' ),
			'language' => sanitize_key( (string) ( $request->get_param( 'language' ) ?? '' ) ),
		);

		return $this->respond( $this->workspace->review_diagnostics( $args ) );
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
		$keys = is_array( $params['segment_keys'] ?? null ) ? array_map( 'strval', $params['segment_keys'] ) : array();
		$mode = (string) ( $params['mode'] ?? 'sync' );

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
	 * Checks the review capability and edit_post for the target post.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|WP_Error
	 */
	public function can_review( WP_REST_Request $request ) {
		if ( ! current_user_can( ReviewCapabilities::REVIEW_TRANSLATIONS ) ) {
			return new WP_Error(
				'aiml_forbidden',
				__( 'You do not have permission to review translations.', 'ai-multilingual' ),
				array( 'status' => 403 )
			);
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
	 * Checks the review capability only, for the global (post-agnostic) queue.
	 *
	 * @return bool|WP_Error
	 */
	public function can_review_queue() {
		if ( current_user_can( ReviewCapabilities::REVIEW_TRANSLATIONS ) ) {
			return true;
		}

		return new WP_Error(
			'aiml_forbidden',
			__( 'You do not have permission to view the review queue.', 'ai-multilingual' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Checks the capability matching the batch's declared action.
	 *
	 * One `action` applies to the whole batch, so the required capability
	 * (translate for submit, review for approve/reject) is resolved once here
	 * rather than per item — mirroring how `can_edit_post()` already gates
	 * `segments/batch` as a single request.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|WP_Error
	 */
	public function can_batch_review( WP_REST_Request $request ) {
		$params = $this->body_params( $request );
		$action = sanitize_key( (string) ( $params['action'] ?? '' ) );

		if ( ReviewBatchCoordinator::ACTION_SUBMIT === $action ) {
			return $this->can_edit_post( $request );
		}

		if ( in_array( $action, array( ReviewBatchCoordinator::ACTION_APPROVE, ReviewBatchCoordinator::ACTION_REJECT ), true ) ) {
			return $this->can_review( $request );
		}

		return new WP_Error(
			'aiml_invalid_review_action',
			__( 'Unknown batch review action.', 'ai-multilingual' ),
			array( 'status' => 422 )
		);
	}

	/**
	 * Maps a domain review exception to a stable REST error response.
	 *
	 * @param ReviewWorkflowException $exception Domain exception.
	 * @return WP_REST_Response
	 */
	private function review_error_response( ReviewWorkflowException $exception ): WP_REST_Response {
		$status = $exception->getCode();
		if ( $status <= 0 ) {
			$status = $this->map_review_error_status( $exception->get_error_code() );
		}

		return new WP_REST_Response(
			array(
				'code'    => $exception->get_error_code(),
				'message' => $exception->getMessage(),
				'context' => $exception->get_context(),
			),
			$status
		);
	}

	/**
	 * Maps a stable review error code to its documented HTTP status
	 * (ADR-0015 §4.2) when the exception did not carry one explicitly.
	 *
	 * @param string $error_code Stable error code.
	 */
	private function map_review_error_status( string $error_code ): int {
		switch ( $error_code ) {
			case ReviewWorkflowService::CODE_CONFLICT:
				return 409;
			case ReviewWorkflowService::CODE_NO_TRANSLATION:
				return 404;
			case ReviewWorkflowService::CODE_PERMISSION_DENIED:
				return 403;
			case ReviewWorkflowService::CODE_NOT_PENDING:
			case ReviewWorkflowService::CODE_ALREADY_PENDING:
			case ReviewWorkflowService::CODE_TRANSLATION_CHANGED:
			case ReviewWorkflowService::CODE_REASON_REQUIRED:
			case ReviewWorkflowService::CODE_INVALID_TRANSITION:
			case ReviewWorkflowService::CODE_INVALID_TRANSLATION:
			case ReviewWorkflowService::CODE_QA_BLOCKED:
				return 422;
			default:
				return 500;
		}
	}

	/**
	 * Reads JSON body params, falling back to form-encoded body params.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string, mixed>
	 */
	private function body_params( WP_REST_Request $request ): array {
		$params = (array) $request->get_json_params();
		if ( array() === $params ) {
			$params = (array) $request->get_body_params();
		}

		return $params;
	}

	/**
	 * Normalizes an optional client-supplied string, treating '' as absent.
	 *
	 * @param mixed $value Raw request value.
	 */
	private function nullable_string( $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}

		return (string) $value;
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
