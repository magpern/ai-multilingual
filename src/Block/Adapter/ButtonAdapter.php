<?php
/**
 * Strategy F button block adapter.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block\Adapter;

/**
 * Extracts translatable content from core/button blocks.
 */
final class ButtonAdapter extends AbstractBlockAdapter {

	/**
	 * {@inheritDoc}
	 */
	protected function block_name(): string {
		return 'core/button';
	}
}
