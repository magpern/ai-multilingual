<?php
/**
 * Public language identity by stable URL code.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

/**
 * Language reference for visitor resolver — code only, not database id.
 */
final class LanguageReference {

	/**
	 * @param string $code Language URL code (e.g. sv, pt-br).
	 */
	public function __construct(
		public readonly string $code,
	) {
	}
}
