<?php
/**
 * Review Workflow audit event logger (ADR-0015 §12).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Review;

/**
 * Emits structured review audit events without translation content.
 *
 * Safe payload only: post/source id, segment key, language id, old/new
 * review status, user id, timestamp, source surface, a safe submitted-hash
 * fingerprint (first 8 chars), and rejection reason presence/length —
 * never the translation body, source body, or full rejection reason.
 */
final class ReviewAuditLogger {

	/**
	 * Number of leading hash characters kept as a safe fingerprint.
	 */
	public const HASH_FINGERPRINT_LEN = 8;

	/**
	 * Logs a review audit event.
	 *
	 * @param string               $event   Event name from {@see ReviewAuditEvents}.
	 * @param array<string, mixed> $payload Audit payload.
	 */
	public function log( string $event, array $payload = array() ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		$payload          = $this->sanitize_payload( $payload );
		$payload['event'] = $event;

		/**
		 * Fires when a review audit event is recorded.
		 *
		 * @since 0.1.0
		 *
		 * @param string               $event   Event name.
		 * @param array<string, mixed> $payload Sanitized payload.
		 */
		\do_action( 'aiml_review_audit', $event, $payload );
	}

	/**
	 * Reduces a translation hash to a safe, non-reversible fingerprint.
	 *
	 * @param string $hash Full hash value.
	 */
	public static function hash_fingerprint( string $hash ): string {
		if ( '' === $hash ) {
			return '';
		}

		return substr( $hash, 0, self::HASH_FINGERPRINT_LEN );
	}

	/**
	 * Strips disallowed keys from an audit payload.
	 *
	 * @param array<string, mixed> $payload Raw payload.
	 * @return array<string, mixed>
	 */
	public function sanitize_payload( array $payload ): array {
		$allowed = array(
			'source_type',
			'source_id',
			'post_id',
			'segment_key',
			'language_id',
			'language_code',
			'old_review_status',
			'new_review_status',
			'user_id',
			'timestamp',
			'source_surface',
			'submitted_hash_fingerprint',
			'reason_present',
			'reason_length',
			'action',
			'total_count',
			'succeeded_count',
			'failed_count',
			'batch_status',
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
