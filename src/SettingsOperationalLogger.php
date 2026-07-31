<?php
/**
 * Strategy F settings operational logging.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual;

/**
 * Emits structured settings operational events without secrets or content.
 */
final class SettingsOperationalLogger {

	public const EVENT_FLAG_COMBO_REJECTED = 'flag_combo_rejected';

	/**
	 * Logs a structured settings operational event.
	 *
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $context Diagnostic context.
	 */
	public function log( string $event, array $context = array() ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		/**
		 * Fires when Strategy F records a settings operational event.
		 *
		 * @since 0.1.0
		 *
		 * @param string               $event   Event name.
		 * @param array<string, mixed> $context Diagnostic context.
		 */
		\do_action( 'aiml_settings_operational_log', $event, $context );
	}
}
