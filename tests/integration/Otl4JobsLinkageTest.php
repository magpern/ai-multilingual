<?php
/**
 * OTL.4 Jobs linkage and list performance integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Jobs\BackgroundTranslationBudgetPolicy;
use AIMultilingual\Jobs\BackgroundTranslationItemRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobService;
use AIMultilingual\Jobs\BackgroundTranslationScheduler;
use AIMultilingual\Jobs\ItemStatuses;
use AIMultilingual\Jobs\JobLeaseService;
use AIMultilingual\Jobs\JobProgressReconciler;
use AIMultilingual\Jobs\JobStatuses;
use AIMultilingual\Jobs\JobTypes;
use AIMultilingual\Jobs\JobsLifecycleLinker;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\SegmentAssembler;
use WP_Error;

/**
 * Bounded domain linkage + list Jobs enrichment = 0.
 */
final class Otl4JobsLinkageTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	private BackgroundTranslationJobService $job_service;

	private BackgroundTranslationJobRepository $jobs;

	private BackgroundTranslationItemRepository $items;

	private JobsLifecycleLinker $linker;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();

		$this->jobs  = new BackgroundTranslationJobRepository();
		$this->items = new BackgroundTranslationItemRepository();

		$this->job_service = $this->build_job_service( new AvailableSchedulerStub() );
		$this->linker      = new JobsLifecycleLinker( $this->jobs, $this->items );
	}

	public function test_association_found_within_scan_window(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();
		$job      = $this->create_selected_job( (int) $post->ID, (int) $language->language_id, array( $key ) );
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$row     = $this->row_for( (int) $post->ID, (int) $language->language_id, $key );
		$payload = $this->linker->link_for_translation(
			$row,
			array(
				'can_view_jobs'   => true,
				'can_run_jobs'    => true,
				'can_cancel_jobs' => true,
			)
		);
		$this->assertIsArray( $payload );
		$this->assertNotNull( $payload['association'] );
		$this->assertTrue( $payload['lookup']['matched'] );
		$this->assertFalse( $payload['lookup']['exhausted'] );
		$this->assertSame( JobsLifecycleLinker::LOOKUP_JOB_SCAN_LIMIT, $payload['lookup']['job_scan_limit'] );
		$this->assertSame( (int) $job->job_id, (int) $payload['association']['job']['job_id'] );
		$this->assertArrayNotHasKey( 'selection_rule', $payload );
		$this->assertArrayNotHasKey( 'active_lock_key', $payload['association']['job'] );
		$this->assertArrayNotHasKey( 'last_error_message', $payload['association']['job'] );
		$this->assertArrayNotHasKey( 'last_error_message', $payload['association']['item'] );
		$this->assertNotEmpty( $payload['association']['operations'] );
	}

	public function test_no_match_within_window(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$this->create_selected_job( (int) $post->ID, (int) $language->language_id, array( $this->default_segment_key() ) );

		$row     = (object) array(
			'source_type' => Store::SOURCE_POST,
			'source_id'   => (int) $post->ID,
			'language_id' => (int) $language->language_id,
			'segment_key' => 'missing_segment_key',
		);
		$payload = $this->linker->link_for_translation(
			$row,
			array(
				'can_view_jobs'   => true,
				'can_run_jobs'    => true,
				'can_cancel_jobs' => true,
			)
		);
		$this->assertNull( $payload['association'] );
		$this->assertFalse( $payload['lookup']['matched'] );
		$this->assertFalse( $payload['lookup']['exhausted'] );
	}

	public function test_exhausted_window_when_match_outside_limit(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();

		$old_job_id = $this->insert_terminal_job_with_item(
			(int) $post->ID,
			(int) $language->language_id,
			$key,
			1
		);

		for ( $i = 0; $i < JobsLifecycleLinker::LOOKUP_JOB_SCAN_LIMIT; $i++ ) {
			$this->insert_terminal_job_with_item(
				(int) $post->ID,
				(int) $language->language_id,
				'noise_seg_' . $i,
				100 + $i
			);
		}

		$row     = $this->row_for( (int) $post->ID, (int) $language->language_id, $key );
		$payload = $this->linker->link_for_translation(
			$row,
			array(
				'can_view_jobs'   => true,
				'can_run_jobs'    => true,
				'can_cancel_jobs' => true,
			)
		);
		$this->assertNull( $payload['association'], 'Older matching job outside scan window must not associate' );
		$this->assertTrue( $payload['lookup']['exhausted'] );
		$this->assertFalse( $payload['lookup']['matched'] );
		$this->assertGreaterThan( 0, $old_job_id );
	}

	public function test_denied_without_view_cap_returns_null(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();
		$this->create_selected_job( (int) $post->ID, (int) $language->language_id, array( $key ) );
		$row = $this->row_for( (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNull(
			$this->linker->link_for_translation( $row, array( 'can_view_jobs' => false ) )
		);
	}

	public function test_list_assembler_does_not_invoke_jobs_linker(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Hello', '<p>Body</p>' );
		$this->translate( $post, $language, 'post_title', 'Hej' );

		$publication = new \AIMultilingual\Translation\Publication\PublicationService(
			$this->store,
			new \AIMultilingual\Translation\Assessment\AssessmentAssembler(),
			new \AIMultilingual\Translation\Publication\PublicationPolicy(),
			new \AIMultilingual\Translation\Publication\PublicationAuditLogger(),
			new Settings()
		);
		$preview     = new \AIMultilingual\Workspace\PreviewService(
			$this->languages,
			$this->context,
			$this->make_router()
		);
		$assembler   = new \AIMultilingual\Workspace\Operator\OperatorTranslationAssembler(
			$this->store,
			$this->languages,
			new \AIMultilingual\Workspace\Operator\AllowedActionsResolver(),
			$preview,
			new \AIMultilingual\Translation\Assessment\AssessmentAssembler(),
			new \AIMultilingual\Workspace\QA\QAEngine(),
			new \AIMultilingual\Translation\AI\FieldSemanticMapper(),
			$publication,
			$this->linker
		);
		$assembler->reset_invocation_counts();

		$result = $this->store->query_operations(
			array(
				'language_id' => (int) $language->language_id,
				'page'        => 1,
				'per_page'    => 20,
			)
		);
		foreach ( $result['items'] as $row ) {
			$item = $assembler->assemble_list_item( $row );
			$this->assertNull( $item['jobs'] );
		}
		$counts = $assembler->invocation_counts();
		$this->assertSame( 0, $counts['jobs'] );
		$this->assertSame( 0, $counts['assessment'] );
	}

	/**
	 * @param int      $post_id     Post id.
	 * @param int      $language_id Language id.
	 * @param string[] $keys        Segment keys.
	 * @return object|WP_Error
	 */
	private function create_selected_job( int $post_id, int $language_id, array $keys ) {
		return $this->job_service->create_job(
			array(
				'job_type'       => JobTypes::TRANSLATE_SELECTED,
				'source_type'    => Store::SOURCE_POST,
				'source_id'      => $post_id,
				'language_id'    => $language_id,
				'segment_keys'   => $keys,
				'provider_id'    => 'echo',
				'prompt_profile' => 'default',
				'prompt_version' => '1',
				'created_by'     => 1,
			)
		);
	}

	/**
	 * Inserts a terminal job+item pair bypassing create locks (for scan-window fixtures).
	 *
	 * @param int    $post_id     Post id.
	 * @param int    $language_id Language id.
	 * @param string $segment_key Segment key.
	 * @param int    $salt        Unique salt for idempotency.
	 */
	private function insert_terminal_job_with_item( int $post_id, int $language_id, string $segment_key, int $salt ): int {
		$now = current_time( 'mysql', true );
		$job = $this->jobs->insert(
			array(
				'job_type'        => JobTypes::TRANSLATE_SELECTED,
				'status'          => JobStatuses::COMPLETED,
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'language_id'     => $language_id,
				'lock_key'        => 'post:' . $post_id . ':' . $language_id . ':hist:' . $salt,
				'active_lock_key' => null,
				'idempotency_key' => 'otl4-hist-' . $post_id . '-' . $language_id . '-' . $salt,
				'total_items'     => 1,
				'completed_items' => 1,
				'failed_items'    => 0,
				'provider_id'     => 'echo',
				'prompt_profile'  => 'default',
				'prompt_version'  => '1',
				'created_by'      => 1,
				'created_at'      => $now,
				'updated_at'      => $now,
				'finished_at'     => $now,
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $job );
		$job_id = (int) $job->job_id;
		$this->items->insert(
			array(
				'job_id'      => $job_id,
				'segment_key' => $segment_key,
				'status'      => ItemStatuses::COMPLETED,
			)
		);

		return $job_id;
	}

	/**
	 * @param int    $post_id     Post id.
	 * @param int    $language_id Language id.
	 * @param string $key         Segment key.
	 */
	private function row_for( int $post_id, int $language_id, string $key ): object {
		$row = $this->store->get( Store::SOURCE_POST, $post_id, $language_id, $key );
		if ( null === $row ) {
			return (object) array(
				'source_type' => Store::SOURCE_POST,
				'source_id'   => $post_id,
				'language_id' => $language_id,
				'segment_key' => $key,
			);
		}

		return $row;
	}

	private function build_job_service( BackgroundTranslationScheduler $scheduler ): BackgroundTranslationJobService {
		$settings         = new Settings();
		$adapter_registry = new AdapterRegistry();
		$block_registry   = new BlockRegistry( $adapter_registry );
		$extractor        = new \AIMultilingual\Translation\Extractor(
			$settings,
			new \AIMultilingual\Translation\BlockExtractor(
				$adapter_registry,
				$block_registry,
				new BlockExtractionLogger()
			)
		);
		$assembler        = new SegmentAssembler( $extractor, $this->store, $block_registry );
		$leases           = new JobLeaseService( $this->jobs, $this->items );
		$reconcile        = new JobProgressReconciler( $this->jobs, $this->items );
		$budget           = new BackgroundTranslationBudgetPolicy( $this->jobs );

		return new BackgroundTranslationJobService(
			$this->jobs,
			$this->items,
			$leases,
			$reconcile,
			$this->store,
			$assembler,
			$scheduler,
			$budget,
			null
		);
	}
}
