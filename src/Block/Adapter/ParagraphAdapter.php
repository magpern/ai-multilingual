<?php
/**
 * Strategy F paragraph block adapter.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block\Adapter;

/**
 * Extracts translatable content from core/paragraph blocks.
 */
final class ParagraphAdapter extends AbstractBlockAdapter {

	/**
	 * {@inheritDoc}
	 */
	protected function block_name(): string {
		return 'core/paragraph';
	}
}
