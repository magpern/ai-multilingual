<?php
/**
 * Background translation job audit event logger (plan §22).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Emits structured job audit events without translation content.
 *
 * Safe payload only: job id, type, counts, language, provider id, profile id,
 * attempts, result class, user id, timestamp, source surface — never bodies,
 * prompts, secrets, stack traces, or full provider error bodies.
 */
final class BackgroundTranslationJobAuditLogger {

	/**
	 * Logs a translation job audit event.
	 *
	 * @param string               $event   Event name from {@see BackgroundTranslationJobAuditEvents}.
	 * @param array<string, mixed> $payload Audit payload.
	 */
	public function log( string $event, array $payload = array() ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		$payload          = $this->sanitize_payload( $payload );
		$payload['event'] = $event;

		/**
		 * Fires when a background translation job audit event is recorded.
		 *
		 * @since 0.1.0
		 *
		 * @param string               $event   Event name.
		 * @param array<string, mixed> $payload Sanitized payload.
		 */
		\do_action( 'aiml_translation_job_audit', $event, $payload );
	}

	/**
	 * Builds a safe payload from a job row and optional extras.
	 *
	 * @param object               $job    Job row.
	 * @param array<string, mixed> $extras Optional additional safe fields.
	 * @return array<string, mixed>
	 */
	public function payload_from_job( object $job, array $extras = array() ): array {
		$payload = array(
			'job_id'          => (int) ( $job->job_id ?? 0 ),
			'job_type'        => (string) ( $job->job_type ?? '' ),
			'status'          => (string) ( $job->status ?? '' ),
			'language_id'     => (int) ( $job->language_id ?? 0 ),
			'provider_id'     => (string) ( $job->provider_id ?? '' ),
			'prompt_profile'  => (string) ( $job->prompt_profile ?? '' ),
			'source_type'     => (string) ( $job->source_type ?? '' ),
			'source_id'       => (int) ( $job->source_id ?? 0 ),
			'total_items'     => (int) ( $job->total_items ?? 0 ),
			'completed_items' => (int) ( $job->completed_items ?? 0 ),
			'failed_items'    => (int) ( $job->failed_items ?? 0 ),
			'skipped_items'   => (int) ( $job->skipped_items ?? 0 ),
			'stale_items'     => (int) ( $job->stale_items ?? 0 ),
			'cancelled_items' => (int) ( $job->cancelled_items ?? 0 ),
			'user_id'         => (int) ( $job->created_by ?? 0 ),
			'source_surface'  => (string) ( $extras['source_surface'] ?? 'system' ),
		);

		return array_merge( $payload, $extras );
	}

	/**
	 * Strips disallowed keys from an audit payload.
	 *
	 * @param array<string, mixed> $payload Raw payload.
	 * @return array<string, mixed>
	 */
	public function sanitize_payload( array $payload ): array {
		$allowed = array(
			'job_id',
			'job_type',
			'status',
			'language_id',
			'language_code',
			'provider_id',
			'prompt_profile',
			'attempts',
			'result_class',
			'user_id',
			'timestamp',
			'source_surface',
			'source_type',
			'source_id',
			'segment_key',
			'item_id',
			'total_items',
			'completed_items',
			'failed_items',
			'skipped_items',
			'stale_items',
			'cancelled_items',
			'error_code',
			'error_class',
		);

		$clean = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				$clean[ $key ] = $payload[ $key ];
			}
		}

		if ( ! isset( $clean['timestamp'] ) ) {
			$clean['timestamp'] = gmdate( 'c' );
		}

		return $clean;
	}
}
