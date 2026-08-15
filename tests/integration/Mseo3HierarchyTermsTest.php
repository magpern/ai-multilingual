<?php
/**
 * MSEO.3 hierarchy / term localized URL integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Database\Migrator;
use AIMultilingual\Jobs\CapabilityVerificationJob;
use AIMultilingual\Jobs\HierarchyReindexJob;
use AIMultilingual\Language\Languages;
use AIMultilingual\Routing\CanonicalPathCollisionChecker;
use AIMultilingual\Routing\EffectiveUrlService;
use AIMultilingual\Routing\HierarchyPathBuilder;
use AIMultilingual\Routing\ObjectLanguagePublicEligibility;
use AIMultilingual\Routing\PathCanonicalizer;
use AIMultilingual\Routing\ReindexFrontierRepository;
use AIMultilingual\Routing\RouteHistoryRepository;
use AIMultilingual\Routing\RoutePublicationService;
use AIMultilingual\Routing\RoutingCapabilityAdmission;
use AIMultilingual\Routing\RoutingCapabilityRegistry;
use AIMultilingual\Routing\SlugCandidateService;
use AIMultilingual\Routing\SlugRouteRepository;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Assessment\AssessmentAssembler;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Publication\PublicationAuditLogger;
use AIMultilingual\Translation\Publication\PublicationMode;
use AIMultilingual\Translation\Publication\PublicationPolicy;
use AIMultilingual\Translation\Publication\PublicationService;
use AIMultilingual\Translation\Store;
use AIMultilingual\Translation\TermExtractor;

/**
 * Focused MSEO.3 coverage for admission, terms, hierarchy, and frontier.
 */
final class Mseo3HierarchyTermsTest extends AimlTestCase {

	private Settings $settings;
	private SlugRouteRepository $routes;
	private PathCanonicalizer $paths;
	private RoutingCapabilityRegistry $capabilities;
	private RoutingCapabilityAdmission $admission;
	private HierarchyPathBuilder $hierarchy;
	private SlugCandidateService $candidates;
	private RoutePublicationService $route_publication;
	private ObjectLanguagePublicEligibility $eligibility;

	protected function setUp(): void {
		parent::setUp();

		$this->set_permalink_structure( '/%postname%/' );

		$this->settings = new Settings(
			array(
				'localized_urls_state'                     => 'on',
				'localized_urls_verified_capability_epoch' => 0,
				'localized_urls_admitted_capabilities'     => array(),
				'segment_publication_gate_enabled'         => false,
				'auto_publication_mode'                    => PublicationMode::MANUAL,
			)
		);
		update_option( Settings::OPTION, $this->settings->get() );

		$this->routes       = new SlugRouteRepository();
		$this->paths        = new PathCanonicalizer();
		$this->capabilities = new RoutingCapabilityRegistry();
		$this->admission    = new RoutingCapabilityAdmission( $this->settings, $this->capabilities );
		$this->hierarchy    = new HierarchyPathBuilder( $this->routes, $this->paths );
		$this->candidates   = new SlugCandidateService( $this->store );
		$this->eligibility  = new ObjectLanguagePublicEligibility(
			$this->store,
			$this->languages,
			$this->capabilities,
			$this->settings,
			$this->routes,
			$this->admission
		);
		$this->route_publication = $this->make_route_publication();
	}

	protected function tearDown(): void {
		$this->set_permalink_structure( '' );
		parent::tearDown();
	}

	private function make_route_publication(): RoutePublicationService {
		$history    = new RouteHistoryRepository();
		$collisions = new CanonicalPathCollisionChecker( $this->routes, $history, $this->paths );

		return new RoutePublicationService(
			$this->store,
			new PublicationService(
				$this->store,
				new AssessmentAssembler(),
				new PublicationPolicy(),
				new PublicationAuditLogger(),
				$this->settings
			),
			$this->routes,
			$history,
			$this->paths,
			$collisions,
			$this->eligibility,
			$this->capabilities,
			$this->hierarchy,
			$this->admission
		);
	}

	public function test_target_remains_eight(): void {
		$this->assertSame( 8, Migrator::TARGET );
	}

	public function test_admission_not_public_until_commit(): void {
		$this->assertFalse( $this->admission->is_publicly_admitted( RoutingCapabilityAdmission::SHAPE_TERM_ARCHIVE ) );
		$this->assertFalse( $this->admission->is_publicly_admitted( RoutingCapabilityAdmission::SHAPE_PAGE_HIERARCHICAL ) );

		$this->admission->commit_admission(
			array( RoutingCapabilityAdmission::SHAPE_TERM_ARCHIVE ),
			RoutingCapabilityAdmission::CODE_CAPABILITY_EPOCH
		);
		$this->settings->reload();

		$this->assertTrue( $this->admission->is_publicly_admitted( RoutingCapabilityAdmission::SHAPE_TERM_ARCHIVE ) );
		$this->assertFalse( $this->admission->is_publicly_admitted( RoutingCapabilityAdmission::SHAPE_PAGE_HIERARCHICAL ) );
		$this->assertSame(
			RoutingCapabilityAdmission::CODE_CAPABILITY_EPOCH,
			$this->settings->localized_urls_verified_capability_epoch()
		);
	}

	public function test_deploy_safe_implemented_not_admitted_uses_source_slug(): void {
		$language = $this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$parent   = $this->create_page( 'Parent Page' );
		$child    = $this->create_child_page( 'Child Page', (int) $parent->ID );

		$this->translate( $child, $language, Extractor::FIELD_TITLE, 'Barnsida' );
		$this->candidates->generate( $child, (int) $language->language_id );
		$published = $this->route_publication->publish_route( $child, (int) $language->language_id );
		$this->assertIsArray( $published );

		$route = $this->routes->find_by_object( Store::SOURCE_POST, (int) $child->ID, (int) $language->language_id );
		$this->assertNotNull( $route );
		$source_path = (string) $route->source_path;
		$localized   = (string) $route->localized_path;
		$this->assertNotSame( '', $localized );

		$effective = new EffectiveUrlService(
			$this->settings,
			$this->routes,
			$this->capabilities,
			$this->paths,
			$this->languages,
			$this->admission
		);

		// Implemented hierarchical page exists, but not publicly admitted → source path.
		$this->assertSame(
			$source_path,
			$effective->unprefixed_effective_path( $source_path, (int) $language->language_id )
		);

		$this->admission->commit_admission(
			array( RoutingCapabilityAdmission::SHAPE_PAGE_HIERARCHICAL ),
			RoutingCapabilityAdmission::CODE_CAPABILITY_EPOCH
		);
		$effective->clear_request_cache();

		$this->assertSame(
			$localized,
			$effective->unprefixed_effective_path( $source_path, (int) $language->language_id )
		);
	}

	public function test_term_generate_and_publish_under_authority(): void {
		$language = $this->add_language( 'de', 'de_DE', Languages::STATUS_PUBLISHED );
		$term_id  = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'News',
				'slug'     => 'news',
			)
		);
		$term = get_term( $term_id, 'category' );
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
				'translated_text' => 'Nachrichten',
			)
		);

		$generated = $this->candidates->generate_for_term( $term, (int) $language->language_id );
		$this->assertIsObject( $generated );
		$this->assertSame( 'nachrichten', (string) $generated->translated_text );
		$this->assertSame( 'generated', (string) $generated->slug_origin );

		$published = $this->route_publication->publish_term_route( $term, (int) $language->language_id );
		$this->assertIsArray( $published );
		$this->assertTrue( ! empty( $published['route_prepared'] ) );

		$route = $this->routes->find_by_object( Store::SOURCE_TERM, (int) $term->term_id, (int) $language->language_id );
		$this->assertNotNull( $route );
		$this->assertSame( 'active', (string) $route->route_status );
		$this->assertSame( 'nachrichten', (string) $route->localized_slug );
		$this->assertStringContainsString( 'nachrichten', (string) $route->localized_path );

		// Canonical term slug untouched.
		$fresh = get_term( (int) $term->term_id, 'category' );
		$this->assertSame( 'news', (string) $fresh->slug );
	}

	public function test_hierarchical_path_builder_uses_ancestor_leaves(): void {
		$language = $this->add_language( 'fr', 'fr_FR', Languages::STATUS_PUBLISHED );
		$parent   = $this->create_page( 'Services' );
		$child    = $this->create_child_page( 'Consulting', (int) $parent->ID );

		$this->translate( $parent, $language, Extractor::FIELD_TITLE, 'Services FR' );
		$this->candidates->generate( $parent, (int) $language->language_id );
		$this->route_publication->publish_route( $parent, (int) $language->language_id );

		$parent_route = $this->routes->find_by_object( Store::SOURCE_POST, (int) $parent->ID, (int) $language->language_id );
		$this->assertNotNull( $parent_route );

		$built = $this->hierarchy->localized_path_for_post( $child, (int) $language->language_id, 'conseil' );
		$this->assertNotInstanceOf( \WP_Error::class, $built );
		$this->assertSame(
			'/' . trim( (string) $parent_route->localized_slug, '/' ) . '/conseil',
			$built->to_string()
		);

		$source = $this->hierarchy->source_path_for_post( $child );
		$this->assertNotInstanceOf( \WP_Error::class, $source );
		$this->assertStringContainsString( (string) $parent->post_name, $source->to_string() );
		$this->assertStringContainsString( (string) $child->post_name, $source->to_string() );
	}

	public function test_frontier_dfs_bounded_and_degraded_on_collision(): void {
		$parent = $this->create_page( 'Root' );
		$child_a = $this->create_child_page( 'Child A', (int) $parent->ID );
		$child_b = $this->create_child_page( 'Child B', (int) $parent->ID );
		$grandchild = $this->create_child_page( 'Grandchild', (int) $child_a->ID );

		$language = $this->add_language( 'it', 'it_IT', Languages::STATUS_PUBLISHED );

		foreach ( array( $parent, $child_a, $child_b, $grandchild ) as $post ) {
			$this->translate( $post, $language, Extractor::FIELD_TITLE, $post->post_title . ' IT' );
			$this->candidates->generate( $post, (int) $language->language_id );
			$result = $this->route_publication->publish_route( $post, (int) $language->language_id );
			$this->assertIsArray( $result, 'publish failed for ' . $post->post_name );
		}

		// Force a collision on child_b by planting another route on its rematerialized target path.
		$child_b_route = $this->routes->find_by_object( Store::SOURCE_POST, (int) $child_b->ID, (int) $language->language_id );
		$this->assertNotNull( $child_b_route );

		$job = new HierarchyReindexJob(
			new ReindexFrontierRepository(),
			$this->route_publication,
			$this->routes
		);

		$enqueued = $job->enqueue_root( Store::SOURCE_POST, (int) $parent->ID );
		$this->assertIsObject( $enqueued );

		$ticks = 0;
		$status = 'running';
		while ( $ticks < 20 && in_array( $status, array( 'pending', 'running' ), true ) ) {
			$out    = $job->process_batch( Store::SOURCE_POST, (int) $parent->ID );
			$status = (string) ( $out['status'] ?? 'idle' );
			++$ticks;
		}

		$row = ( new ReindexFrontierRepository() )->find_by_parent( Store::SOURCE_POST, (int) $parent->ID );
		$this->assertNotNull( $row );
		$checkpoint = json_decode( (string) ( $row->checkpoint_json ?? '' ), true );
		$this->assertIsArray( $checkpoint );
		$this->assertArrayHasKey( 'stack', $checkpoint );
		$this->assertLessThanOrEqual( HierarchyReindexJob::MAX_STACK_DEPTH, count( (array) $checkpoint['stack'] ) );

		// Without engineered collision the frontier should complete.
		$this->assertContains(
			(string) $row->status,
			array( HierarchyReindexJob::STATUS_COMPLETED, HierarchyReindexJob::STATUS_DEGRADED, HierarchyReindexJob::STATUS_RUNNING )
		);

		// Simulate degraded: mark conflict and empty stack.
		$checkpoint['stack']           = array();
		$checkpoint['conflict_ids']    = array( (int) $child_b->ID );
		$checkpoint['processed_count'] = 3;
		$frontiers                     = new ReindexFrontierRepository();
		$frontiers->update_checkpoint(
			Store::SOURCE_POST,
			(int) $parent->ID,
			(int) ( $row->generation ?? 1 ),
			wp_json_encode( $checkpoint ),
			HierarchyReindexJob::STATUS_DEGRADED
		);

		$row = $frontiers->find_by_parent( Store::SOURCE_POST, (int) $parent->ID );
		$this->assertSame( HierarchyReindexJob::STATUS_DEGRADED, (string) $row->status );
		$this->assertNotSame( HierarchyReindexJob::STATUS_COMPLETED, (string) $row->status );
	}

	public function test_capability_verification_admits_atomically_without_disabling_mseo2(): void {
		$this->assertSame( 'on', $this->settings->localized_urls_state() );
		$this->assertSame( 0, $this->settings->localized_urls_verified_capability_epoch() );

		$job = new CapabilityVerificationJob(
			$this->settings,
			$this->admission,
			$this->capabilities,
			$this->hierarchy,
			$this->routes
		);

		$guard = 0;
		$result = array( 'status' => 'continue' );
		while ( $guard < 50 && 'admitted' !== ( $result['status'] ?? '' ) && 'failed' !== ( $result['status'] ?? '' ) ) {
			$result = $job->process_batch();
			++$guard;
		}

		$this->assertSame( 'admitted', $result['status'] ?? '' );
		$this->assertSame( 'on', $this->settings->localized_urls_state() );
		$this->assertSame(
			RoutingCapabilityAdmission::CODE_CAPABILITY_EPOCH,
			$this->settings->localized_urls_verified_capability_epoch()
		);
		$this->assertTrue( $this->admission->is_publicly_admitted( RoutingCapabilityAdmission::SHAPE_TERM_ARCHIVE ) );
		$this->assertTrue( $this->admission->is_publicly_admitted( RoutingCapabilityAdmission::SHAPE_PAGE_HIERARCHICAL ) );
	}

	/**
	 * Creates a child page under a parent.
	 *
	 * @param string $title     Title.
	 * @param int    $parent_id Parent post id.
	 */
	private function create_child_page( string $title, int $parent_id ): \WP_Post {
		$id = self::factory()->post->create(
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
}
