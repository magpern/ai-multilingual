<?php
/**
 * Internal extension catalog and seal state.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

/**
 * Tracks registered extensions for diagnostics; not a public API.
 */
final class ExtensionRegistry {

	/**
	 * @var array<string, ExtensionRecord>
	 */
	private array $extensions = array();

	private bool $sealed = false;

	/**
	 * @param ExtensionManifest $manifest Extension manifest.
	 * @throws \InvalidArgumentException On duplicate id.
	 */
	public function add_extension( ExtensionManifest $manifest ): ExtensionRecord {
		$this->assert_open();

		if ( isset( $this->extensions[ $manifest->extension_id ] ) ) {
			throw new \InvalidArgumentException( 'Duplicate extension ID.' );
		}

		$record = new ExtensionRecord( $manifest );
		$this->extensions[ $manifest->extension_id ] = $record;

		return $record;
	}

	/**
	 * @param string $extension_id Extension id.
	 */
	public function get( string $extension_id ): ?ExtensionRecord {
		return $this->extensions[ $extension_id ] ?? null;
	}

	/**
	 * @return list<ExtensionRecord>
	 */
	public function all(): array {
		return array_values( $this->extensions );
	}

	public function seal(): void {
		$this->sealed = true;
	}

	public function is_sealed(): bool {
		return $this->sealed;
	}

	/**
	 * @throws \LogicException When registries are sealed.
	 */
	public function assert_open(): void {
		if ( $this->sealed ) {
			throw new \LogicException( 'Extension registries are sealed; late registration rejected.' );
		}
	}
}
