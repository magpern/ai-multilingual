<?php
/**
 * Background Translation Jobs J4 integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Glossary\GlossaryMatcher;
use AIMultilingual\Glossary\GlossaryNormalizer;
use AIMultilingual\Glossary\GlossaryRepository;
use AIMultilingual\Glossary\GlossaryService;
use AIMultilingual\Jobs\BackgroundTranslationBatchCoordinator;
use AIMultilingual\Jobs\BackgroundTranslationBudgetPolicy;
use AIMultilingual\Jobs\BackgroundTranslationItemProcessor;
use AIMultilingual\Jobs\BackgroundTranslationItemRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobService;
use AIMultilingual\Jobs\BackgroundTranslationRetryPolicy;
use AIMultilingual\Jobs\BackgroundTranslationScheduler;
use AIMultilingual\Jobs\BackgroundTranslationWorker;
use AIMultilingual\Jobs\ItemStatuses;
use AIMultilingual\Jobs\JobLeaseService;
use AIMultilingual\Jobs\JobProgressReconciler;
use AIMultilingual\Jobs\JobStatuses;
use AIMultilingual\Jobs\JobTypes;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\SegmentAssembler;
use AIMultilingual\Workspace\TranslationService;
use WP_Error;

/**
 * J4 retry, budget, and AS health integration coverage.
 */
final class JobsRetryBudgetTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	private BackgroundTranslationJobService $job_service;

	private BackgroundTranslationWorker $worker;

	private BackgroundTranslationItemRepository $items;

	private BackgroundTranslationJobRepository $jobs;

	public function test_create_job_rejects_when_action_scheduler_unavailable(): void {
		$unavailable = new UnavailableJobsSchedulerStub();
		$service     = $this->build_job_service( $unavailable );
		$language    = $this->add_language();
		$post        = $this->create_block_page();

		$result = $service->create_job(
			array(
				'job_type'       => JobTypes::TRANSLATE_SELECTED,
				'source_type'    => Store::SOURCE_POST,
				'source_id'      => (int) $post->ID,
				'language_id'    => (int) $language->language_id,
				'segment_keys'   => array( $this->default_segment_key() ),
				'provider_id'    => 'echo',
				'prompt_profile' => 'default',
				'prompt_version' => '1',
				'created_by'     => 1,
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'action_scheduler_unavailable', $result->get_error_code() );
	}

	public function test_batch_create_rejects_when_action_scheduler_unavailable(): void {
		$unavailable = new UnavailableJobsSchedulerStub();
		$service     = $this->build_job_service( $unavailable );
		$batch       = new BackgroundTranslationBatchCoordinator( $service, new BackgroundTranslationJobRepository(), $unavailable );
		$language    = $this->add_language();
		$post        = $this->create_block_page();

		$result = $batch->create_bulk(
			array(
				array(
					'source_type'  => Store::SOURCE_POST,
					'source_id'    => (int) $post->ID,
					'segment_keys' => array( $this->default_segment_key() ),
				),
			),
			(int) $language->language_id,
			array(
				'provider_id'    => 'echo',
				'prompt_profile' => 'default',
				'prompt_version' => '1',
				'created_by'     => 1,
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'action_scheduler_unavailable', $result->get_error_code() );
	}

	public function test_successful_item_retained_after_budget_overrun(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key_a    = $this->default_segment_key();

		$job = $this->job_service->create_job(
			array(
				'job_type'            => JobTypes::TRANSLATE_SELECTED,
				'source_type'         => Store::SOURCE_POST,
				'source_id'           => (int) $post->ID,
				'language_id'         => (int) $language->language_id,
				'segment_keys'        => array( $key_a ),
				'budget_max_requests' => 1,
				'provider_id'         => 'echo',
				'prompt_profile'      => 'default',
				'prompt_version'      => '1',
				'created_by'          => 1,
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$first_run = $this->worker->run( (int) $job->job_id, 'budget-worker-a' );
		$this->assertNotInstanceOf( WP_Error::class, $first_run );
		$this->assertSame( JobStatuses::COMPLETED, $first_run->status );

		$key_b = 'content:block:99999999-9999-4999-8999-999999999999';
		$this->seed_second_segment( $post, $key_b );
		$this->items->insert(
			array(
				'job_id'      => (int) $job->job_id,
				'segment_key' => $key_b,
				'status'      => ItemStatuses::QUEUED,
			)
		);
		$this->jobs->update(
			(int) $job->job_id,
			array(
				'status'          => JobStatuses::QUEUED,
				'finished_at'     => null,
				'queued_items'    => 1,
				'completed_items' => 1,
				'total_items'     => 2,
			)
		);

		$second_run = $this->worker->run( (int) $job->job_id, 'budget-worker-b' );
		$this->assertNotInstanceOf( WP_Error::class, $second_run );
		$this->assertSame( JobStatuses::PAUSED, $second_run->status );
		$this->assertSame( 'budget_exceeded', $second_run->last_error_code );

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key_a );
		$this->assertNotNull( $row );
		$this->assertSame( Store::STATUS_MACHINE_TRANSLATED, $row->status );

		$fresh_job = $this->jobs->find( (int) $job->job_id );
		$this->assertNotNull( $fresh_job );
		$this->assertSame( 1, (int) $fresh_job->budget_used_requests );

		$items = $this->items->list_by_job( (int) $job->job_id );
		$this->assertSame( 1, $this->count_items_by_status( $items, ItemStatuses::COMPLETED ) );
		$this->assertSame( 1, $this->count_items_by_status( $items, ItemStatuses::QUEUED ) );
	}

	public function test_duplicate_callback_while_lease_held(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();

		$job = $this->job_service->create_job(
			array(
				'job_type'       => JobTypes::TRANSLATE_SELECTED,
				'source_type'    => Store::SOURCE_POST,
				'source_id'      => (int) $post->ID,
				'language_id'    => (int) $language->language_id,
				'segment_keys'   => array( $key ),
				'provider_id'    => 'echo',
				'prompt_profile' => 'default',
				'prompt_version' => '1',
				'created_by'     => 1,
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$leases = new JobLeaseService( $this->jobs, $this->items );
		$claim  = $leases->claim( (int) $job->job_id, 'holder-token', 300 );
		$this->assertNotInstanceOf( WP_Error::class, $claim );

		$duplicate = $this->worker->run( (int) $job->job_id, 'other-token' );
		$this->assertInstanceOf( WP_Error::class, $duplicate );
		$this->assertSame( 'lease_held', $duplicate->get_error_code() );
	}

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();

		$this->jobs  = new BackgroundTranslationJobRepository();
		$this->items = new BackgroundTranslationItemRepository();

		$available         = new AvailableSchedulerStub();
		$this->job_service = $this->build_job_service( $available );
		$this->worker      = $this->build_worker( $available, $this->job_service );
	}

	/**
	 * @param array<object> $items Item rows.
	 */
	private function count_items_by_status( array $items, string $status ): int {
		$count = 0;
		foreach ( $items as $item ) {
			if ( $status === (string) $item->status ) {
				++$count;
			}
		}

		return $count;
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

	private function build_worker(
		BackgroundTranslationScheduler $scheduler,
		BackgroundTranslationJobService $job_service
	): BackgroundTranslationWorker {
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
		$glossary         = new GlossaryService(
			new GlossaryRepository(),
			new GlossaryNormalizer(),
			new GlossaryMatcher( new GlossaryNormalizer() )
		);
		$translation      = new TranslationService(
			$this->store,
			$assembler,
			$this->languages,
			new EchoAIProvider(),
			null,
			null,
			$glossary
		);
		$processor        = new BackgroundTranslationItemProcessor(
			$this->store,
			$translation,
			$glossary,
			$assembler,
			new BackgroundTranslationRetryPolicy()
		);
		$leases           = new JobLeaseService( $this->jobs, $this->items );
		$reconcile        = new JobProgressReconciler( $this->jobs, $this->items );
		$budget           = new BackgroundTranslationBudgetPolicy( $this->jobs );

		return new BackgroundTranslationWorker(
			$processor,
			$job_service,
			$this->jobs,
			$this->items,
			$leases,
			$reconcile,
			new BackgroundTranslationRetryPolicy(),
			$budget,
			$scheduler,
			null
		);
	}

	/**
	 * Seed a second editable block segment on a post for multi-item jobs.
	 *
	 * @param \WP_Post $post Post fixture.
	 * @param string   $key  Segment key.
	 */
	private function seed_second_segment( \WP_Post $post, string $key ): void {
		global $wpdb;

		$content = '<!-- wp:paragraph {"aimlUuid":"99999999-9999-4999-8999-999999999999"} -->'
			. '<p>Second segment body</p>'
			. '<!-- /wp:paragraph -->';

		wp_update_post(
			array(
				'ID'           => (int) $post->ID,
				'post_content' => $post->post_content . "\n" . $content,
			)
		);

		unset( $wpdb );
	}
}
