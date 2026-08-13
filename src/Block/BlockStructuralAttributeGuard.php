<?php
/**
 * Fail-closed structural attribute preservation for block render apply paths.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

use AIMultilingual\Translation\Safety\StructuralAttributeGuard;

/**
 * Block render consumer of the surface-neutral structural guard.
 */
final class BlockStructuralAttributeGuard {

	/**
	 * Whether structural attributes are unchanged between two HTML fragments.
	 *
	 * @param string $before_html Source innerHTML before apply.
	 * @param string $after_html  innerHTML after apply.
	 */
	public static function preserves_structure( string $before_html, string $after_html ): bool {
		return StructuralAttributeGuard::preserves_structure( $before_html, $after_html );
	}
}
