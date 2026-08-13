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

	/**
	 * Whether the extension remains active after seal.
	 *
	 * @var bool
	 */
	public bool $active = true;

	/**
	 * Count of registered meta fields.
	 *
	 * @var int
	 */
	public int $meta_count = 0;

	/**
	 * Count of registered block adapters.
	 *
	 * @var int
	 */
	public int $block_count = 0;

	/**
	 * Count of meta fields with provider_allowed enabled.
	 *
	 * @var int
	 */
	public int $provider_allowed_count = 0;

	/**
	 * Count of meta fields with provider_allowed disabled.
	 *
	 * @var int
	 */
	public int $provider_denied_count = 0;

	/**
	 * Meta registrations awaiting seal-time activation evaluation.
	 *
	 * @var list<PendingExtensionMeta>
	 */
	public array $pending_meta = array();

	/**
	 * Block adapter registrations for this extension.
	 *
	 * @var list<ExtensionBlockAdapter>
	 */
	public array $block_adapters = array();

	/**
	 * Creates a registration record for one extension manifest.
	 *
	 * @param ExtensionManifest $manifest Extension manifest.
	 */
	public function __construct(
		public readonly ExtensionManifest $manifest,
	) {
	}
}
