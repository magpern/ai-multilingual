<?php
/**
 * Maps job rows to safe REST ViewModels.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Job and item serializers — strips lease tokens, lock keys, and bodies.
 */
final class JobsViewModelSerializer {

	/**
	 * Maps one job row to a summary ViewModel (no items).
	 *
	 * @param object $row Job row.
	 */
	public function job_from_row( object $row ): JobsViewModel {
		return $this->build_job_view_model( $row, array() );
	}

	/**
	 * Maps one job row and item rows to a detail ViewModel.
	 *
	 * @param object        $row   Job row.
	 * @param array<object> $items Item rows.
	 */
	public function job_detail_from_rows( object $row, array $items ): JobsViewModel {
		$item_models = array();
		foreach ( $items as $item ) {
			$item_models[] = $this->item_from_row( $item );
		}

		return $this->build_job_view_model( $row, $item_models );
	}

	/**
	 * Maps many job rows to arrays.
	 *
	 * @param array<object> $rows Job rows.
	 * @return list<array<string, mixed>>
	 */
	public function many_jobs_to_arrays( array $rows ): array {
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = $this->job_from_row( $row )->to_array();
		}

		return $out;
	}

	/**
	 * Maps one item row to a ViewModel.
	 *
	 * @param object $row Item row.
	 */
	public function item_from_row( object $row ): JobItemViewModel {
		return new JobItemViewModel(
			(int) $row->item_id,
			(int) $row->job_id,
			(string) $row->segment_key,
			(string) $row->status,
			(string) ( $row->result_code ?? '' ),
			(int) ( $row->attempt_count ?? 0 ),
			(string) ( $row->last_error_code ?? '' ),
			(string) ( $row->last_error_class ?? '' ),
			$this->bound_message( (string) ( $row->last_error_message ?? '' ) ),
			(string) ( $row->created_at ?? '' ),
			(string) ( $row->updated_at ?? '' ),
			$this->nullable_string( $row->started_at ?? null ),
			$this->nullable_string( $row->finished_at ?? null )
		);
	}

	/**
	 * Builds a job ViewModel from a row.
	 *
	 * @param object                       $row   Job row.
	 * @param array<int, JobItemViewModel> $items Item models.
	 */
	private function build_job_view_model( object $row, array $items ): JobsViewModel {
		$checkpoint = null;
		if ( property_exists( $row, 'checkpoint' ) && null !== $row->checkpoint && '' !== $row->checkpoint ) {
			$decoded    = JobCheckpoint::decode( (string) $row->checkpoint );
			$checkpoint = array() === $decoded ? null : $decoded;
		}

		return new JobsViewModel(
			(int) $row->job_id,
			(string) $row->job_type,
			(string) $row->status,
			(string) ( $row->requested_action ?? RequestedActions::NONE ),
			$this->nullable_string( $row->batch_id ?? null ),
			(string) $row->source_type,
			(int) $row->source_id,
			(int) $row->language_id,
			(string) ( $row->provider_id ?? '' ),
			(string) ( $row->prompt_profile ?? '' ),
			(string) ( $row->prompt_version ?? '' ),
			(int) ( $row->glossary_version_intended ?? 0 ),
			(int) ( $row->glossary_version_actual ?? 0 ),
			(int) ( $row->total_items ?? 0 ),
			(int) ( $row->queued_items ?? 0 ),
			(int) ( $row->running_items ?? 0 ),
			(int) ( $row->completed_items ?? 0 ),
			(int) ( $row->failed_items ?? 0 ),
			(int) ( $row->skipped_items ?? 0 ),
			(int) ( $row->stale_items ?? 0 ),
			(int) ( $row->cancelled_items ?? 0 ),
			(int) ( $row->budget_max_requests ?? 0 ),
			(int) ( $row->budget_max_tokens ?? 0 ),
			(int) ( $row->budget_used_requests ?? 0 ),
			(int) ( $row->budget_used_tokens ?? 0 ),
			(int) ( $row->budget_warning_pct ?? 80 ),
			(int) ( $row->attempt_count ?? 0 ),
			(string) ( $row->last_error_code ?? '' ),
			(string) ( $row->last_error_class ?? '' ),
			$this->bound_message( (string) ( $row->last_error_message ?? '' ) ),
			(int) ( $row->created_by ?? 0 ),
			(string) ( $row->created_at ?? '' ),
			(string) ( $row->updated_at ?? '' ),
			$this->nullable_string( $row->started_at ?? null ),
			$this->nullable_string( $row->finished_at ?? null ),
			$checkpoint,
			$items
		);
	}

	/**
	 * Truncates operator-facing error text to the operational cap.
	 *
	 * @param string $message Raw message.
	 */
	private function bound_message( string $message ): string {
		if ( strlen( $message ) <= 500 ) {
			return $message;
		}

		return substr( $message, 0, 500 );
	}

	/**
	 * Normalizes nullable string columns.
	 *
	 * @param mixed $value Raw value.
	 */
	private function nullable_string( $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}

		return (string) $value;
	}
}
