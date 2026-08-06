<?php
/**
 * Safe REST presentation contract for one background translation job.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Job ViewModel — no bodies, prompts, secrets, or worker lease tokens.
 */
final class JobsViewModel {

	/**
	 * Builds the ViewModel.
	 *
	 * @param int                          $job_id                  Job id.
	 * @param string                       $job_type                Job type.
	 * @param string                       $status                  Job status.
	 * @param string                       $requested_action        Pending operator action.
	 * @param string|null                  $batch_id                Batch id.
	 * @param string                       $source_type             Source type.
	 * @param int                          $source_id               Source id.
	 * @param int                          $language_id             Target language id.
	 * @param string                       $provider_id             Provider id.
	 * @param string                       $prompt_profile          Prompt profile id.
	 * @param string                       $prompt_version          Prompt version.
	 * @param int                          $glossary_version_intended Intended glossary version.
	 * @param int                          $glossary_version_actual   Actual glossary version.
	 * @param int                          $total_items             Total items.
	 * @param int                          $queued_items            Queued items.
	 * @param int                          $running_items           Running items.
	 * @param int                          $completed_items         Completed items.
	 * @param int                          $failed_items            Failed items.
	 * @param int                          $skipped_items           Skipped items.
	 * @param int                          $stale_items             Stale items.
	 * @param int                          $cancelled_items         Cancelled items.
	 * @param int                          $budget_max_requests     Budget max requests.
	 * @param int                          $budget_max_tokens       Budget max tokens.
	 * @param int                          $budget_used_requests    Budget used requests.
	 * @param int                          $budget_used_tokens      Budget used tokens.
	 * @param int                          $budget_warning_pct      Budget warning percent.
	 * @param int                          $attempt_count           Job attempt count.
	 * @param string                       $last_error_code         Last error code.
	 * @param string                       $last_error_class        Last error class.
	 * @param string                       $last_error_message      Bounded error message.
	 * @param int                          $created_by              Creator user id.
	 * @param string                       $created_at              Created timestamp.
	 * @param string                       $updated_at              Updated timestamp.
	 * @param string|null                  $started_at              Started timestamp.
	 * @param string|null                  $finished_at             Finished timestamp.
	 * @param array<string, mixed>|null    $checkpoint              Safe checkpoint snapshot.
	 * @param array<int, JobItemViewModel> $items                   Optional item summaries.
	 */
	public function __construct(
		public readonly int $job_id,
		public readonly string $job_type,
		public readonly string $status,
		public readonly string $requested_action,
		public readonly ?string $batch_id,
		public readonly string $source_type,
		public readonly int $source_id,
		public readonly int $language_id,
		public readonly string $provider_id,
		public readonly string $prompt_profile,
		public readonly string $prompt_version,
		public readonly int $glossary_version_intended,
		public readonly int $glossary_version_actual,
		public readonly int $total_items,
		public readonly int $queued_items,
		public readonly int $running_items,
		public readonly int $completed_items,
		public readonly int $failed_items,
		public readonly int $skipped_items,
		public readonly int $stale_items,
		public readonly int $cancelled_items,
		public readonly int $budget_max_requests,
		public readonly int $budget_max_tokens,
		public readonly int $budget_used_requests,
		public readonly int $budget_used_tokens,
		public readonly int $budget_warning_pct,
		public readonly int $attempt_count,
		public readonly string $last_error_code,
		public readonly string $last_error_class,
		public readonly string $last_error_message,
		public readonly int $created_by,
		public readonly string $created_at,
		public readonly string $updated_at,
		public readonly ?string $started_at,
		public readonly ?string $finished_at,
		public readonly ?array $checkpoint,
		public readonly array $items = array()
	) {
	}

	/**
	 * Serializes the ViewModel to REST JSON.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$payload = array(
			'job_id'                    => $this->job_id,
			'job_type'                  => $this->job_type,
			'status'                    => $this->status,
			'requested_action'          => $this->requested_action,
			'batch_id'                  => $this->batch_id,
			'source_type'               => $this->source_type,
			'source_id'                 => $this->source_id,
			'language_id'               => $this->language_id,
			'provider_id'               => $this->provider_id,
			'prompt_profile'            => $this->prompt_profile,
			'prompt_version'            => $this->prompt_version,
			'glossary_version_intended' => $this->glossary_version_intended,
			'glossary_version_actual'   => $this->glossary_version_actual,
			'total_items'               => $this->total_items,
			'queued_items'              => $this->queued_items,
			'running_items'             => $this->running_items,
			'completed_items'           => $this->completed_items,
			'failed_items'              => $this->failed_items,
			'skipped_items'             => $this->skipped_items,
			'stale_items'               => $this->stale_items,
			'cancelled_items'           => $this->cancelled_items,
			'budget_max_requests'       => $this->budget_max_requests,
			'budget_max_tokens'         => $this->budget_max_tokens,
			'budget_used_requests'      => $this->budget_used_requests,
			'budget_used_tokens'        => $this->budget_used_tokens,
			'budget_warning_pct'        => $this->budget_warning_pct,
			'attempt_count'             => $this->attempt_count,
			'last_error_code'           => $this->last_error_code,
			'last_error_class'          => $this->last_error_class,
			'last_error_message'        => $this->last_error_message,
			'created_by'                => $this->created_by,
			'created_at'                => $this->created_at,
			'updated_at'                => $this->updated_at,
			'started_at'                => $this->started_at,
			'finished_at'               => $this->finished_at,
			'checkpoint'                => $this->checkpoint,
		);

		if ( array() !== $this->items ) {
			$payload['items'] = array_map(
				static fn( JobItemViewModel $item ): array => $item->to_array(),
				$this->items
			);
		}

		return $payload;
	}
}
