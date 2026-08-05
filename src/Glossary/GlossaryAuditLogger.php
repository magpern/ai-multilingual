<?php
/**
 * Glossary audit event logger.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Glossary;

/**
 * Emits structured glossary audit events without term content.
 */
final class GlossaryAuditLogger {

	/**
	 * Logs a glossary audit event.
	 *
	 * @param string               $event   Event name from {@see GlossaryAuditEvents}.
	 * @param array<string, mixed> $payload Audit payload.
	 */
	public function log( string $event, array $payload = array() ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		$payload          = $this->sanitize_payload( $payload );
		$payload['event'] = $event;

		/**
		 * Fires when a glossary audit event is recorded.
		 *
		 * @since 0.1.0
		 *
		 * @param string               $event   Event name.
		 * @param array<string, mixed> $payload Sanitized payload.
		 */
		\do_action( 'aiml_glossary_audit', $event, $payload );
	}

	/**
	 * Strips disallowed keys from an audit payload.
	 *
	 * @param array<string, mixed> $payload Raw payload.
	 * @return array<string, mixed>
	 */
	public function sanitize_payload( array $payload ): array {
		$allowed = array(
			'glossary_id',
			'source_lang_id',
			'target_lang_id',
			'source_lang_code',
			'target_lang_code',
			'is_active',
			'was_active',
			'glossary_version',
			'user_id',
			'timestamp',
			'source_surface',
			'count',
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
