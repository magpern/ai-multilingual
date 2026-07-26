<?php
/**
 * Spike S5 prototype — replaces byte ranges of a document without touching
 * anything outside them, and refuses to apply anything if any single range
 * does not still contain what the caller expects to find there.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5;

/**
 * Applies a set of byte-range replacements to a string.
 *
 * Every replacement carries the text it expects to find at its offset. Before
 * touching the document, every single expectation is checked. If any one of
 * them has gone stale — the content moved, changed underneath, or the offset
 * was simply wrong — nothing is applied and the caller gets the untouched
 * original back plus the specific mismatch, so the failure is loud rather
 * than a silently corrupted document.
 */
final class Splicer {

	/**
	 * Splices verified replacements into a document.
	 *
	 * @param string                                                     $content      Original document.
	 * @param array<int, array{offset: int, length: int, expected: string, replacement: string}> $replacements Ranges to replace.
	 * @return array{ok: bool, content: string, error: string|null} `content` is
	 *              the spliced result on success or the untouched original on
	 *              failure; `error` names the first mismatch found.
	 */
	public function splice( string $content, array $replacements ): array {
		foreach ( $replacements as $i => $r ) {
			$offset   = (int) $r['offset'];
			$length   = (int) $r['length'];
			$expected = (string) $r['expected'];

			if ( $offset < 0 || $length < 0 || $offset + $length > strlen( $content ) ) {
				return array(
					'ok'      => false,
					'content' => $content,
					'error'   => sprintf( 'Replacement #%d: [%d, %d) is out of bounds for a %d-byte document.', $i, $offset, $length, strlen( $content ) ),
				);
			}

			$actual = substr( $content, $offset, $length );

			if ( $actual !== $expected ) {
				return array(
					'ok'      => false,
					'content' => $content,
					'error'   => sprintf(
						"Replacement #%d: expected %s at [%d, %d) but found %s. Refusing to splice.",
						$i,
						wp_json_encode( $expected ),
						$offset,
						$offset + $length,
						wp_json_encode( $actual )
					),
				);
			}
		}

		// All expectations verified. Apply highest offset first so that
		// earlier offsets are never invalidated by a length change at a later
		// one.
		$ordered = $replacements;
		usort(
			$ordered,
			static function ( $a, $b ) {
				return $b['offset'] <=> $a['offset'];
			}
		);

		$result = $content;

		foreach ( $ordered as $r ) {
			$result = substr_replace( $result, (string) $r['replacement'], (int) $r['offset'], (int) $r['length'] );
		}

		return array(
			'ok'      => true,
			'content' => $result,
			'error'   => null,
		);
	}
}
