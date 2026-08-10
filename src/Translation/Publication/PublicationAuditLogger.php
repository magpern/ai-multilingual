<?php
/**
 * Bounded publication audit logger (ADR-0020).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Publication;

/**
 * Emits structured publication audit events without translation bodies.
 */
final class PublicationAuditLogger {

	/**
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $payload Audit payload.
	 */
	public function log( string $event, array $payload = array() ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		$payload          = $this->sanitize_payload( $payload );
		$payload['event'] = $event;

		/**
		 * Fires when a publication audit event is recorded.
		 *
		 * @since 0.1.0
		 *
		 * @param string               $event   Event name.
		 * @param array<string, mixed> $payload Sanitized payload.
		 */
		\do_action( 'aiml_publication_audit', $event, $payload );
	}

	/**
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
			'old_publish_status',
			'new_publish_status',
			'policy_version',
			'assessment_version',
			'overall_category',
			'reason_codes',
			'mode',
			'actor_kind',
			'user_id',
			'timestamp',
			'source_surface',
			'action',
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
