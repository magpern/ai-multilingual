<?php
/**
 * Reference/test adapter proving native m: overlay (TSC.2 — not production binder).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Fixtures\RegisteredMeta;

/**
 * Explicit code-owned visitor filter for native registered meta proof.
 * Must not be wired from production Plugin.php.
 */
final class NativeMetaReferenceAdapter {

	public const FILTER = 'aiml_tsc2_reference_meta_overlay';

	/**
	 * @param callable(string): (?string) $resolve Segment key → translated text.
	 * @param string                      $segment_key Segment to overlay.
	 */
	public function register( callable $resolve, string $segment_key ): void {
		add_filter(
			self::FILTER,
			static function ( $value ) use ( $resolve, $segment_key ) {
				if ( function_exists( 'is_admin' ) && is_admin() ) {
					return $value;
				}
				$translated = $resolve( $segment_key );
				return is_string( $translated ) && '' !== $translated ? $translated : $value;
			},
			10,
			1
		);
	}
}
