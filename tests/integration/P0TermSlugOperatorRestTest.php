<?php
/**
 * P0 term slug operator lifecycle (thin seam / sync_term_view).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Language\Languages;
use AIMultilingual\Routing\CanonicalPathCollisionChecker;
use AIMultilingual\Routing\ObjectLanguagePublicEligibility;
use AIMultilingual\Routing\PathCanonicalizer;
use AIMultilingual\Routing\RouteHistoryRepository;
use AIMultilingual\Routing\RoutePublicationService;
use AIMultilingual\Routing\RoutingCapabilityAdmission;
use AIMultilingual\Routing\RoutingCapabilityRegistry;
use AIMultilingual\Routing\SlugCandidateService;
use AIMultilingual\Routing\SlugRouteRepository;
use AIMultilingual\Settings;
use AIMultilingual\Surface\SurfaceRegistry;
use AIMultilingual\Surface\TermSurfaceAdapter;
use AIMultilingual\Translation\Assessment\AssessmentAssembler;
use AIMultilingual\Translation\Publication\PublicationAuditLogger;
use AIMultilingual\Translation\Publication\PublicationMode;
use AIMultilingual\Translation\Publication\PublicationPolicy;
use AIMultilingual\Translation\Publication\PublicationService;
use AIMultilingual\Translation\Store;
use AIMultilingual\Translation\TermExtractor;

/**
 * AC2 family representatives: hierarchical category + flat post_tag.
 */
final class P0TermSlugOperatorRestTest extends AimlTestCase {

	private SlugCandidateService $candidates;
	private RoutePublicationService $route_publication;
	private SlugRouteRepository $routes;

	protected function setUp(): void {
		parent::setUp();
		$this->set_permalink_structure( '/%postname%/' );

		$settings = new Settings(
			array(
				'localized_urls_state'                     => 'on',
				'localized_urls_verified_capability_epoch' => 2,
				'localized_urls_admitted_capabilities'     => array(
					'term_archive',
					'page_hierarchical',
					'product_category_permalink',
				),
				'segment_publication_gate_enabled'         => false,
				'auto_publication_mode'                    => PublicationMode::MANUAL,
			)
		);
		update_option( Settings::OPTION, $settings->get() );

		$surfaces = new SurfaceRegistry();
		$surfaces->register( new TermSurfaceAdapter() );

		$publication = new PublicationService(
			$this->store,
			new AssessmentAssembler(),
			new PublicationPolicy(),
			new PublicationAuditLogger(),
			$settings,
			$surfaces
		);

		$this->candidates = new SlugCandidateService( $this->store );
		$this->routes     = new SlugRouteRepository();
		$history          = new RouteHistoryRepository();
		$paths            = new PathCanonicalizer();
		$capabilities     = new RoutingCapabilityRegistry();
		$admission        = new RoutingCapabilityAdmission( $settings, $capabilities );
		$collisions       = new CanonicalPathCollisionChecker( $this->routes, $history, $paths );
		$eligibility      = new ObjectLanguagePublicEligibility(
			$this->store,
			$this->languages,
			$capabilities,
			$settings,
			$this->routes,
			$admission
		);

		$this->route_publication = new RoutePublicationService(
			$this->store,
			$publication,
			$this->routes,
			$history,
			$paths,
			$collisions,
			$eligibility,
			$capabilities
		);
	}

	protected function tearDown(): void {
		$this->set_permalink_structure( '' );
		parent::tearDown();
	}

	public function test_category_family_generate_publish_and_sync_view(): void {
		$language = $this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$term_id  = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'News',
				'slug'     => 'news',
			)
		);
		$term     = get_term( $term_id, 'category' );
		$this->assertInstanceOf( \WP_Term::class, $term );

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_TERM,
				'source_id'       => (int) $term->term_id,
				'source_subtype'  => 'category',
				'language_id'     => (int) $language->language_id,
				'field_key'       => TermExtractor::FIELD_NAME,
				'segment_key'     => TermExtractor::FIELD_NAME,
				'segment_order'   => 0,
				'text_format'     => Store::FORMAT_PLAIN,
				'source_text'     => 'News',
				'translated_text' => 'Nyheter',
			)
		);

		$generated = $this->candidates->generate_for_term( $term, (int) $language->language_id );
		$this->assertIsObject( $generated );

		$view = $this->route_publication->sync_term_view( $term, (int) $language->language_id );
		$this->assertNotSame( '', $view['slug_candidate'] );
		$this->assertSame( 'pending', $view['route_sync_state'] );

		$published = $this->route_publication->publish_term_route( $term, (int) $language->language_id, 1 );
		$this->assertIsArray( $published );
		$this->assertTrue( ! empty( $published['route_prepared'] ) );
		$this->assertSame( Store::PUBLISH_PUBLISHED, (string) ( $published['slug_candidate_publish_status'] ?? '' ) );

		$view2 = $this->route_publication->sync_term_view( $term, (int) $language->language_id );
		$this->assertSame( 'synchronized', $view2['route_sync_state'] );
		$this->assertNotSame( '', $view2['localized_path'] );
	}

	public function test_post_tag_family_manual_candidate_sync_view(): void {
		$language = $this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$term_id  = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'name'     => 'Gate Tag',
				'slug'     => 'gate-tag',
			)
		);
		$term     = get_term( $term_id, 'post_tag' );
		$this->assertInstanceOf( \WP_Term::class, $term );

		$saved = $this->candidates->save_manual_for_term( $term, (int) $language->language_id, 'gate-etikett' );
		$this->assertIsObject( $saved );

		$view = $this->route_publication->sync_term_view( $term, (int) $language->language_id );
		$this->assertSame( 'gate-etikett', $view['slug_candidate'] );
		$this->assertSame( 'manual', $view['slug_origin'] );
		$this->assertFalse( $view['can_generate'] );
	}
}
