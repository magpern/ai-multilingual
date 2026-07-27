<?php
/**
 * Strategy F block render logging.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Emits structured block-render proof events without translated body text.
 */
final class BlockRenderLogger {

	public const EVENT_BLOCK_RENDERED      = 'block_rendered';
	public const EVENT_TRANSLATION_MISSING = 'translation_missing';
	public const EVENT_UNSUPPORTED_BLOCK   = 'unsupported_block';

	/**
	 * Logs a structured render event.
	 *
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $context Diagnostic context (no body text).
	 */
	public function log( string $event, array $context = array() ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		/**
		 * Fires when Strategy F records a block-render proof event.
		 *
		 * @since 0.1.0
		 *
		 * @param string               $event   Event name.
		 * @param array<string, mixed> $context Diagnostic context.
		 */
		\do_action( 'aiml_block_render_log', $event, $context );
	}
}
