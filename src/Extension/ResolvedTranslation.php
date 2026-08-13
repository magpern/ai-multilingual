<?php
/**
 * Immutable visitor translation lookup result.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

/**
 * Public resolver result — no Store row or internal ids exposed.
 */
final class ResolvedTranslation {

	/**
	 * @param string $text      Translated text.
	 * @param string $format    plain|html.
	 * @param bool   $available Whether translation is non-empty and overlay-eligible.
	 */
	public function __construct(
		public readonly string $text,
		public readonly string $format,
		public readonly bool $available,
	) {
	}
}
