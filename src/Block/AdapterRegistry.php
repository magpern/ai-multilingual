<?php
/**
 * Strategy F block adapter registry.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

use AIMultilingual\Block\Adapter\ButtonAdapter;
use AIMultilingual\Block\Adapter\HeadingAdapter;
use AIMultilingual\Block\Adapter\ListItemAdapter;
use AIMultilingual\Block\Adapter\ParagraphAdapter;
use AIMultilingual\Block\Adapter\PreformattedAdapter;

/**
 * Explicit adapter lookup by block name.
 *
 * One adapter owns each supported block type. No reflection or auto-discovery.
 */
final class AdapterRegistry {

	/**
	 * Adapters keyed by block name.
	 *
	 * @var array<string, TranslatableBlockAdapter>
	 */
	private array $adapters = array();

	/**
	 * Builds the registry with production adapters.
	 */
	public function __construct() {
		$this->register( new ParagraphAdapter() );
		$this->register( new HeadingAdapter() );
		$this->register( new ButtonAdapter() );
		$this->register( new ListItemAdapter() );
		$this->register( new PreformattedAdapter() );
	}

	/**
	 * Registers an adapter for its declared block names.
	 *
	 * @param TranslatableBlockAdapter $adapter Block adapter.
	 * @throws \InvalidArgumentException When a block name is already owned.
	 */
	public function register( TranslatableBlockAdapter $adapter ): void {
		foreach ( $adapter->get_block_names() as $block_name ) {
			if ( isset( $this->adapters[ $block_name ] ) ) {
				throw new \InvalidArgumentException( 'Adapter already registered for block type.' );
			}

			$this->adapters[ $block_name ] = $adapter;
		}
	}

	/**
	 * Returns the adapter for a block type, or null when unsupported.
	 *
	 * @param string $block_name Block type name.
	 */
	public function get( string $block_name ): ?TranslatableBlockAdapter {
		return $this->adapters[ $block_name ] ?? null;
	}

	/**
	 * Returns every registered block name.
	 *
	 * @return list<string>
	 */
	public function block_names(): array {
		return array_keys( $this->adapters );
	}
}
