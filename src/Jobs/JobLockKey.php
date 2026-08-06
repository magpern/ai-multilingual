<?php
/**
 * Stable object+language lock key builder.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Builds lock_key / active_lock_key values (plan §7).
 */
final class JobLockKey {

	/**
	 * Build `{source_type}:{source_id}:{language_id}`.
	 *
	 * @param string $source_type Source type slug.
	 * @param int    $source_id   Source object id.
	 * @param int    $language_id Language id.
	 */
	public static function build( string $source_type, int $source_id, int $language_id ): string {
		return sprintf( '%s:%d:%d', $source_type, $source_id, $language_id );
	}
}
