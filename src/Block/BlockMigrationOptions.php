<?php
/**
 * Strategy F block migration options.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Per-post migration execution options.
 */
final class BlockMigrationOptions {

	/**
	 * Builds migration options.
	 *
	 * @param bool $dry_run            When true, perform analysis only.
	 * @param bool $refresh_extraction When true, run extraction even if identity is already compliant.
	 */
	public function __construct(
		public readonly bool $dry_run = false,
		public readonly bool $refresh_extraction = false,
	) {
	}
}
