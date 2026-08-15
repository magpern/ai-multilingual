<?php
/**
 * Background translation jobs REST integration tests (J5).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Jobs\BackgroundTranslationBatchCoordinator;
use AIMultilingual\Jobs\BackgroundTranslationDiagnostics;
use AIMultilingual\Jobs\BackgroundTranslationJobRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobService;
use AIMultilingual\Jobs\BackgroundTranslationScheduler;
use AIMultilingual\Jobs\BackgroundTranslationWorker;
use AIMultilingual\Jobs\JobLeaseService;
use AIMultilingual\Jobs\JobProgressReconciler;
use AIMultilingual\Jobs\JobTypes;
use AIMultilingual\Jobs\JobsCapabilities;
use AIMultilingual\Jobs\JobsController;
use AIMultilingual\Translation\Store;
use WP_Error;
use WP_REST_Request;

/**
 * REST surface for jobs CRUD/actions, capability gating, and domain error mapping.
 */
final class JobsRestTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	private BackgroundTranslationJobRepository $job_repo;

	protected function setUp(): void {
		parent::setUp();
		require_once __DIR__ . '/UnavailableJobsSchedulerStub.php';
		$this->enable_strategy_f_flags();
		$this->define_action_scheduler_stubs();
		JobsCapabilities::grant_default_roles();
		$this->job_repo = new BackgroundTranslationJobRepository();
	}

	public function test_job_routes_are_registered_under_aiml_v1(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/aiml/v1/jobs', $routes );
		$this->assertArrayHasKey( '/aiml/v1/jobs/(?P<id>\\d+)', $routes );
		$this->assertArrayHasKey( '/aiml/v1/jobs/(?P<id>\\d+)/pause', $routes );
		$this->assertArrayHasKey( '/aiml/v1/jobs/(?P<id>\\d+)/resume', $routes );
		$this->assertArrayHasKey( '/aiml/v1/jobs/(?P<id>\\d+)/cancel', $routes );
		$this->assertArrayHasKey( '/aiml/v1/jobs/(?P<id>\\d+)/retry-failed', $routes );
		$this->assertArrayHasKey( '/aiml/v1/jobs/(?P<id>\\d+)/run', $routes );
		$this->assertArrayHasKey( '/aiml/v1/jobs/(?P<id>\\d+)/items/(?P<item_id>\\d+)/assessment', $routes );
		$this->assertArrayHasKey( '/aiml/v1/jobs/batch/(?P<batch_id>[a-zA-Z0-9\\-]+)', $routes );
		$this->assertArrayHasKey( '/aiml/v1/jobs/health', $routes );
		$this->assertArrayHasKey( '/aiml/v1/jobs/diagnostics', $routes );
	}

	public function test_subscriber_cannot_list_jobs(): void {
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/aiml/v1/jobs' ) );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_diagnostics_route_requires_view_capability_and_returns_bounded_shape(): void {
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$denied = rest_do_request( new WP_REST_Request( 'GET', '/aiml/v1/jobs/diagnostics' ) );
		$this->assertSame( 403, $denied->get_status() );

		wp_set_current_user( $this->create_translator() );
		$allowed = rest_do_request( new WP_REST_Request( 'GET', '/aiml/v1/jobs/diagnostics' ) );
		$this->assertSame( 200, $allowed->get_status() );

		$data = $allowed->get_data();
		$this->assertArrayHasKey( 'status_counts', $data );
		$this->assertArrayHasKey( 'queue_age', $data );
		$this->assertArrayHasKey( 'counters', $data );
		$this->assertArrayHasKey( 'action_scheduler', $data );
		$this->assertArrayNotHasKey( 'prompt', $data );
		$this->assertSame(
			BackgroundTranslationDiagnostics::counter_keys(),
			array_keys( $data['counters'] )
		);
	}

	public function test_editor_can_list_jobs_but_cannot_run(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$job      = $this->seed_job( (int) $post->ID, (int) $language->language_id );

		wp_set_current_user( $this->create_translator() );

		$list = rest_do_request( new WP_REST_Request( 'GET', '/aiml/v1/jobs' ) );
		$this->assertSame( 200, $list->get_status() );

		$run = rest_do_request(
			new WP_REST_Request( 'POST', '/aiml/v1/jobs/' . (int) $job->job_id . '/run' )
		);
		$this->assertSame( 403, $run->get_status() );
	}

	public function test_administrator_can_run_job(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$job      = $this->seed_job( (int) $post->ID, (int) $language->language_id );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = rest_do_request(
			new WP_REST_Request( 'POST', '/aiml/v1/jobs/' . (int) $job->job_id . '/run' )
		);
		$this->assertSame( 202, $response->get_status() );
		$this->assertTrue( $response->get_data()['queued'] ?? false );
	}

	public function test_create_job_returns_201_with_safe_viewmodel(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$request = new WP_REST_Request( 'POST', '/aiml/v1/jobs' );
		$request->set_body_params(
			array(
				'job_type'       => JobTypes::TRANSLATE_SELECTED,
				'source_type'    => Store::SOURCE_POST,
				'source_id'      => (int) $post->ID,
				'language_id'    => (int) $language->language_id,
				'segment_keys'   => array( $this->default_segment_key() ),
				'prompt_profile' => 'default',
				'prompt_version' => '1',
			)
		);

		$response = rest_do_request( $request );
		$this->assertSame( 201, $response->get_status(), wp_json_encode( $response->get_data() ) );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'job_id', $data );
		$this->assertArrayNotHasKey( 'prompt', $data );
		$this->assertArrayNotHasKey( 'lease_owner', $data );
		$this->assertArrayNotHasKey( 'lock_key', $data );
		$headers = array_change_key_case( (array) $response->get_headers(), CASE_LOWER );
		$this->assertSame( JobsController::API_VERSION, $headers['x-aiml-jobs-api-version'] ?? '' );
	}

	public function test_create_job_rejects_default_language(): void {
		$post    = $this->create_block_page();
		$default = $this->languages->default();
		wp_set_current_user( $this->create_translator() );

		$request = new WP_REST_Request( 'POST', '/aiml/v1/jobs' );
		$request->set_body_params(
			array(
				'job_type'       => JobTypes::TRANSLATE_SELECTED,
				'source_type'    => Store::SOURCE_POST,
				'source_id'      => (int) $post->ID,
				'language_id'    => (int) $default->language_id,
				'segment_keys'   => array( 'post_title' ),
				'prompt_profile' => 'default',
				'prompt_version' => '1',
			)
		);

		$response = rest_do_request( $request );
		$this->assertSame( 422, $response->get_status() );
	}

	public function test_create_job_requires_edit_post_on_source(): void {
		$language = $this->add_language();
		$owner    = (int) self::factory()->user->create( array( 'role' => 'author' ) );
		$other    = (int) self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id  = (int) self::factory()->post->create(
			array(
				'post_author'  => $owner,
				'post_status'  => 'private',
				'post_type'    => 'post',
				'post_title'   => 'Private',
				'post_content' => 'Secret',
			)
		);

		wp_set_current_user( $other );

		$request = new WP_REST_Request( 'POST', '/aiml/v1/jobs' );
		$request->set_body_params(
			array(
				'job_type'       => JobTypes::TRANSLATE_SELECTED,
				'source_type'    => Store::SOURCE_POST,
				'source_id'      => $post_id,
				'language_id'    => (int) $language->language_id,
				'segment_keys'   => array( 'post_title' ),
				'prompt_profile' => 'default',
				'prompt_version' => '1',
			)
		);

		$response = rest_do_request( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * P2 A1 — multi-post Workspace create without manual segment keys.
	 */
	public function test_bulk_create_without_segment_keys_resolves_missing(): void {
		$language = $this->add_language();
		$post_a   = $this->create_block_page( '550e8400-e29b-41d4-a716-4466554400aa' );
		$post_b   = $this->create_block_page( '550e8400-e29b-41d4-a716-4466554400bb' );
		wp_set_current_user( $this->create_translator() );

		$request = new WP_REST_Request( 'POST', '/aiml/v1/jobs' );
		$request->set_body_params(
			array(
				'language_id'    => (int) $language->language_id,
				'prompt_profile' => 'default',
				'prompt_version' => '1',
				'posts'          => array(
					array( 'source_id' => (int) $post_a->ID ),
					array( 'source_id' => (int) $post_b->ID ),
				),
			)
		);

		$response = rest_do_request( $request );
		$this->assertSame( 201, $response->get_status(), wp_json_encode( $response->get_data() ) );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'batch_id', $data );
		$this->assertNotEmpty( $data['batch_id'] );
		$this->assertCount( 2, $data['jobs'] );

		foreach ( $data['jobs'] as $job ) {
			$this->assertSame( JobTypes::BULK_TRANSLATE, $job['job_type'] );
			$this->assertGreaterThan( 0, (int) $job['total_items'] );
			$this->assertArrayNotHasKey( 'prompt', $job );
		}
	}

	public function test_get_job_requires_edit_post_on_source(): void {
		$language = $this->add_language();
		$owner    = (int) self::factory()->user->create( array( 'role' => 'author' ) );
		$other    = (int) self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id  = (int) self::factory()->post->create(
			array(
				'post_author'  => $owner,
				'post_status'  => 'private',
				'post_type'    => 'post',
				'post_title'   => 'Private job',
				'post_content' => 'Secret',
			)
		);
		wp_set_current_user( $owner );
		$job = $this->seed_job( $post_id, (int) $language->language_id );

		wp_set_current_user( $other );

		$response = rest_do_request(
			new WP_REST_Request( 'GET', '/aiml/v1/jobs/' . (int) $job->job_id )
		);
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_idempotent_create_via_rest_returns_409_on_parameter_mismatch(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$body = array(
			'job_type'       => JobTypes::TRANSLATE_SELECTED,
			'source_type'    => Store::SOURCE_POST,
			'source_id'      => (int) $post->ID,
			'language_id'    => (int) $language->language_id,
			'segment_keys'   => array( $this->default_segment_key() ),
			'prompt_profile' => 'default',
			'prompt_version' => '1',
		);

		$first = new WP_REST_Request( 'POST', '/aiml/v1/jobs' );
		$first->set_body_params( $body );

		$first_response = rest_do_request( $first );
		$this->assertSame( 201, $first_response->get_status() );

		$this->job_repo->update(
			(int) $first_response->get_data()['job_id'],
			array(
				'provider_id' => 'tampered',
			)
		);

		$second = new WP_REST_Request( 'POST', '/aiml/v1/jobs' );
		$second->set_body_params( $body );
		$response = rest_do_request( $second );
		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'idempotency_conflict', $response->get_data()['code'] ?? '' );
	}

	public function test_create_job_returns_503_when_action_scheduler_unavailable(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$request = new WP_REST_Request( 'POST', '/aiml/v1/jobs' );
		$request->set_body_params(
			array(
				'job_type'       => JobTypes::TRANSLATE_SELECTED,
				'source_type'    => Store::SOURCE_POST,
				'source_id'      => (int) $post->ID,
				'language_id'    => (int) $language->language_id,
				'segment_keys'   => array( $this->default_segment_key() ),
				'prompt_profile' => 'default',
				'prompt_version' => '1',
			)
		);

		$controller = $this->build_jobs_controller( new UnavailableJobsSchedulerStub() );
		$result     = $controller->create_job( $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 503, $result->get_error_data()['status'] ?? 0 );
		$this->assertSame( 'action_scheduler_unavailable', $result->get_error_code() );
	}

	public function test_get_job_includes_bounded_item_errors_without_bodies(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$job      = $this->seed_job( (int) $post->ID, (int) $language->language_id );

		wp_set_current_user( $this->create_translator() );

		$response = rest_do_request(
			new WP_REST_Request( 'GET', '/aiml/v1/jobs/' . (int) $job->job_id )
		);
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'items', $data );
		$this->assertNotEmpty( $data['items'] );
		$item = $data['items'][0];
		$this->assertArrayHasKey( 'segment_key', $item );
		$this->assertArrayNotHasKey( 'translated_text', $item );
		$this->assertArrayNotHasKey( 'source_text', $item );
	}

	public function test_item_assessment_is_on_demand_and_does_not_mutate_status(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$job      = $this->seed_job( (int) $post->ID, (int) $language->language_id );
		$items    = ( new \AIMultilingual\Jobs\BackgroundTranslationItemRepository() )->list_by_job( (int) $job->job_id );
		$this->assertNotEmpty( $items );
		$item_id = (int) $items[0]->item_id;
		$status  = (string) $items[0]->status;
		$job_st  = (string) $job->status;

		wp_set_current_user( $this->create_translator() );

		$response = rest_do_request(
			new WP_REST_Request(
				'GET',
				'/aiml/v1/jobs/' . (int) $job->job_id . '/items/' . $item_id . '/assessment'
			)
		);
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$this->assertSame( (int) $job->job_id, (int) $data['job_id'] );
		$this->assertSame( $item_id, (int) $data['item_id'] );
		$this->assertIsArray( $data['assessment'] ?? null );
		$this->assertArrayHasKey( 'overall_category', $data['assessment'] );

		$fresh_job  = $this->job_repo->find( (int) $job->job_id );
		$fresh_item = ( new \AIMultilingual\Jobs\BackgroundTranslationItemRepository() )->find( $item_id );
		$this->assertSame( $job_st, (string) $fresh_job->status );
		$this->assertSame( $status, (string) $fresh_item->status );
	}

	/**
	 * Seeds one queued job directly (bypasses REST for fixture setup).
	 */
	private function seed_job( int $post_id, int $language_id ): object {
		$service = new BackgroundTranslationJobService(
			$this->job_repo,
			null,
			new JobLeaseService( $this->job_repo ),
			new JobProgressReconciler( $this->job_repo )
		);

		$job = $service->create_job(
			array(
				'job_type'       => JobTypes::TRANSLATE_SELECTED,
				'source_type'    => Store::SOURCE_POST,
				'source_id'      => $post_id,
				'language_id'    => $language_id,
				'segment_keys'   => array( $this->default_segment_key() ),
				'prompt_profile' => 'default',
				'prompt_version' => '1',
				'created_by'     => get_current_user_id(),
				'force_new'      => true,
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $job );

		return $job;
	}

	/**
	 * Defines minimal Action Scheduler stubs for REST create tests.
	 */
	private function define_action_scheduler_stubs(): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			/**
			 * @param mixed ...$args Unused Action Scheduler args.
			 */
			function as_enqueue_async_action( ...$args ): int { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return 1;
			}
		}

		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			/**
			 * @param mixed ...$args Unused Action Scheduler args.
			 */
			function as_schedule_single_action( ...$args ): int { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return 1;
			}
		}

		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			/**
			 * @param mixed ...$args Unused Action Scheduler args.
			 */
			function as_has_scheduled_action( ...$args ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return false;
			}
		}

		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			/**
			 * @param mixed ...$args Unused Action Scheduler args.
			 */
			function as_schedule_recurring_action( ...$args ): int { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return 1;
			}
		}
	}

	/**
	 * Builds a jobs REST controller with an injectable scheduler.
	 */
	private function build_jobs_controller( BackgroundTranslationScheduler $scheduler ): JobsController {
		$jobs    = new BackgroundTranslationJobService( $this->job_repo, null, new JobLeaseService( $this->job_repo ), new JobProgressReconciler( $this->job_repo ), null, null, $scheduler );
		$batches = new BackgroundTranslationBatchCoordinator( $jobs, $this->job_repo, $scheduler );
		$worker  = new BackgroundTranslationWorker(
			new \AIMultilingual\Jobs\BackgroundTranslationItemProcessor(
				$this->store,
				new \AIMultilingual\Workspace\TranslationService( $this->store, new \AIMultilingual\Workspace\SegmentAssembler( $this->extractor, $this->store, new \AIMultilingual\Block\BlockRegistry( new \AIMultilingual\Block\AdapterRegistry() ) ), $this->languages, new EchoAIProvider() ),
				new \AIMultilingual\Glossary\GlossaryService( new \AIMultilingual\Glossary\GlossaryRepository(), new \AIMultilingual\Glossary\GlossaryNormalizer(), new \AIMultilingual\Glossary\GlossaryMatcher( new \AIMultilingual\Glossary\GlossaryNormalizer() ) ),
				new \AIMultilingual\Workspace\SegmentAssembler( $this->extractor, $this->store, new \AIMultilingual\Block\BlockRegistry( new \AIMultilingual\Block\AdapterRegistry() ) )
			),
			$jobs,
			$this->job_repo,
			null,
			null,
			null,
			null,
			null,
			$scheduler
		);

		return new JobsController( $jobs, $batches, $scheduler, $worker, $this->languages );
	}
}
