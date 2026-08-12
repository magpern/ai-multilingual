<?php
/**
 * Narrow integration segment authority facts (TSC.3).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Integration;

/**
 * Facts/mechanics only — not publication policy (TI.7) and not a second policy engine.
 */
interface IntegrationSegmentAuthority {

	/**
	 * Whether this authority applies to the Store row.
	 *
	 * @param object $row Store translation row.
	 */
	public function applies( object $row ): bool;

	/**
	 * Whether the semantic source still exists.
	 *
	 * @param object $row Store row.
	 */
	public function exists( object $row ): bool;

	/**
	 * Visitor-public fact for the semantic source (not host post status).
	 *
	 * @param object $row Store row.
	 */
	public function is_visitor_public( object $row ): bool;

	/**
	 * Whether the user may edit/mutate this translation as authority.
	 *
	 * @param int    $user_id User id (0 = current).
	 * @param object $row     Store row.
	 */
	public function user_can_edit( int $user_id, object $row ): bool;

	/**
	 * Preferred admin edit URL for the semantic source, or empty.
	 *
	 * @param object $row Store row.
	 */
	public function edit_link( object $row ): string;
}
