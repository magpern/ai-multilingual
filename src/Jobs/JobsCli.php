<?php
/**
 * WP-CLI background translation job operator commands (plan §20).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use AIMultilingual\Translation\Store;
use WP_CLI;

/**
 * Shared operator CLI for background translation jobs.
 */
final class JobsCli {

	/**
	 * Job domain service.
	 *
	 * @var BackgroundTranslationJobService
	 */
	private BackgroundTranslationJobService $jobs;

	/**
	 * Batch coordinator.
	 *
	 * @var BackgroundTranslationBatchCoordinator
	 */
	private BackgroundTranslationBatchCoordinator $batches;

	/**
	 * Action Scheduler wake service.
	 *
	 * @var BackgroundTranslationScheduler
	 */
	private BackgroundTranslationScheduler $scheduler;

	/**
	 * Worker for synchronous execution.
	 *
	 * @var BackgroundTranslationWorker
	 */
	private BackgroundTranslationWorker $worker;

	/**
	 * Lease service for cleanup sweep.
	 *
	 * @var JobLeaseService
	 */
	private JobLeaseService $leases;

	/**
	 * ViewModel serializer.
	 *
	 * @var JobsViewModelSerializer
	 */
	private JobsViewModelSerializer $serializer;

	/**
	 * Builds the CLI command group.
	 *
	 * @param BackgroundTranslationJobService       $jobs       Job service.
	 * @param BackgroundTranslationBatchCoordinator $batches    Batch coordinator.
	 * @param BackgroundTranslationScheduler        $scheduler  Scheduler.
	 * @param BackgroundTranslationWorker           $worker     Worker.
	 * @param JobLeaseService                       $leases     Lease service.
	 * @param JobsViewModelSerializer|null          $serializer Serializer.
	 */
	public function __construct(
		BackgroundTranslationJobService $jobs,
		BackgroundTranslationBatchCoordinator $batches,
		BackgroundTranslationScheduler $scheduler,
		BackgroundTranslationWorker $worker,
		JobLeaseService $leases,
		?JobsViewModelSerializer $serializer = null
	) {
		$this->jobs       = $jobs;
		$this->batches    = $batches;
		$this->scheduler  = $scheduler;
		$this->worker     = $worker;
		$this->leases     = $leases;
		$this->serializer = $serializer ?? new JobsViewModelSerializer();
	}

	/**
	 * Registers job commands when WP-CLI is available.
	 *
	 * @param BackgroundTranslationJobService       $jobs      Job service.
	 * @param BackgroundTranslationBatchCoordinator $batches   Batch coordinator.
	 * @param BackgroundTranslationScheduler        $scheduler Scheduler.
	 * @param BackgroundTranslationWorker           $worker    Worker.
	 * @param JobLeaseService                       $leases    Lease service.
	 */
	public static function register(
		BackgroundTranslationJobService $jobs,
		BackgroundTranslationBatchCoordinator $batches,
		BackgroundTranslationScheduler $scheduler,
		BackgroundTranslationWorker $worker,
		JobLeaseService $leases
	): void {
		if ( ! class_exists( WP_CLI::class ) ) {
			return;
		}

		$cli = new self( $jobs, $batches, $scheduler, $worker, $leases );

		WP_CLI::add_command(
			'aiml jobs',
			$cli,
			array(
				'shortdesc' => 'Inspect and control background translation jobs.',
			)
		);
	}

	/**
	 * Lists jobs.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Filter by job status.
	 *
	 * [--batch-id=<batch_id>]
	 * : Filter by batch id.
	 *
	 * [--language-id=<language_id>]
	 * : Filter by target language id.
	 *
	 * [--page=<page>]
	 * : Page number.
	 *
	 * [--per-page=<per_page>]
	 * : Items per page (max 100).
	 *
	 * [--format=<format>]
	 * : Output format: table or json.
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc Assoc args.
	 */
	public function list( array $args, array $assoc ): void {
		unset( $args );
		self::require_cap( JobsCapabilities::VIEW_JOBS );

		$result = $this->jobs->list_jobs(
			array(
				'status'      => $assoc['status'] ?? null,
				'batch_id'    => $assoc['batch-id'] ?? null,
				'language_id' => isset( $assoc['language-id'] ) ? (int) $assoc['language-id'] : null,
				'page'        => isset( $assoc['page'] ) ? (int) $assoc['page'] : 1,
				'per_page'    => isset( $assoc['per-page'] ) ? (int) $assoc['per-page'] : 20,
			)
		);

		$rows = $this->serializer->many_jobs_to_arrays( $result['items'] );
		$fmt  = isset( $assoc['format'] ) ? (string) $assoc['format'] : 'table';

		if ( 'json' === $fmt ) {
			WP_CLI::log(
				wp_json_encode(
					array(
						'items'    => $rows,
						'total'    => $result['total'],
						'page'     => $result['page'],
						'per_page' => $result['per_page'],
					),
					JSON_PRETTY_PRINT
				)
			);
			return;
		}

		WP_CLI\Utils\format_items(
			'table',
			$rows,
			array( 'job_id', 'job_type', 'status', 'source_id', 'language_id', 'total_items', 'completed_items', 'failed_items' )
		);
	}

	/**
	 * Shows one job with item summaries.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Job id.
	 *
	 * [--format=<format>]
	 * : Output format: json (default).
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc Assoc args.
	 */
	public function show( array $args, array $assoc ): void {
		self::require_cap( JobsCapabilities::VIEW_JOBS );

		$job_id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( $job_id <= 0 ) {
			WP_CLI::error( 'Missing job id.' );
		}

		$job = $this->jobs->find_job( $job_id );
		if ( null === $job ) {
			WP_CLI::error( 'Job not found.' );
		}

		self::assert_post_scope( $job );

		$detail = $this->serializer->job_detail_from_rows( $job, $this->jobs->list_job_items( $job_id ) );
		WP_CLI::log( wp_json_encode( $detail->to_array(), JSON_PRETTY_PRINT ) );
		unset( $assoc );
	}

	/**
	 * Enqueues or synchronously runs a worker wake.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Job id.
	 *
	 * [--sync]
	 * : Run the worker inline instead of enqueueing Action Scheduler.
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc Assoc args.
	 */
	public function run( array $args, array $assoc ): void {
		self::require_cap( JobsCapabilities::RUN_JOBS );

		$job_id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( $job_id <= 0 ) {
			WP_CLI::error( 'Missing job id.' );
		}

		$job = $this->jobs->find_job( $job_id );
		if ( null === $job ) {
			WP_CLI::error( 'Job not found.' );
		}

		self::assert_post_scope( $job );

		if ( ! empty( $assoc['sync'] ) ) {
			$result = $this->worker->run( $job_id );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}

			WP_CLI::success( 'Job wake completed synchronously.' );
			return;
		}

		$wake = $this->scheduler->enqueue_job( $job_id );
		if ( is_wp_error( $wake ) ) {
			WP_CLI::error( $wake->get_error_message() );
		}

		WP_CLI::success( 'Worker wake enqueued.' );
	}

	/**
	 * Requests job pause.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Job id.
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc Assoc args.
	 */
	public function pause( array $args, array $assoc ): void {
		unset( $assoc );
		self::require_cap( JobsCapabilities::CANCEL_JOBS );
		$this->mutate_job( $args, array( $this->jobs, 'request_pause' ), 'Pause requested.' );
	}

	/**
	 * Resumes a paused job.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Job id.
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc Assoc args.
	 */
	public function resume( array $args, array $assoc ): void {
		unset( $assoc );
		self::require_cap( JobsCapabilities::CANCEL_JOBS );
		$this->mutate_job( $args, array( $this->jobs, 'resume' ), 'Job resumed.' );
	}

	/**
	 * Requests job cancel and observes it at a safe boundary.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Job id.
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc Assoc args.
	 */
	public function cancel( array $args, array $assoc ): void {
		unset( $assoc );
		self::require_cap( JobsCapabilities::CANCEL_JOBS );

		$job_id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( $job_id <= 0 ) {
			WP_CLI::error( 'Missing job id.' );
		}

		$job = $this->jobs->find_job( $job_id );
		if ( null === $job ) {
			WP_CLI::error( 'Job not found.' );
		}

		self::assert_post_scope( $job );

		$result = $this->jobs->request_cancel( $job_id );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		$this->jobs->observe_requested_action_at_boundary( $job_id );
		WP_CLI::success( 'Cancel requested.' );
	}

	/**
	 * Resets failed items and enqueues a worker wake.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Job id.
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc Assoc args.
	 */
	public function retry_failed( array $args, array $assoc ): void {
		unset( $assoc );
		self::require_cap( JobsCapabilities::RUN_JOBS );

		$job_id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( $job_id <= 0 ) {
			WP_CLI::error( 'Missing job id.' );
		}

		$job = $this->jobs->find_job( $job_id );
		if ( null === $job ) {
			WP_CLI::error( 'Job not found.' );
		}

		self::assert_post_scope( $job );

		$result = $this->jobs->retry_failed_items( $job_id );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		$wake = $this->scheduler->enqueue_job( $job_id );
		if ( is_wp_error( $wake ) ) {
			WP_CLI::error( $wake->get_error_message() );
		}

		WP_CLI::success( 'Failed items reset and worker wake enqueued.' );
	}

	/**
	 * Runs the bounded jobs sweep (lease recovery stub).
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Confirm cleanup.
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc Assoc args.
	 */
	public function cleanup( array $args, array $assoc ): void {
		unset( $args );
		self::require_cap( JobsCapabilities::RUN_JOBS );

		if ( empty( $assoc['yes'] ) ) {
			WP_CLI::error( 'Pass --yes to confirm cleanup.' );
		}

		$this->scheduler->run_sweep( $this->leases );
		WP_CLI::success( 'Jobs sweep completed.' );
	}

	/**
	 * Applies a domain mutation for pause/resume helpers.
	 *
	 * @param array<int, string> $args     Positional args.
	 * @param callable           $callback Service callback.
	 * @param string             $message  Success message.
	 */
	private function mutate_job( array $args, callable $callback, string $message ): void {
		$job_id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( $job_id <= 0 ) {
			WP_CLI::error( 'Missing job id.' );
		}

		$job = $this->jobs->find_job( $job_id );
		if ( null === $job ) {
			WP_CLI::error( 'Job not found.' );
		}

		self::assert_post_scope( $job );

		$result = $callback( $job_id );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( $message );
	}

	/**
	 * Ensures the current CLI user has a capability.
	 *
	 * @param string $cap Capability.
	 */
	private static function require_cap( string $cap ): void {
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user_id <= 0 || ! user_can( $user_id, $cap ) ) {
			WP_CLI::error( 'Current user lacks required job capability: ' . $cap );
		}
	}

	/**
	 * Ensures the current CLI user may access the job post scope.
	 *
	 * @param object $job Job row.
	 */
	private static function assert_post_scope( object $job ): void {
		if ( Store::SOURCE_POST !== (string) $job->source_type ) {
			return;
		}

		$user_id = (int) get_current_user_id();
		if ( ! user_can( $user_id, 'edit_post', (int) $job->source_id ) ) {
			WP_CLI::error( 'Current user lacks edit_post on job source.' );
		}
	}
}
