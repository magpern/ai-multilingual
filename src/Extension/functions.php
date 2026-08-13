<?php
/**
 * Public Extension API v1 global helpers.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

use AIMultilingual\Extension\ExtensionServices;

/**
 * Marks a source identity dirty for coalesced request-local sync.
 *
 * Validates admitted source only; never syncs immediately.
 *
 * @param string $source_type post|term.
 * @param int    $source_id   Source object id.
 */
function aiml_mark_source_dirty( string $source_type, int $source_id ): bool {
	return ExtensionServices::mark_source_dirty( $source_type, $source_id );
}
