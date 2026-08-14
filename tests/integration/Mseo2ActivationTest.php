<?php
/**
 * MSEO.2 activation job integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Database\Schema;
use AIMultilingual\Jobs\SlugRouteActivationJob;
use AIMultilingual\Routing\CanonicalPathCollisionChecker;
use AIMultilingual\Routing\LocalizedUrlsActivationService;
use AIMultilingual\Routing\PathCanonicalizer;
use AIMultilingual\Routing\PathHash;
use AIMultilingual\Routing\RouteHistoryRepository;
use AIMultilingual\Routing\RoutePublicationService;
use AIMultilingual\Routing\RouteRecord;
use AIMultilingual\Routing\RoutingCapabilityRegistry;
use AIMultilingual\Routing\SlugRouteActivationVerifier;
use AIMultilingual\Routing\SlugCandidateService;
use AIMultilingual\Routing\SlugRouteRepository;
use AIMultilingual\Routing\ObjectLanguagePublicEligibility;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Publication\PublicationMode;
use AIMultilingual\Translation\Store;

/**
 * Covers MSEO.2.4–MSEO.2.5 activation verification and state machine.
 */
final class Mseo2ActivationTest extends AimlTestCase {

	private Settings $settings;
	private SlugRouteRepository $routes;
	private PathCanonicalizer $paths;
	private LocalizedUrlsActivationService $activation;
	private SlugRouteActivationJob $job;
	private RoutePublicationService $route_publication;
	private SlugCandidateService $candidates;

	protected function setUp(): void {
		parent::setUp();

		$this->set_permalink_structure( '/%postname%/' );

		$this->settings = new Settings(
			array(
				'localized_urls_state'             => LocalizedUrlsActivationService::STATE_OFF,
				'segment_publication_gate_enabled' => false,
				'auto_publication_mode'            => PublicationMode::MANUAL,
			)
		);
		update_option( Settings::OPTION, $this->settings->get() );

		$this->routes     = new SlugRouteRepository();
		$this->paths      = new PathCanonicalizer();
		$this->candidates = new SlugCandidateService( $this->store );
		$this->route_publication = $this->make_route_publication();
		$this->activation        = new LocalizedUrlsActivationService( $this->settings );
		$this->job               = new SlugRouteActivationJob(
			$this->settings,
			$this->routes,
			$this->make_verifier(),
			$this->activation
		);
		$this->activation->bind_job( $this->job );
	}

	protected function tearDown(): void {
		$this->set_permalink_structure( '' );
		parent::tearDown();
	}

	private function make_verifier(): SlugRouteActivationVerifier {
		$capabilities = new RoutingCapabilityRegistry();
		$history      = new RouteHistoryRepository();

		return new SlugRouteActivationVerifier(
			$this->languages,
			$capabilities,
			$this->paths,
			$this->routes,
			$history,
			new CanonicalPathCollisionChecker( $this->routes, $history, $this->paths )
		);
	}

	private function make_route_publication(): RoutePublicationService {
		$capabilities = new RoutingCapabilityRegistry();
		$history      = new RouteHistoryRepository();
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
			$this->paths,
			new CanonicalPathCollisionChecker( $this->routes, $history, $this->paths ),
			$eligibility,
			$capabilities
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function snapshot_routes(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			'SELECT route_id, route_status, source_path, localized_path, slug_origin FROM ' . Schema::slug_routes(),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	private function seed_published_active_route(): void {
		$post     = $this->create_page( 'Activation Page' );
		$language = $this->add_language( 'sv', 'sv_SE', \AIMultilingual\Language\Languages::STATUS_PUBLISHED );
		$this->translate( $post, $language, Extractor::FIELD_TITLE, 'Aktivering' );

		$this->candidates->generate( $post, (int) $language->language_id );
		$result = $this->route_publication->publish_route( $post, (int) $language->language_id, 1 );
		$this->assertIsArray( $result );
	}

	private function seed_hierarchical_active_route(): void {
		$parent   = $this->create_page( 'Parent Page' );
		$child_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Child Page',
				'post_status' => 'publish',
				'post_parent' => (int) $parent->ID,
			)
		);
		$post     = get_post( $child_id );
		$this->assertInstanceOf( \WP_Post::class, $post );

		$language = $this->add_language( 'no', 'nb_NO', \AIMultilingual\Language\Languages::STATUS_PUBLISHED );
		$source   = $this->paths->canonicalize( '/' . (string) $post->post_name );
		$localized = $this->paths->canonicalize( '/barn-side' );

		$this->routes->save(
			new RouteRecord(
				(int) $language->language_id,
				Store::SOURCE_POST,
				(int) $post->ID,
				'page',
				$source,
				$localized,
				'barn-side',
				'',
				'generated',
				'active'
			)
		);
	}

	public function test_activation_completes_to_on_with_admitted_routes(): void {
		$this->seed_published_active_route();

		$this->activation->request_enable();
		$this->settings->reload();
		$this->assertSame( LocalizedUrlsActivationService::STATE_ACTIVATING, $this->settings->localized_urls_state() );

		$this->job->tick();

		$this->settings->reload();
		$this->assertSame( LocalizedUrlsActivationService::STATE_ON, $this->settings->localized_urls_state() );
		$this->assertSame( '', $this->settings->localized_urls_activation_error() );
	}

	public function test_skipped_unsupported_does_not_fail_activation(): void {
		$this->seed_hierarchical_active_route();

		$this->activation->request_enable();
		$this->job->tick();

		$this->settings->reload();
		$this->assertSame( LocalizedUrlsActivationService::STATE_ON, $this->settings->localized_urls_state() );
	}

	public function test_invalid_data_sets_failed(): void {
		global $wpdb;

		$this->seed_published_active_route();
		$before = $this->snapshot_routes();
		$this->assertNotEmpty( $before );

		$route_id = (int) ( $before[0]['route_id'] ?? 0 );
		$this->assertGreaterThan( 0, $route_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::slug_routes() . ' SET localized_path = %s WHERE route_id = %d',
				'/corrupt-path',
				$route_id
			)
		);

		$this->activation->request_enable();
		$this->job->tick();

		$this->settings->reload();
		$this->assertSame( LocalizedUrlsActivationService::STATE_FAILED, $this->settings->localized_urls_state() );
		$this->assertNotSame( '', $this->settings->localized_urls_activation_error() );
	}

	public function test_activation_does_not_mutate_routes(): void {
		$this->seed_published_active_route();
		$before = $this->snapshot_routes();

		$this->activation->request_enable();
		$this->job->tick();

		$this->assertSame( $before, $this->snapshot_routes() );
	}

	public function test_activation_job_does_not_reference_route_publication_or_candidates(): void {
		$root = dirname( __DIR__, 2 );
		$job  = (string) file_get_contents( $root . '/src/Jobs/SlugRouteActivationJob.php' );
		$verifier = (string) file_get_contents( $root . '/src/Routing/SlugRouteActivationVerifier.php' );

		foreach ( array( $job, $verifier ) as $source ) {
			$this->assertStringNotContainsString( 'RoutePublicationService', $source );
			$this->assertStringNotContainsString( 'publish_route', $source );
			$this->assertStringNotContainsString( 'SlugCandidateService', $source );
			$this->assertStringNotContainsString( 'generate(', $source );
		}
	}

	public function test_corrupt_hash_fixture_is_invalid_data(): void {
		global $wpdb;

		$language = $this->add_language( 'fi', 'fi_FI', \AIMultilingual\Language\Languages::STATUS_PUBLISHED );
		$path_a   = $this->paths->canonicalize( '/hash-a' );
		$path_b   = new \AIMultilingual\Routing\CanonicalPath( '/hash-b' );
		$hash_hex = PathHash::from_canonical( $path_a )->hex();
		$now      = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . Schema::slug_routes() . '
				(language_id, source_type, source_id, source_subtype,
				 source_path, source_path_hash, localized_path, localized_path_hash,
				 localized_slug, route_namespace, slug_origin, route_status, activated_at,
				 created_at, updated_at)
				VALUES (%d, %s, %d, %s, %s, UNHEX(%s), %s, UNHEX(%s), %s, %s, %s, %s, %s, %s, %s)',
				(int) $language->language_id,
				Store::SOURCE_POST,
				99001,
				'post',
				$path_b->to_string(),
				$hash_hex,
				$path_b->to_string(),
				$hash_hex,
				'hash-b',
				'',
				'generated',
				'active',
				$now,
				$now,
				$now
			)
		);

		$this->activation->request_enable();
		$this->job->tick();

		$this->settings->reload();
		$this->assertSame( LocalizedUrlsActivationService::STATE_FAILED, $this->settings->localized_urls_state() );
	}
}
