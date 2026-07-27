<?php
/**
 * SPIKE S5 ONLY — DO NOT DEPLOY. DELETE AFTER PHASE 3 CONCURRENT-EDIT TEST.
 *
 * Re-added temporarily (was deleted after the Phase 2 attribute-registration
 * spike) solely to reproduce a REAL Gutenberg-copied duplicate UUID for the
 * Phase 3 concurrent-edit simulation — the unregistered attribute never
 * survives a duplicate action, so it cannot exercise that race condition.
 *
 * Registers `aimlBlockId` as a REAL block attribute (via `block_type_metadata`,
 * the same filter WordPress core uses to bootstrap client-side block
 * definitions) for `core/paragraph` only. No production code changes.
 */

add_filter( 'block_type_metadata', function ( $metadata ) {
	if ( isset( $metadata['name'] ) && 'core/paragraph' === $metadata['name'] ) {
		if ( ! isset( $metadata['attributes'] ) || ! is_array( $metadata['attributes'] ) ) {
			$metadata['attributes'] = array();
		}
		$metadata['attributes']['aimlBlockId'] = array( 'type' => 'string' );
	}
	return $metadata;
} );
