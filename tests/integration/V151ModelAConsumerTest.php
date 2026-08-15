<?php
/**
 * V1.5.1 Model A consumer characterization / EffectiveUrl agreement.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Frontend\Switcher;
use AIMultilingual\Routing\ObjectLanguagePublicEligibility;
use AIMultilingual\Routing\PathCanonicalizer;
use AIMultilingual\Routing\RoutePublicationService;
use AIMultilingual\Routing\RoutingCapabilityRegistry;
use AIMultilingual\Routing\SlugCandidateService;
use AIMultilingual\Routing\SlugRouteRepository;
use AIMultilingual\Seo\DocumentSeoHead;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Publication\PublicationMode;
use AIMultilingual\Translation\Store;

/**
 * WP3/WP4 — per-consumer EffectiveUrl agreement under CURRENT_LOCALIZED + LU ON.
 */
final class V151ModelAConsumerTest extends AimlTestCase {

	private Settings $settings;
	private SlugRouteRepository $routes;
	private SlugCandidateService $candidates;
	private RoutePublicationService $route_publication;

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

		$this->routes            = new SlugRouteRepository();
		$this->candidates        = new SlugCandidateService( $this->store );
		$this->route_publication = $this->make_route_publication();
	}

	protected function tearDown(): void {
		$this->set_permalink_structure( '' );
		remove_all_filters( 'home_url' );
		remove_all_filters( 'term_link' );
		parent::tearDown();
	}

	private function make_route_publication(): RoutePublicationService {
		$capabilities = new RoutingCapabilityRegistry();
		$paths        = new PathCanonicalizer();
		$history      = new \AIMultilingual\Routing\RouteHistoryRepository();
		$collisions   = new \AIMultilingual\Routing\CanonicalPathCollisionChecker( $this->routes, $history, $paths );
		$eligibility  = new ObjectLanguagePublicEligibility(
			$this->store,
			$this->languages,
			$capabilities,
			$this->settings,
			$this->routes
		);

		return new RoutePublicationService(
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
	 * @return array{post: \WP_Post, language: object, localized: string, source: string, expected_sv: string}
	 */
	private function seed_flat_discoverable(): array {
		$post     = $this->create_page( 'Flat Discoverable' );
		$language = $this->add_language( 'sv', 'sv_SE', \AIMultilingual\Language\Languages::STATUS_PUBLISHED );
		$this->translate( $post, $language, Extractor::FIELD_TITLE, 'Platt Upptackbar' );

		$this->assertIsObject( $this->candidates->generate( $post, (int) $language->language_id ) );
		$this->assertIsArray( $this->route_publication->publish_route( $post, (int) $language->language_id, 1 ) );

		$route = $this->routes->find_by_object( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id );
		$this->assertNotNull( $route );

		$expected = trailingslashit( (string) get_option( 'home' ) ) . 'sv' . (string) $route->localized_path;

		return array(
			'post'        => $post,
			'language'    => $language,
			'localized'   => (string) $route->localized_path,
			'source'      => (string) $route->source_path,
			'expected_sv' => $expected,
		);
	}

	/**
	 * @return array{parent: \WP_Post, child: \WP_Post, language: object, localized: string, source: string, expected_sv: string}
	 */
	private function seed_hierarchical_parent_discoverable(): array {
		$parent   = $this->create_page( 'Gate B Parent' );
		$child    = $this->create_child_page( 'Gate B Child', (int) $parent->ID );
		$language = $this->add_language( 'sv', 'sv_SE', \AIMultilingual\Language\Languages::STATUS_PUBLISHED );

		$this->translate( $parent, $language, Extractor::FIELD_TITLE, 'Gate B Foralder' );
		$this->translate( $child, $language, Extractor::FIELD_TITLE, 'Gate B Barn' );

		$this->assertIsObject( $this->candidates->generate( $parent, (int) $language->language_id ) );
		$this->assertIsArray( $this->route_publication->publish_route( $parent, (int) $language->language_id, 1 ) );
		$this->assertIsObject( $this->candidates->generate( $child, (int) $language->language_id ) );
		$this->assertIsArray( $this->route_publication->publish_route( $child, (int) $language->language_id, 1 ) );

		$route = $this->routes->find_by_object( Store::SOURCE_POST, (int) $parent->ID, (int) $language->language_id );
		$this->assertNotNull( $route );

		$expected = trailingslashit( (string) get_option( 'home' ) ) . 'sv' . (string) $route->localized_path;

		return array(
			'parent'      => $parent,
			'child'       => $child,
			'language'    => $language,
			'localized'   => (string) $route->localized_path,
			'source'      => (string) $route->source_path,
			'expected_sv' => $expected,
		);
	}

	/**
	 * Creates a child page under a parent.
	 *
	 * @param string $title     Title.
	 * @param int    $parent_id Parent post id.
	 */
	private function create_child_page( string $title, int $parent_id ): \WP_Post {
		$id   = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_parent' => $parent_id,
			)
		);
		$post = get_post( $id );
		$this->assertInstanceOf( \WP_Post::class, $post );

		return $post;
	}

	private function url_for_code( array $rels, string $code ): ?string {
		foreach ( $rels as $rel ) {
			if ( $code === $rel->language_code ) {
				return $rel->url;
			}
		}

		return null;
	}

	/**
	 * V151AC7/AC8/AC10 — hreflang agrees with EffectiveUrl on CURRENT_LOCALIZED.
	 */
	public function test_hreflang_current_localized_agrees_with_effective_url_flat(): void {
		$fixture = $this->seed_flat_discoverable();
		$router  = $this->route( '/sv' . $fixture['localized'], $this->settings );
		$router->enable_url_prefixing();

		$svc = $this->make_relationships( $this->settings );
		$sv  = $this->url_for_code( $svc->for_public_request(), 'sv' );

		$this->assertNotNull( $sv );
		$this->assertSame(
			untrailingslashit( $fixture['expected_sv'] ),
			untrailingslashit( (string) $sv )
		);
		$this->assertStringContainsString( $fixture['localized'], (string) $sv );
		$this->assertStringNotContainsString( $fixture['source'], (string) $sv );
	}

	public function test_hreflang_current_localized_agrees_with_effective_url_hierarchical_parent(): void {
		$fixture = $this->seed_hierarchical_parent_discoverable();
		$router  = $this->route( '/sv' . $fixture['localized'], $this->settings );
		$router->enable_url_prefixing();

		$svc = $this->make_relationships( $this->settings );
		$sv  = $this->url_for_code( $svc->for_public_request(), 'sv' );

		$this->assertNotNull( $sv );
		$this->assertSame(
			untrailingslashit( $fixture['expected_sv'] ),
			untrailingslashit( (string) $sv )
		);
		$this->assertStringContainsString( $fixture['localized'], (string) $sv );
	}

	/**
	 * Canonical under CURRENT_LOCALIZED — already-correct family or fixed via shared SB11.
	 */
	public function test_canonical_current_localized_agrees_with_effective_url(): void {
		$fixture = $this->seed_flat_discoverable();
		$router  = $this->route( '/sv' . $fixture['localized'], $this->settings );
		$router->enable_url_prefixing();

		$svc       = $this->make_relationships( $this->settings );
		$canonical = $svc->current_canonical_url();

		$this->assertNotNull( $canonical );
		$this->assertSame(
			untrailingslashit( $fixture['expected_sv'] ),
			untrailingslashit( (string) $canonical )
		);
	}

	/**
	 * Open Graph URL via current_public() — D3a / shared SB11 path.
	 */
	public function test_og_url_current_public_agrees_with_effective_url(): void {
		$fixture = $this->seed_flat_discoverable();
		$router  = $this->route( '/sv' . $fixture['localized'], $this->settings );
		$router->enable_url_prefixing();

		$current = $this->make_relationships( $this->settings )->current_public();
		$this->assertNotNull( $current );
		$this->assertSame(
			untrailingslashit( $fixture['expected_sv'] ),
			untrailingslashit( $current->url )
		);
	}

	/**
	 * Switcher links for discoverable CURRENT_LOCALIZED agree with EffectiveUrl.
	 */
	public function test_switcher_current_localized_agrees_with_effective_url(): void {
		$fixture = $this->seed_flat_discoverable();
		$router  = $this->route( '/sv' . $fixture['localized'], $this->settings );
		$router->enable_url_prefixing();

		$switcher = new Switcher(
			$this->settings,
			$this->languages,
			$this->context,
			$this->make_relationships( $this->settings )
		);

		$links = $switcher->links();
		$sv    = null;
		foreach ( $links as $link ) {
			if ( 'sv' === ( $link['code'] ?? '' ) ) {
				$sv = (string) ( $link['url'] ?? '' );
				break;
			}
		}

		$this->assertNotNull( $sv );
		$this->assertNotSame( '', $sv );
		$this->assertStringContainsString( $fixture['localized'], (string) $sv );
	}

	/**
	 * Source request hreflang SV also agrees (control — was already correct in Gate B).
	 */
	public function test_hreflang_source_request_agrees_with_effective_url(): void {
		$fixture = $this->seed_flat_discoverable();
		$this->route( $fixture['source'] . '/', $this->settings );

		$svc = $this->make_relationships( $this->settings );
		$sv  = $this->url_for_code( $svc->for_public_request(), 'sv' );

		$this->assertNotNull( $sv );
		$this->assertSame(
			untrailingslashit( $fixture['expected_sv'] ),
			untrailingslashit( (string) $sv )
		);
	}

	/**
	 * DocumentSeoHead emit path uses the corrected public set.
	 */
	public function test_document_seo_head_hreflang_contains_localized_path(): void {
		$fixture = $this->seed_flat_discoverable();
		$router  = $this->route( '/sv' . $fixture['localized'], $this->settings );
		$router->enable_url_prefixing();

		$head = new DocumentSeoHead( $this->make_relationships( $this->settings ) );
		ob_start();
		$head->emit_hreflang();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'hreflang="sv-SE"', $html );
		$this->assertStringContainsString( $fixture['localized'], $html );
	}

	/**
	 * Sitemap-style for_path on source loc (Model A) agrees with EffectiveUrl — ALREADY CORRECT / regression.
	 */
	public function test_sitemap_for_path_on_source_loc_agrees_with_effective_url(): void {
		$fixture = $this->seed_flat_discoverable();
		// No CURRENT_LOCALIZED / no home_url prefixing — Rank Math primary locs are default-language.
		$svc = $this->make_relationships( $this->settings );
		$sv  = $this->url_for_code( $svc->for_path( $fixture['source'], false, true ), 'sv' );

		$this->assertNotNull( $sv );
		$this->assertSame(
			untrailingslashit( $fixture['expected_sv'] ),
			untrailingslashit( (string) $sv )
		);
	}

	/**
	 * LU OFF remains inert for public set (SA7 source paths).
	 */
	public function test_lu_off_hreflang_uses_source_slug_path(): void {
		$fixture        = $this->seed_flat_discoverable();
		$this->settings = new Settings(
			array_merge(
				$this->settings->get(),
				array( 'localized_urls_state' => 'off' )
			)
		);
		update_option( Settings::OPTION, $this->settings->get() );

		$this->route( '/sv' . $fixture['source'] . '/', $this->settings );
		$svc = $this->make_relationships( $this->settings );
		$sv  = $this->url_for_code( $svc->for_public_request(), 'sv' );

		$this->assertNotNull( $sv );
		$this->assertStringContainsString( $fixture['source'], (string) $sv );
	}
}
