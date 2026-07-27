<?php
/**
 * Strategy F structured identity logging.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Emits structured block-identity events without source or translated text.
 */
final class BlockIdentityLogger {

	public const EVENT_UUID_CREATED              = 'uuid_created';
	public const EVENT_UUID_PRESERVED            = 'uuid_preserved';
	public const EVENT_UUID_REPLACED_INVALID     = 'uuid_replaced_invalid';
	public const EVENT_UUID_DUPLICATE_DETECTED   = 'uuid_duplicate_detected';
	public const EVENT_UUID_DUPLICATE_REPAIRED   = 'uuid_duplicate_repaired';
	public const EVENT_UUID_GENERATION_COLLISION = 'uuid_generation_collision';
	public const EVENT_UUID_REPAIR_FAILED        = 'uuid_repair_failed';
	public const EVENT_UUID_REPAIR_COMPLETE      = 'uuid_repair_complete';

	/**
	 * Logs a structured identity event.
	 *
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $context Diagnostic context (no body text).
	 */
	public function log( string $event, array $context = array() ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		/**
		 * Fires when Strategy F records a block-identity event.
		 *
		 * @since 0.1.0
		 *
		 * @param string               $event   Event name.
		 * @param array<string, mixed> $context Diagnostic context.
		 */
		\do_action( 'aiml_block_identity_log', $event, $context );
	}
}
