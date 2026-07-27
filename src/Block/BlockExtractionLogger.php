<?php
/**
 * Strategy F block extraction logging.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Emits structured block-extraction events without source or translated text.
 */
final class BlockExtractionLogger {

	public const EVENT_BLOCK_EXTRACTED = 'block_extracted';
	public const EVENT_ADAPTER_MISSING = 'adapter_missing';
	public const EVENT_FIELD_SKIPPED   = 'field_skipped';

	/**
	 * Logs a structured extraction event.
	 *
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $context Diagnostic context (no body text).
	 */
	public function log( string $event, array $context = array() ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		/**
		 * Fires when Strategy F records a block-extraction event.
		 *
		 * @since 0.1.0
		 *
		 * @param string               $event   Event name.
		 * @param array<string, mixed> $context Diagnostic context.
		 */
		\do_action( 'aiml_block_extraction_log', $event, $context );
	}
}
