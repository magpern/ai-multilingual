<?php
/**
 * Public visitor language context DTO (Extension API).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

/**
 * Minimal URL/host-resolved visitor language snapshot for chrome consumers.
 */
final class VisitorLanguageContext {

	/**
	 * Builds the visitor language context DTO.
	 *
	 * @param string $code       Language URL code.
	 * @param bool   $is_default Whether this is the site default language.
	 */
	public function __construct(
		public readonly string $code,
		public readonly bool $is_default,
	) {
	}
}
