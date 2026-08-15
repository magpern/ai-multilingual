<?php
/**
 * V1.5.1 D1 — bounded term_link re-entry characterization / regression.
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
 * Proves the defective filter_term_link ↔ get_term_link cycle without hanging CI.
 */
final class V151D1TermLinkRecursionTest extends AimlTestCase {

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
	 * Seeds a CURRENT_LOCALIZED request so term_link localization is armed.
	 *
	 * @return array{post: \WP_Post, language: object, localized: string, source: string}
	 */
	private function seed_localized_page_request(): array {
		$post     = $this->create_page( 'Gate B Style Page' );
		$language = $this->add_language( 'sv', 'sv_SE', \AIMultilingual\Language\Languages::STATUS_PUBLISHED );
		$this->translate( $post, $language, Extractor::FIELD_TITLE, 'Gate B Stil Sida' );

		$generated = $this->candidates->generate( $post, (int) $language->language_id );
		$this->assertIsObject( $generated );

		$publication = $this->make_route_publication_service();
		$result      = $publication->publish_route( $post, (int) $language->language_id, 1 );
		$this->assertIsArray( $result );

		$route = $this->routes->find_by_object( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id );
		$this->assertNotNull( $route );

		return array(
			'post'      => $post,
			'language'  => $language,
			'localized' => (string) $route->localized_path,
			'source'    => (string) $route->source_path,
		);
	}

	private function make_route_publication_service(): \AIMultilingual\Routing\RoutePublicationService {
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

	/**
	 * Installs a capped proxy around Router::filter_term_link.
	 *
	 * @param object $router Router instance with term_link registered.
	 * @return callable(): int Call-count accessor.
	 */
	private function install_capped_term_link_proxy( object $router ): callable {
		$calls = 0;
		remove_filter( 'term_link', array( $router, 'filter_term_link' ), 10 );
		add_filter(
			'term_link',
			static function ( $url, $term, $taxonomy ) use ( $router, &$calls ) {
				++$calls;
				if ( $calls > self::SAFE_CALL_BOUND ) {
					$message = sprintf( 'aiml_v151_d1_term_link_reentry_exceeded_safe_bound:%d', $calls );
					throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test harness abort signal.
				}

				return $router->filter_term_link( $url, $term, $taxonomy );
			},
			10,
			3
		);

		return static function () use ( &$calls ): int {
			return $calls;
		};
	}

	/**
	 * V151AC1/AC2 — category without stored term source_path must not re-enter unboundedly.
	 */
	public function test_term_link_without_stored_source_path_stays_within_safe_bound(): void {
		$fixture = $this->seed_localized_page_request();

		$term_id = wp_insert_term( 'Gate B Category', 'category', array( 'slug' => 'gate-b-category' ) );
		$this->assertIsArray( $term_id );
		$term = get_term( (int) $term_id['term_id'], 'category' );
		$this->assertInstanceOf( \WP_Term::class, $term );

		// No term route / no stored source_path — Gate B breadcrumb class.
		$route = $this->routes->find_by_object( Store::SOURCE_TERM, (int) $term->term_id, (int) $fixture['language']->language_id );
		$this->assertNull( $route );

		$router = $this->route( '/sv' . $fixture['localized'], $this->settings );
		$router->enable_url_prefixing();
		$count = $this->install_capped_term_link_proxy( $router );

		$link = get_term_link( $term );
		$this->assertIsString( $link );
		$this->assertLessThanOrEqual( self::SAFE_CALL_BOUND, $count() );
		$this->assertLessThanOrEqual( 3, $count(), 'Expected single-pass localization, not nested re-entry.' );
		$this->assertStringContainsString( '/sv/', $link );
	}

	/**
	 * Source-language (default) term links must remain healthy under LU ON.
	 */
	public function test_source_term_link_remains_correct_when_not_translated_context(): void {
		$term_id = wp_insert_term( 'Source Only Cat', 'category', array( 'slug' => 'source-only-cat' ) );
		$this->assertIsArray( $term_id );
		$term = get_term( (int) $term_id['term_id'], 'category' );
		$this->assertInstanceOf( \WP_Term::class, $term );

		$link = get_term_link( $term );
		$this->assertIsString( $link );
		$this->assertStringContainsString( 'source-only-cat', $link );
		$this->assertStringNotContainsString( '/sv/', $link );
	}

	/**
	 * HierarchyPathBuilder source_path_for_term under live term_link filter must not recurse.
	 */
	public function test_hierarchy_source_path_for_term_under_live_filter_is_bounded(): void {
		$fixture = $this->seed_localized_page_request();

		$term_id = wp_insert_term( 'Builder Cat', 'category', array( 'slug' => 'builder-cat' ) );
		$this->assertIsArray( $term_id );
		$term = get_term( (int) $term_id['term_id'], 'category' );
		$this->assertInstanceOf( \WP_Term::class, $term );

		$router = $this->route( '/sv' . $fixture['localized'], $this->settings );
		$router->enable_url_prefixing();
		$count = $this->install_capped_term_link_proxy( $router );

		$paths     = new \AIMultilingual\Routing\PathCanonicalizer();
		$hierarchy = new \AIMultilingual\Routing\HierarchyPathBuilder( $this->routes, $paths );
		$source    = $hierarchy->source_path_for_term( $term );

		$this->assertNotInstanceOf( \WP_Error::class, $source );
		$this->assertStringContainsString( 'builder-cat', $source->to_string() );
		$this->assertLessThanOrEqual( 3, $count() );
	}
}
