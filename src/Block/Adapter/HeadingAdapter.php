<?php
/**
 * Strategy F heading block adapter.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block\Adapter;

/**
 * Extracts translatable content from core/heading blocks.
 */
final class HeadingAdapter extends AbstractBlockAdapter {

	/**
	 * {@inheritDoc}
	 */
	protected function block_name(): string {
		return 'core/heading';
	}
}
