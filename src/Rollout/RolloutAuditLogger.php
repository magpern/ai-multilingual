<?php
/**
 * Rollout audit event logger.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Emits structured rollout audit events without secrets or content.
 */
final class RolloutAuditLogger {

	/**
	 * Logs a rollout audit event.
	 *
	 * Stable payload fields only — no content, prompts, or secrets.
	 *
	 * @param string               $event       Event name from {@see RolloutAuditEvents}.
	 * @param array<string, mixed> $payload     Audit payload.
	 */
	public function log( string $event, array $payload = array() ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		$payload          = $this->sanitize_payload( $payload );
		$payload['event'] = $event;

		/**
		 * Fires when a rollout audit event is recorded.
		 *
		 * @since 0.1.0
		 *
		 * @param string               $event   Event name.
		 * @param array<string, mixed> $payload Sanitized payload.
		 */
		\do_action( 'aiml_rollout_audit', $event, $payload );
	}

	/**
	 * Strips disallowed keys from an audit payload.
	 *
	 * @param array<string, mixed> $payload Raw payload.
	 * @return array<string, mixed>
	 */
	private function sanitize_payload( array $payload ): array {
		$allowed = array(
			'old_stage',
			'new_stage',
			'policy_version',
			'user_id',
			'timestamp',
			'source',
			'reason',
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
