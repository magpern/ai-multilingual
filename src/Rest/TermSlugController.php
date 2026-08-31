<?php
/**
 * Thin REST seam for term localized-slug operator lifecycle (P0).
 *
 * Delegates to SlugCandidateService + RoutePublicationService only.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rest;

use AIMultilingual\Language\Languages;
use AIMultilingual\Plugin;
use AIMultilingual\Routing\RoutePublicationService;
use AIMultilingual\Routing\SlugCandidateService;
use AIMultilingual\Surface\AdmittedTaxonomies;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_Term;

/**
 * Administrator REST for admitted term archive slug routes.
 */
final class TermSlugController {

	public const REST_NAMESPACE = 'aiml/v1';

	public const REST_BASE = 'workspace/terms';

	/**
	 * Constructs the thin term-slug REST seam.
	 *
	 * @param SlugCandidateService    $candidates Slug candidates.
	 * @param RoutePublicationService $routes     Route publication.
	 * @param Languages               $languages  Languages.
	 */
	public function __construct(
		private SlugCandidateService $candidates,
		private RoutePublicationService $routes,
		private Languages $languages
	) {
	}

	/**
	 * Registers routes.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers term slug REST routes.
	 */
	public function register_routes(): void {
		$term_args = array(
			'term_id'  => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
			'language' => array(
				'type'     => 'string',
				'required' => true,
			),
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<term_id>\d+)/slug/generate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => array( $this, 'can_edit_term' ),
				'args'                => $term_args,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<term_id>\d+)/slug',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'view' ),
					'permission_callback' => array( $this, 'can_edit_term' ),
					'args'                => $term_args,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'can_edit_term' ),
					'args'                => $term_args,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'clear' ),
					'permission_callback' => array( $this, 'can_edit_term' ),
					'args'                => $term_args,
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<term_id>\d+)/slug/publish-route',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'publish' ),
				'permission_callback' => array( $this, 'can_edit_term' ),
				'args'                => $term_args,
			)
		);
	}

	/**
	 * Permission: translate + edit this term.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function can_edit_term( WP_REST_Request $request ) {
		if ( ! current_user_can( Plugin::CAP_TRANSLATE ) ) {
			return new WP_Error( 'aiml_forbidden', __( 'You cannot manage translations.', 'universal-multilingual' ), array( 'status' => 403 ) );
		}

		$term = $this->resolve_term( $request );
		if ( $term instanceof WP_Error ) {
			return $term;
		}

		if ( ! current_user_can( 'edit_term', (int) $term->term_id ) ) {
			return new WP_Error( 'aiml_forbidden', __( 'You cannot edit this term.', 'universal-multilingual' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * GET sync view.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function view( WP_REST_Request $request ) {
		$term = $this->resolve_term( $request );
		if ( $term instanceof WP_Error ) {
			return $term;
		}
		$language = $this->resolve_language( $request );
		if ( $language instanceof WP_Error ) {
			return $language;
		}

		return new WP_REST_Response( $this->routes->sync_term_view( $term, (int) $language->language_id ), 200 );
	}

	/**
	 * POST generate candidate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate( WP_REST_Request $request ) {
		$term = $this->resolve_term( $request );
		if ( $term instanceof WP_Error ) {
			return $term;
		}
		$language = $this->resolve_language( $request );
		if ( $language instanceof WP_Error ) {
			return $language;
		}

		$row = $this->candidates->generate_for_term( $term, (int) $language->language_id );
		if ( $row instanceof WP_Error ) {
			return $row;
		}

		return new WP_REST_Response( $this->routes->sync_term_view( $term, (int) $language->language_id ), 200 );
	}

	/**
	 * POST save manual candidate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		$term = $this->resolve_term( $request );
		if ( $term instanceof WP_Error ) {
			return $term;
		}
		$language = $this->resolve_language( $request );
		if ( $language instanceof WP_Error ) {
			return $language;
		}

		$params    = $request->get_json_params();
		$params    = is_array( $params ) ? $params : array();
		$candidate = (string) ( $params['slug_candidate'] ?? $params['translated_text'] ?? '' );

		$row = $this->candidates->save_manual_for_term( $term, (int) $language->language_id, $candidate );
		if ( $row instanceof WP_Error ) {
			return $row;
		}

		return new WP_REST_Response( $this->routes->sync_term_view( $term, (int) $language->language_id ), 200 );
	}

	/**
	 * DELETE clear candidate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function clear( WP_REST_Request $request ) {
		$term = $this->resolve_term( $request );
		if ( $term instanceof WP_Error ) {
			return $term;
		}
		$language = $this->resolve_language( $request );
		if ( $language instanceof WP_Error ) {
			return $language;
		}

		$row = $this->candidates->clear_for_term( $term, (int) $language->language_id );
		if ( $row instanceof WP_Error ) {
			return $row;
		}

		return new WP_REST_Response( $this->routes->sync_term_view( $term, (int) $language->language_id ), 200 );
	}

	/**
	 * POST publish route.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function publish( WP_REST_Request $request ) {
		$term = $this->resolve_term( $request );
		if ( $term instanceof WP_Error ) {
			return $term;
		}
		$language = $this->resolve_language( $request );
		if ( $language instanceof WP_Error ) {
			return $language;
		}

		$result = $this->routes->publish_term_route( $term, (int) $language->language_id, get_current_user_id() );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		$view               = $this->routes->sync_term_view( $term, (int) $language->language_id );
		$view['idempotent'] = ! empty( $result['idempotent'] );
		$view['status']     = 'published';

		return new WP_REST_Response( $view, 200 );
	}

	/**
	 * Resolves an admitted term from the request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Term|WP_Error
	 */
	private function resolve_term( WP_REST_Request $request ) {
		$term_id = (int) $request['term_id'];
		$term    = get_term( $term_id );
		if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
			return new WP_Error( 'aiml_term_not_found', __( 'Term not found.', 'universal-multilingual' ), array( 'status' => 404 ) );
		}
		if ( ! AdmittedTaxonomies::admits( (string) $term->taxonomy ) ) {
			return new WP_Error(
				'aiml_term_taxonomy_unsupported',
				__( 'This taxonomy is not supported for localized URL routes.', 'universal-multilingual' ),
				array( 'status' => 400 )
			);
		}

		return $term;
	}

	/**
	 * Resolves a language row from the request language code.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return object|WP_Error Language row.
	 */
	private function resolve_language( WP_REST_Request $request ) {
		$code = (string) $request->get_param( 'language' );
		$row  = $this->languages->find_by_code( $code );
		if ( null === $row ) {
			return new WP_Error( 'aiml_language_not_found', __( 'Language not found.', 'universal-multilingual' ), array( 'status' => 404 ) );
		}

		return $row;
	}
}
