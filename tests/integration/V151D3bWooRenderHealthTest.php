<?php
/**
 * V1.5.1 D3b — Woo render-health characterization (bounded).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Routing\SlugCandidateService;
use AIMultilingual\Routing\SlugRouteRepository;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Publication\PublicationMode;
use AIMultilingual\Translation\Store;
use RuntimeException;

/**
 * Classifies D3b relative to D1: product_cat term_link under LU ON must stay bounded.
 */
final class V151D3bWooRenderHealthTest extends AimlTestCase {

	private const SAFE_CALL_BOUND = 8;

	private Settings $settings;
	private SlugRouteRepository $routes;
	private SlugCandidateService $candidates;

	protected function setUp(): void {
		parent::setUp();

		$this->set_permalink_structure( '/%postname%/' );

		$this->settings = new Settings(
			array(
				'localized_urls_state'             => 'on',
				'segment_publication_gate_enabled' => false,
				'auto_publication_mode'            => PublicationMode::MANUAL,
			)
		);
		update_option( Settings::OPTION, $this->settings->get() );

		$this->routes     = new SlugRouteRepository();
		$this->candidates = new SlugCandidateService( $this->store );
	}

	protected function tearDown(): void {
		$this->set_permalink_structure( '' );
		remove_all_filters( 'term_link' );
		remove_all_filters( 'home_url' );
		parent::tearDown();
	}

	/**
	 * D3b disposition A evidence: same term_link re-entry class as D1 on product_cat.
	 */
	public function test_product_cat_term_link_under_lu_on_stays_bounded(): void {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			$this->markTestSkipped( 'product_cat taxonomy unavailable in this integration environment.' );
		}

		$post     = $this->create_page( 'Woo Adjacent Page' );
		$language = $this->add_language( 'sv', 'sv_SE', \AIMultilingual\Language\Languages::STATUS_PUBLISHED );
		$this->translate( $post, $language, Extractor::FIELD_TITLE, 'Woo Angransande' );
		$this->assertIsObject( $this->candidates->generate( $post, (int) $language->language_id ) );

		$publication = $this->make_route_publication();
		$this->assertIsArray( $publication->publish_route( $post, (int) $language->language_id, 1 ) );
		$route = $this->routes->find_by_object( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id );
		$this->assertNotNull( $route );

		$term_id = wp_insert_term( 'Woo Gate Cat', 'product_cat', array( 'slug' => 'woo-gate-cat' ) );
		$this->assertIsArray( $term_id );
		$term = get_term( (int) $term_id['term_id'], 'product_cat' );
		$this->assertInstanceOf( \WP_Term::class, $term );

		$router = $this->route( '/sv' . (string) $route->localized_path, $this->settings );
		$router->enable_url_prefixing();

		$calls = 0;
		remove_filter( 'term_link', array( $router, 'filter_term_link' ), 10 );
		add_filter(
			'term_link',
			static function ( $url, $term_arg, $taxonomy ) use ( $router, &$calls ) {
				++$calls;
				if ( $calls > self::SAFE_CALL_BOUND ) {
					$message = sprintf( 'aiml_v151_d3b_term_link_reentry_exceeded_safe_bound:%d', $calls );
					throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test harness abort signal.
				}

				return $router->filter_term_link( $url, $term_arg, $taxonomy );
			},
			10,
			3
		);

		$link = get_term_link( $term );
		$this->assertIsString( $link );
		$this->assertLessThanOrEqual( 3, $calls );
	}

	private function make_route_publication(): \AIMultilingual\Routing\RoutePublicationService {
		$capabilities = new \AIMultilingual\Routing\RoutingCapabilityRegistry();
		$paths        = new \AIMultilingual\Routing\PathCanonicalizer();
		$history      = new \AIMultilingual\Routing\RouteHistoryRepository();
		$collisions   = new \AIMultilingual\Routing\CanonicalPathCollisionChecker( $this->routes, $history, $paths );
		$eligibility  = new \AIMultilingual\Routing\ObjectLanguagePublicEligibility(
			$this->store,
			$this->languages,
			$capabilities,
			$this->settings,
			$this->routes
		);

		return new \AIMultilingual\Routing\RoutePublicationService(
			$this->store,
			new \AIMultilingual\Translation\Publication\PublicationService(
				$this->store,
				new \AIMultilingual\Translation\Assessment\AssessmentAssembler(),
				new \AIMultilingual\Translation\Publication\PublicationPolicy(),
				new \AIMultilingual\Translation\Publication\PublicationAuditLogger(),
				$this->settings
			),
			$this->routes,
			$history,
			$paths,
			$collisions,
			$eligibility,
			$capabilities,
			new \AIMultilingual\Routing\HierarchyPathBuilder( $this->routes, $paths )
		);
	}
}
