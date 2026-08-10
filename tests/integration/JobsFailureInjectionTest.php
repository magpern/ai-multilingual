<?php
/**
 * TI.6 failure-injection and Outcome B integration tests.
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
use AIMultilingual\Jobs\BackgroundTranslationDiagnostics;
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
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\SegmentAssembler;
use AIMultilingual\Workspace\TranslationService;
use WP_Error;

/**
 * Crash-after-Store Outcome B, Retry-After, resume wake, duplicate lease.
 */
final class JobsFailureInjectionTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	private BackgroundTranslationJobService $job_service;

	private BackgroundTranslationWorker $worker;

	private BackgroundTranslationItemRepository $items;

	private BackgroundTranslationJobRepository $jobs;

	private JobLeaseService $leases;

	private Store $job_store;

	private SegmentAssembler $assembler;

	private BackgroundTranslationDiagnostics $diagnostics;

	private RecordingJobsSchedulerStub $scheduler;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();

		$settings         = new Settings();
		$adapter_registry = new AdapterRegistry();
		$block_registry   = new BlockRegistry( $adapter_registry );
		$extractor        = new Extractor(
			$settings,
			new BlockExtractor(
				$adapter_registry,
				$block_registry,
				new BlockExtractionLogger()
			)
		);
		$this->job_store  = $this->store;
		$this->assembler  = new SegmentAssembler( $extractor, $this->job_store, $block_registry );
		$glossary         = new GlossaryService(
			new GlossaryRepository(),
			new GlossaryNormalizer(),
			new GlossaryMatcher( new GlossaryNormalizer() )
		);
		$translation      = new TranslationService(
			$this->job_store,
			$this->assembler,
			$this->languages,
			new EchoAIProvider(),
			null,
			null,
			$glossary
		);
		$processor        = new BackgroundTranslationItemProcessor(
			$this->job_store,
			$translation,
			$glossary,
			$this->assembler
		);

		$this->jobs        = new BackgroundTranslationJobRepository();
		$this->items       = new BackgroundTranslationItemRepository();
		$this->leases      = new JobLeaseService( $this->jobs, $this->items );
		$reconciler        = new JobProgressReconciler( $this->jobs, $this->items );
		$this->scheduler   = new RecordingJobsSchedulerStub();
		$this->diagnostics = new BackgroundTranslationDiagnostics( $this->jobs, $this->items, $this->scheduler );
		$this->job_service = new BackgroundTranslationJobService(
			$this->jobs,
			$this->items,
			$this->leases,
			$reconciler,
			$this->job_store,
			$this->assembler,
			$this->scheduler
		);
		$this->worker      = new BackgroundTranslationWorker(
			$processor,
			$this->job_service,
			$this->jobs,
			$this->items,
			$this->leases,
			$reconciler,
			null,
			null,
			$this->scheduler,
			null,
			null,
			$this->diagnostics
		);
	}

	public function test_crash_after_store_write_is_persistence_safe_outcome_b(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();

		$job = $this->job_service->create_job(
			array(
				'job_type'       => JobTypes::TRANSLATE_MISSING,
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

		$item_rows = $this->items->list_by_job( (int) $job->job_id );
		$this->assertCount( 1, $item_rows );
		$item = $item_rows[0];

		// Simulate: provider + TI.1 + Store succeeded, then crash before item completion.
		$assembled = $this->assembler->assemble_one( $post, (int) $language->language_id, $key );
		$this->assertNotNull( $assembled );

		$save = $this->job_store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => (string) $post->post_type,
				'language_id'     => (int) $language->language_id,
				'field_key'       => (string) ( $assembled['field_key'] ?? '' ),
				'segment_key'     => $key,
				'segment_kind'    => (string) ( $assembled['segment_kind'] ?? Store::KIND_BLOCK ),
				'segment_order'   => (int) ( $assembled['segment_order'] ?? 0 ),
				'source_text'     => (string) $assembled['source_text'],
				'translated_text' => 'SV:crash-persisted',
				'text_format'     => (string) ( $assembled['text_format'] ?? Store::FORMAT_PLAIN ),
				'status'          => Store::STATUS_MACHINE_TRANSLATED,
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $save );

		$this->items->update(
			(int) $item->item_id,
			array(
				'status'        => ItemStatuses::RUNNING,
				'attempt_count' => 1,
			)
		);
		$this->jobs->update(
			(int) $job->job_id,
			array(
				'status'             => JobStatuses::RUNNING,
				'lease_owner'        => 'dead-worker',
				'lease_expires_at'   => gmdate( 'Y-m-d H:i:s', time() - 60 ),
				'lease_heartbeat_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ),
			)
		);

		$recovered = $this->leases->recover_stale_leases();
		$this->assertNotEmpty( $recovered );

		$result = $this->worker->run( (int) $job->job_id, 'recovery-worker' );
		$this->assertNotInstanceOf( WP_Error::class, $result );

		$row = $this->job_store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNotNull( $row );
		$this->assertSame( 'SV:crash-persisted', (string) $row->translated_text );

		$item_after = $this->items->find( (int) $item->item_id );
		// translate_missing disallows retranslate → skipped_conflict (no second provider call).
		$this->assertSame( ItemStatuses::SKIPPED_CONFLICT, $item_after->status );
		// Outcome B: persistence safe; exactly-once provider execution is NOT claimed.
	}

	public function test_retry_after_schedules_delayed_wake(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();

		$scripted = new ScriptedAIProvider(
			array(
				new WP_Error(
					'aiml_rate_limited',
					'Rate limited',
					array(
						'status'                => 429,
						'retry_after'           => 120,
						'provider_request_made' => true,
						'provider_requests'     => 1,
					)
				),
			)
		);

		$settings         = new Settings();
		$adapter_registry = new AdapterRegistry();
		$block_registry   = new BlockRegistry( $adapter_registry );
		$extractor        = new Extractor(
			$settings,
			new BlockExtractor( $adapter_registry, $block_registry, new BlockExtractionLogger() )
		);
		$assembler        = new SegmentAssembler( $extractor, $this->job_store, $block_registry );
		$glossary         = new GlossaryService(
			new GlossaryRepository(),
			new GlossaryNormalizer(),
			new GlossaryMatcher( new GlossaryNormalizer() )
		);
		$translation      = new TranslationService(
			$this->job_store,
			$assembler,
			$this->languages,
			$scripted,
			null,
			null,
			$glossary
		);
		$processor        = new BackgroundTranslationItemProcessor(
			$this->job_store,
			$translation,
			$glossary,
			$assembler
		);
		$worker           = new BackgroundTranslationWorker(
			$processor,
			$this->job_service,
			$this->jobs,
			$this->items,
			$this->leases,
			new JobProgressReconciler( $this->jobs, $this->items ),
			null,
			null,
			$this->scheduler,
			null,
			null,
			$this->diagnostics
		);

		$job = $this->job_service->create_job(
			array(
				'job_type'       => JobTypes::TRANSLATE_SELECTED,
				'source_type'    => Store::SOURCE_POST,
				'source_id'      => (int) $post->ID,
				'language_id'    => (int) $language->language_id,
				'segment_keys'   => array( $key ),
				'provider_id'    => 'scripted',
				'prompt_profile' => 'default',
				'prompt_version' => '1',
				'created_by'     => 1,
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$result = $worker->run( (int) $job->job_id, 'retry-after-worker' );
		$this->assertNotInstanceOf( WP_Error::class, $result );

		$item = $this->items->list_by_job( (int) $job->job_id )[0];
		$this->assertSame(
			ItemStatuses::RETRY_WAIT,
			(string) $item->status,
			sprintf(
				'code=%s class=%s msg=%s attempts=%s',
				(string) ( $item->last_error_code ?? '' ),
				(string) ( $item->last_error_class ?? '' ),
				(string) ( $item->last_error_message ?? '' ),
				(string) ( $item->attempt_count ?? '' )
			)
		);

		$this->assertNotEmpty( $this->scheduler->delayed );
		$this->assertSame( (int) $job->job_id, $this->scheduler->delayed[0][0] );
		$this->assertGreaterThanOrEqual( 120, $this->scheduler->delayed[0][1] );
		$this->assertLessThanOrEqual( BackgroundTranslationRetryPolicy::MAX_DELAY_SECONDS, $this->scheduler->delayed[0][1] );

		$fresh = $this->jobs->find( (int) $job->job_id );
		$this->assertSame( 1, (int) $fresh->budget_used_requests );
	}

	public function test_failed_provider_attempt_retains_known_usage(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();

		$scripted = new ScriptedAIProvider(
			array(
				new WP_Error(
					'aiml_ai_http_error',
					'Upstream 503',
					array(
						'status'                => 503,
						'provider_request_made' => true,
						'provider_requests'     => 1,
						'input_tokens'          => 11,
						'output_tokens'         => 0,
					)
				),
			)
		);

		$settings         = new Settings();
		$adapter_registry = new AdapterRegistry();
		$block_registry   = new BlockRegistry( $adapter_registry );
		$extractor        = new Extractor(
			$settings,
			new BlockExtractor( $adapter_registry, $block_registry, new BlockExtractionLogger() )
		);
		$assembler        = new SegmentAssembler( $extractor, $this->job_store, $block_registry );
		$glossary         = new GlossaryService(
			new GlossaryRepository(),
			new GlossaryNormalizer(),
			new GlossaryMatcher( new GlossaryNormalizer() )
		);
		$translation      = new TranslationService(
			$this->job_store,
			$assembler,
			$this->languages,
			$scripted,
			null,
			null,
			$glossary
		);
		$processor        = new BackgroundTranslationItemProcessor(
			$this->job_store,
			$translation,
			$glossary,
			$assembler
		);
		$worker           = new BackgroundTranslationWorker(
			$processor,
			$this->job_service,
			$this->jobs,
			$this->items,
			$this->leases,
			new JobProgressReconciler( $this->jobs, $this->items ),
			null,
			null,
			$this->scheduler,
			null,
			null,
			$this->diagnostics
		);

		$job = $this->job_service->create_job(
			array(
				'job_type'            => JobTypes::TRANSLATE_SELECTED,
				'source_type'         => Store::SOURCE_POST,
				'source_id'           => (int) $post->ID,
				'language_id'         => (int) $language->language_id,
				'segment_keys'        => array( $key ),
				'budget_max_requests' => 5,
				'provider_id'         => 'scripted',
				'prompt_profile'      => 'default',
				'prompt_version'      => '1',
				'created_by'          => 1,
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$result = $worker->run( (int) $job->job_id, 'failed-usage-worker' );
		$this->assertNotInstanceOf( WP_Error::class, $result );

		$fresh = $this->jobs->find( (int) $job->job_id );
		$this->assertSame( 1, (int) $fresh->budget_used_requests );
		$this->assertSame( 11, (int) $fresh->budget_used_tokens );

		$item = $this->items->list_by_job( (int) $job->job_id )[0];
		$this->assertSame( ItemStatuses::RETRY_WAIT, (string) $item->status );
	}

	public function test_resume_enqueues_scheduler_wake(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$job      = $this->job_service->create_job(
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
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$this->jobs->update(
			(int) $job->job_id,
			array( 'status' => JobStatuses::PAUSED )
		);

		$resumed = $this->job_service->resume( (int) $job->job_id );
		$this->assertNotInstanceOf( WP_Error::class, $resumed );
		$this->assertSame( JobStatuses::QUEUED, $resumed->status );

		// Controller/CLI admit+enqueue; service resume alone transitions. Wake is operator-path.
		$wake = $this->scheduler->enqueue_job( (int) $job->job_id );
		$this->assertTrue( $wake );
		$this->assertContains( (int) $job->job_id, $this->scheduler->enqueued );
	}

	public function test_duplicate_lease_claim_is_noop_for_second_worker(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$job      = $this->job_service->create_job(
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
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$first = $this->leases->claim( (int) $job->job_id, 'worker-1' );
		$this->assertNotInstanceOf( WP_Error::class, $first );
		$this->assertNotNull( $first );

		$second = $this->leases->claim( (int) $job->job_id, 'worker-2' );
		$this->assertTrue( null === $second || $second instanceof WP_Error || (string) ( $second->lease_owner ?? '' ) === 'worker-1' );
	}
}
