<?php
/**
 * Internal surface capability registry (code-owned; not a public API).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Surface;

/**
 * Keyed by source_type. Wired once from Plugin. No public registration filter.
 */
final class SurfaceRegistry {

	/**
	 * Registered adapters keyed by source_type.
	 *
	 * @var array<string, SurfaceCapability>
	 */
	private array $by_type = array();

	/**
	 * Registers a capability adapter for its source_type.
	 *
	 * @param SurfaceCapability $capability Adapter.
	 */
	public function register( SurfaceCapability $capability ): void {
		$this->by_type[ $capability->source_type() ] = $capability;
	}

	/**
	 * Returns the capability for a source_type or null.
	 *
	 * @param string $source_type Source type key.
	 */
	public function for( string $source_type ): ?SurfaceCapability {
		return $this->by_type[ $source_type ] ?? null;
	}

	/**
	 * Requires a registered capability or throws.
	 *
	 * @param string $source_type Source type key.
	 * @throws \InvalidArgumentException When unregistered.
	 */
	public function require( string $source_type ): SurfaceCapability {
		$capability = $this->for( $source_type );
		if ( null === $capability ) {
			throw new \InvalidArgumentException( 'Unregistered source_type.' );
		}
		return $capability;
	}

	/**
	 * Whether a source_type is registered and declares a capability.
	 *
	 * @param string $source_type Source type.
	 * @param string $capability  Capability name.
	 */
	public function supports( string $source_type, string $capability ): bool {
		$adapter = $this->for( $source_type );
		return null !== $adapter && $adapter->supports( $capability );
	}

	/**
	 * Whether a source_type is registered.
	 *
	 * @param string $source_type Source type.
	 */
	public function is_registered( string $source_type ): bool {
		return null !== $this->for( $source_type );
	}

	/**
	 * Registered source_type keys (for tests / diagnostics).
	 *
	 * @return list<string>
	 */
	public function registered_types(): array {
		return array_keys( $this->by_type );
	}
}
