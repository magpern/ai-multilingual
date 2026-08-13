<?php
/**
 * Pending public meta registration awaiting seal-time activation evaluation.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

/**
 * Holds meta definition until registry seal evaluates activation once.
 */
final class PendingExtensionMeta {

	/**
	 * Wraps one pending meta registration until seal completes.
	 *
	 * @param ExtensionMetaDefinition $definition Meta definition.
	 * @param bool                    $active     Activation result after seal.
	 */
	public function __construct(
		public readonly ExtensionMetaDefinition $definition,
		public bool $active = true,
	) {
	}
}
