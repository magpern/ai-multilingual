<?php
/**
 * Strategy F block migration logging.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Emits structured migration events without body text.
 */
final class BlockMigrationLogger {

	public const EVENT_STARTED                 = 'block_migration_started';
	public const EVENT_SKIPPED                 = 'block_migration_skipped';
	public const EVENT_DRY_RUN                 = 'block_migration_dry_run';
	public const EVENT_POST_COMPLETE           = 'block_migration_post_complete';
	public const EVENT_POST_FAILED             = 'block_migration_post_failed';
	public const EVENT_CONCURRENT_MODIFICATION = 'block_migration_concurrent_modification';
	public const EVENT_BATCH_COMPLETE          = 'block_migration_batch_complete';

	/**
	 * Logs a structured migration event.
	 *
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $context Diagnostic context (no body text).
	 */
	public function log( string $event, array $context = array() ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		/**
		 * Fires when Strategy F records a block migration event.
		 *
		 * @since 0.1.0
		 *
		 * @param string               $event   Event name.
		 * @param array<string, mixed> $context Diagnostic context.
		 */
		\do_action( 'aiml_block_migration_log', $event, $context );
	}
}
