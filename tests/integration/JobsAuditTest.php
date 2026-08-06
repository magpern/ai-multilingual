<?php
/**
 * Background translation job audit integration tests (J7 / plan §22).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Jobs\BackgroundTranslationJobAuditEvents;
use AIMultilingual\Jobs\BackgroundTranslationJobAuditLogger;
use AIMultilingual\Jobs\BackgroundTranslationJobRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobService;
use AIMultilingual\Jobs\JobLeaseService;
use AIMultilingual\Jobs\JobProgressReconciler;
use AIMultilingual\Jobs\JobStatuses;
use AIMultilingual\Jobs\JobTypes;

/**
 * Job lifecycle emits safe `aiml_translation_job_audit` events.
 */
final class JobsAuditTest extends AimlTestCase {

	private BackgroundTranslationJobService $service;

	private BackgroundTranslationJobAuditLogger $audit;

	/** @var list<array{0: string, 1: array<string, mixed>}> */
	private array $captured = array();

	protected function setUp(): void {
		parent::setUp();

		$jobs          = new BackgroundTranslationJobRepository();
		$this->audit   = new BackgroundTranslationJobAuditLogger();
		$this->service = new BackgroundTranslationJobService(
			$jobs,
			null,
			new JobLeaseService( $jobs ),
			new JobProgressReconciler( $jobs ),
			null,
			null,
			null,
			null,
			null,
			$this->audit
		);
	}

	/**
	 * @param callable(): void $callback Callback under test.
	 * @return list<array{0: string, 1: array<string, mixed>}>
	 */
	private function capture_audit( callable $callback ): array {
		$events   = array();
		$listener = static function ( string $event, array $payload ) use ( &$events ): void {
			$events[] = array( $event, $payload );
		};

		add_action( 'aiml_translation_job_audit', $listener, 10, 2 );
		$callback();
		remove_action( 'aiml_translation_job_audit', $listener, 10 );

		return $events;
	}

	/**
	 * @param list<array{0: string, 1: array<string, mixed>}> $events Captured events.
	 */
	private function find_event( array $events, string $name ): ?array {
		foreach ( $events as $entry ) {
			if ( $entry[0] === $name ) {
				return $entry[1];
			}
		}

		return null;
	}

	public function test_create_emits_translation_job_created_without_bodies(): void {
		$events = $this->capture_audit(
			function (): void {
				$this->service->create_job(
					array(
						'job_type'       => JobTypes::TRANSLATE_SELECTED,
						'source_type'    => 'post',
						'source_id'      => 9001,
						'language_id'    => 2,
						'segment_keys'   => array( 'title' ),
						'provider_id'    => 'openai',
						'prompt_profile' => 'default',
						'prompt_version' => '1',
						'created_by'     => 1,
					)
				);
			}
		);

		$created = $this->find_event( $events, BackgroundTranslationJobAuditEvents::CREATED );
		$this->assertNotNull( $created );
		$this->assertArrayNotHasKey( 'translated_text', $created );
		$this->assertArrayNotHasKey( 'prompt', $created );
		$this->assertSame( 'translate_selected', $created['job_type'] );
	}

	public function test_resume_emits_translation_job_resumed(): void {
		$job = $this->service->create_job(
			array(
				'job_type'       => JobTypes::TRANSLATE_SELECTED,
				'source_type'    => 'post',
				'source_id'      => 9002,
				'language_id'    => 2,
				'segment_keys'   => array( 'title' ),
				'provider_id'    => 'openai',
				'prompt_profile' => 'default',
				'prompt_version' => '1',
				'created_by'     => 1,
			)
		);
		$this->assertIsObject( $job );

		$jobs = new BackgroundTranslationJobRepository();
		$jobs->update( (int) $job->job_id, array( 'status' => JobStatuses::PAUSED ) );

		$events = $this->capture_audit(
			function () use ( $job ): void {
				$this->service->resume( (int) $job->job_id );
			}
		);

		$this->assertNotNull( $this->find_event( $events, BackgroundTranslationJobAuditEvents::RESUMED ) );
	}
}
