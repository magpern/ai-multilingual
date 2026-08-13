<?php
/**
 * Internal per-extension registration record.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

/**
 * Mutable registration state for one extension until seal completes.
 */
final class ExtensionRecord {

	public bool $active = true;

	public int $meta_count = 0;

	public int $block_count = 0;

	public int $provider_allowed_count = 0;

	public int $provider_denied_count = 0;

	/** @var list<PendingExtensionMeta> */
	public array $pending_meta = array();

	/** @var list<ExtensionBlockAdapter> */
	public array $block_adapters = array();

	/**
	 * @param ExtensionManifest $manifest Extension manifest.
	 */
	public function __construct(
		public readonly ExtensionManifest $manifest,
	) {
	}
}
