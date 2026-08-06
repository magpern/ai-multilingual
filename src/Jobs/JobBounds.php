<?php
/**
 * Background translation job workload bounds.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Frozen MVP limits (plan §5).
 */
final class JobBounds {

	public const MAX_POSTS_PER_BULK     = 50;
	public const MAX_ITEMS_PER_JOB      = 500;
	public const MAX_SELECTED_SEGMENTS  = 50;
	public const MAX_CONCURRENT_RUNNING = 20;

	/**
	 * Default lease TTL in seconds (plan §7).
	 */
	public const DEFAULT_LEASE_TTL_SECONDS = 300;
}
