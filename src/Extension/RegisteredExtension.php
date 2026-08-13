<?php
/**
 * Nested registration handle for one extension (Extension API v1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

use AIMultilingual\Extension\Block\ExtensionBlockAdapter;

/**
 * Authoritative owner handle for meta and block registrations.
 */
final class RegisteredExtension {

	/**
	 * @param ExtensionRegistrar $registrar    Parent registrar.
	 * @param ExtensionRecord    $record       Extension record.
	 */
	public function __construct(
		private ExtensionRegistrar $registrar,
		private ExtensionRecord $record,
	) {
	}

	/**
	 * Extension id from manifest.
	 */
	public function get_extension_id(): string {
		return $this->record->manifest->extension_id;
	}

	/**
	 * Registers one exact-key meta field definition.
	 *
	 * @param ExtensionMetaDefinition $definition Meta definition.
	 * @throws \InvalidArgumentException On validation failure (Tier A).
	 */
	public function register_meta( ExtensionMetaDefinition $definition ): void {
		$this->registrar->register_meta_for( $this->record, $definition );
	}

	/**
	 * Registers one custom block adapter.
	 *
	 * @param ExtensionBlockAdapter $adapter Block adapter.
	 * @throws \InvalidArgumentException On validation failure (Tier A).
	 */
	public function register_block_adapter( ExtensionBlockAdapter $adapter ): void {
		$this->registrar->register_block_for( $this->record, $adapter );
	}
}
