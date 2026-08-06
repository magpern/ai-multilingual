<?php
/**
 * Safe REST presentation contract for one job item row.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Job item ViewModel — no bodies, prompts, or secrets.
 */
final class JobItemViewModel {

	/**
	 * Builds the ViewModel.
	 *
	 * @param int         $item_id         Item id.
	 * @param int         $job_id          Parent job id.
	 * @param string      $segment_key     Segment key.
	 * @param string      $status          Item status.
	 * @param string      $result_code     Result code.
	 * @param int         $attempt_count   Attempt count.
	 * @param string      $last_error_code Last error code.
	 * @param string      $last_error_class Last error class.
	 * @param string      $last_error_message Bounded error message.
	 * @param string      $created_at      Created timestamp.
	 * @param string      $updated_at      Updated timestamp.
	 * @param string|null $started_at      Started timestamp.
	 * @param string|null $finished_at     Finished timestamp.
	 */
	public function __construct(
		public readonly int $item_id,
		public readonly int $job_id,
		public readonly string $segment_key,
		public readonly string $status,
		public readonly string $result_code,
		public readonly int $attempt_count,
		public readonly string $last_error_code,
		public readonly string $last_error_class,
		public readonly string $last_error_message,
		public readonly string $created_at,
		public readonly string $updated_at,
		public readonly ?string $started_at,
		public readonly ?string $finished_at
	) {
	}

	/**
	 * Serializes the ViewModel to REST JSON.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'item_id'            => $this->item_id,
			'job_id'             => $this->job_id,
			'segment_key'        => $this->segment_key,
			'status'             => $this->status,
			'result_code'        => $this->result_code,
			'attempt_count'      => $this->attempt_count,
			'last_error_code'    => $this->last_error_code,
			'last_error_class'   => $this->last_error_class,
			'last_error_message' => $this->last_error_message,
			'created_at'         => $this->created_at,
			'updated_at'         => $this->updated_at,
			'started_at'         => $this->started_at,
			'finished_at'        => $this->finished_at,
		);
	}
}
