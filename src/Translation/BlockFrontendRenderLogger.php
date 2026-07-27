<?php
/**
 * Strategy F frontend block render logging.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

/**
 * Emits structured frontend render events without body text.
 */
final class BlockFrontendRenderLogger {

	public const EVENT_GATE_ALLOWED         = 'block_render_gate_allowed';
	public const EVENT_GATE_DENIED          = 'block_render_gate_denied';
	public const EVENT_LOOKUP_COMPLETE      = 'block_translation_lookup_complete';
	public const EVENT_LOOKUP_FAILED        = 'block_translation_lookup_failed';
	public const EVENT_RENDER_COMPLETE      = 'block_frontend_render_complete';
	public const EVENT_RENDER_FAILED        = 'block_frontend_render_failed';
	public const EVENT_TRANSLATION_REJECTED = 'block_translation_rejected';

	/**
	 * Logs a structured frontend render event.
	 *
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $context Diagnostic context (no body text).
	 */
	public function log( string $event, array $context = array() ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		/**
		 * Fires when Strategy F records a frontend block render event.
		 *
		 * @since 0.1.0
		 *
		 * @param string               $event   Event name.
		 * @param array<string, mixed> $context Diagnostic context.
		 */
		\do_action( 'aiml_block_frontend_render_log', $event, $context );
	}
}
