<?php
/**
 * Bounded detail-only Jobs linkage for OTL (OTL.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use AIMultilingual\Translation\Store;

/**
 * Associates a Store translation domain identity with a retained Jobs job/item.
 *
 * Semantic identity: source_type + source_id + language_id + segment_key.
 * active_lock_key may optimize active work but is never public linkage identity.
 */
final class JobsLifecycleLinker {

	/**
	 * Maximum recent jobs scanned per object+language on detail.
	 */
	public const LOOKUP_JOB_SCAN_LIMIT = 32;

	/**
	 * Job repository.
	 *
	 * @var BackgroundTranslationJobRepository
	 */
	private BackgroundTranslationJobRepository $jobs;

	/**
	 * Item repository.
	 *
	 * @var BackgroundTranslationItemRepository
	 */
	private BackgroundTranslationItemRepository $items;

	/**
	 * Operation admission.
	 *
	 * @var JobsOperationAdmission
	 */
	private JobsOperationAdmission $admission;

	/**
	 * Failure presenter.
	 *
	 * @var JobsFailurePresenter
	 */
	private JobsFailurePresenter $failures;

	/**
	 * Builds the linker.
	 *
	 * @param BackgroundTranslationJobRepository|null  $jobs      Job repository.
	 * @param BackgroundTranslationItemRepository|null $items     Item repository.
	 * @param JobsOperationAdmission|null              $admission Admission.
	 * @param JobsFailurePresenter|null                $failures  Failure presenter.
	 */
	public function __construct(
		?BackgroundTranslationJobRepository $jobs = null,
		?BackgroundTranslationItemRepository $items = null,
		?JobsOperationAdmission $admission = null,
		?JobsFailurePresenter $failures = null
	) {
		$this->jobs      = $jobs ?? new BackgroundTranslationJobRepository();
		$this->items     = $items ?? new BackgroundTranslationItemRepository();
		$this->admission = $admission ?? new JobsOperationAdmission( $this->items );
		$this->failures  = $failures ?? new JobsFailurePresenter();
	}

	/**
	 * Builds the detail Jobs subtree for one translation row.
	 *
	 * @param object              $row  Hydrated Store row.
	 * @param array<string, bool> $caps Caps including can_view_jobs / can_run / can_cancel.
	 * @return array<string, mixed>|null Null when Jobs view is denied.
	 */
	public function link_for_translation( object $row, array $caps ): ?array {
		if ( empty( $caps['can_view_jobs'] ) ) {
			return null;
		}

		$source_type = (string) ( $row->source_type ?? Store::SOURCE_POST );
		$source_id   = (int) ( $row->source_id ?? 0 );
		$language_id = (int) ( $row->language_id ?? 0 );
		$segment_key = (string) ( $row->segment_key ?? '' );

		if ( $source_id <= 0 || $language_id <= 0 || '' === $segment_key ) {
			return $this->empty_payload( false, false );
		}

		$limit  = self::LOOKUP_JOB_SCAN_LIMIT;
		$recent = $this->jobs->list_recent_by_object( $source_type, $source_id, $language_id, $limit );

		$matched_job  = null;
		$matched_item = null;

		foreach ( $recent as $job ) {
			$item = $this->items->find_by_job_and_segment( (int) $job->job_id, $segment_key );
			if ( null !== $item ) {
				$matched_job  = $job;
				$matched_item = $item;
				break;
			}
		}

		$scanned   = count( $recent );
		$exhausted = null === $matched_job && $scanned >= $limit;

		if ( null === $matched_job || null === $matched_item ) {
			return $this->empty_payload( true, $exhausted );
		}

		$failed_count = (int) ( $matched_job->failed_items ?? 0 );
		$operations   = $this->admission->evaluate(
			$matched_job,
			array(
				'can_run'    => ! empty( $caps['can_run_jobs'] ),
				'can_cancel' => ! empty( $caps['can_cancel_jobs'] ),
			)
		);

		$failure = $this->failures->present(
			(string) ( $matched_item->last_error_code ?? '' ),
			(string) ( $matched_item->last_error_class ?? '' ),
			(string) ( $matched_item->last_error_message ?? '' ),
			(string) ( $matched_item->result_code ?? '' )
		);
		if ( null === $failure ) {
			$failure = $this->failures->present(
				(string) ( $matched_job->last_error_code ?? '' ),
				(string) ( $matched_job->last_error_class ?? '' ),
				(string) ( $matched_job->last_error_message ?? '' )
			);
		}

		$item_status       = (string) ( $matched_item->status ?? '' );
		$exactly_once_help = null;
		if ( ItemStatuses::RETRY_WAIT === $item_status || ItemStatuses::FAILED === $item_status ) {
			$exactly_once_help = array(
				'code'    => 'ti6_no_exactly_once_claim',
				'message' => __(
					'Background Jobs do not claim exactly-once provider execution. Some retry or recovery paths may repeat a provider request even though translation persistence remains safe. This does not prove a duplicate charge occurred for this item.',
					'ai-multilingual'
				),
			);
		}

		$usage         = null;
		$used_requests = (int) ( $matched_job->budget_used_requests ?? 0 );
		$used_tokens   = (int) ( $matched_job->budget_used_tokens ?? 0 );
		$max_requests  = (int) ( $matched_job->budget_max_requests ?? 0 );
		$max_tokens    = (int) ( $matched_job->budget_max_tokens ?? 0 );
		if ( $max_requests > 0 || $max_tokens > 0 || $used_requests > 0 || $used_tokens > 0 ) {
			$usage = array(
				'budget_max_requests'  => $max_requests,
				'budget_max_tokens'    => $max_tokens,
				'budget_used_requests' => $used_requests,
				'budget_used_tokens'   => $used_tokens,
				'usage_known'          => true,
				'scope'                => 'job',
			);
		}

		return array(
			'association'  => array(
				'job'                 => array(
					'job_id'               => (int) $matched_job->job_id,
					'status'               => (string) ( $matched_job->status ?? '' ),
					'job_type'             => (string) ( $matched_job->job_type ?? '' ),
					'source_type'          => (string) ( $matched_job->source_type ?? '' ),
					'source_id'            => (int) ( $matched_job->source_id ?? 0 ),
					'language_id'          => (int) ( $matched_job->language_id ?? 0 ),
					'total_items'          => (int) ( $matched_job->total_items ?? 0 ),
					'queued_items'         => (int) ( $matched_job->queued_items ?? 0 ),
					'running_items'        => (int) ( $matched_job->running_items ?? 0 ),
					'completed_items'      => (int) ( $matched_job->completed_items ?? 0 ),
					'failed_items'         => $failed_count,
					'skipped_items'        => (int) ( $matched_job->skipped_items ?? 0 ),
					'stale_items'          => (int) ( $matched_job->stale_items ?? 0 ),
					'cancelled_items'      => (int) ( $matched_job->cancelled_items ?? 0 ),
					'last_error_code'      => (string) ( $matched_job->last_error_code ?? '' ),
					'last_error_class'     => (string) ( $matched_job->last_error_class ?? '' ),
					'last_error_message'   => (string) ( $matched_job->last_error_message ?? '' ),
					'budget_max_requests'  => $max_requests,
					'budget_max_tokens'    => $max_tokens,
					'budget_used_requests' => $used_requests,
					'budget_used_tokens'   => $used_tokens,
				),
				'item'                => array(
					'item_id'            => (int) $matched_item->item_id,
					'segment_key'        => (string) ( $matched_item->segment_key ?? '' ),
					'status'             => $item_status,
					'attempt_count'      => (int) ( $matched_item->attempt_count ?? 0 ),
					'result_code'        => (string) ( $matched_item->result_code ?? '' ),
					'last_error_code'    => (string) ( $matched_item->last_error_code ?? '' ),
					'last_error_class'   => (string) ( $matched_item->last_error_class ?? '' ),
					'last_error_message' => (string) ( $matched_item->last_error_message ?? '' ),
				),
				'failed_items_in_job' => $failed_count,
				'mutation_scope'      => JobsOperationAdmission::SCOPE_JOB,
				'operations'          => $operations,
			),
			'lookup'       => array(
				'bounded'        => true,
				'job_scan_limit' => $limit,
				'matched'        => true,
				'exhausted'      => false,
			),
			'retention'    => array(
				'applies' => true,
			),
			'presentation' => array(
				'failure'           => $failure,
				'usage'             => $usage,
				'exactly_once_help' => $exactly_once_help,
			),
			'navigation'   => array(
				'jobs_tab' => true,
				'job_id'   => (int) $matched_job->job_id,
				'item_id'  => (int) $matched_item->item_id,
			),
		);
	}

	/**
	 * Empty Jobs payload after a bounded lookup (or empty domain).
	 *
	 * @param bool $looked_up Whether a lookup was attempted.
	 * @param bool $exhausted Whether the scan window was exhausted without a match.
	 * @return array<string, mixed>
	 */
	private function empty_payload( bool $looked_up, bool $exhausted ): array {
		return array(
			'association'  => null,
			'lookup'       => array(
				'bounded'        => true,
				'job_scan_limit' => self::LOOKUP_JOB_SCAN_LIMIT,
				'matched'        => false,
				'exhausted'      => $looked_up && $exhausted,
			),
			'retention'    => array(
				'applies' => true,
			),
			'presentation' => array(
				'failure'           => null,
				'usage'             => null,
				'exactly_once_help' => null,
			),
			'navigation'   => array(
				'jobs_tab' => true,
			),
		);
	}
}
