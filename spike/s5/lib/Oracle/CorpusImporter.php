<?php
/**
 * Spike S5 prototype — converts real parse_blocks() output into OracleNode
 * trees, so authentic (or, currently, provisional-automated) corpus fixtures
 * can be used as ScaleDocumentGenerator templates.
 *
 * KNOWN, DOCUMENTED LIMITATION (see Phase 1b review package): the accepted
 * Phase 1a OracleNode container model supports exactly one wrapper-prefix
 * chunk before ALL children and one wrapper-suffix chunk after ALL children —
 * it has no representation for a string chunk sitting BETWEEN two children
 * (e.g. the blank line Gutenberg conventionally places between sibling
 * blocks inside a shared container). The corpus in
 * spike/s5/corpus/authored/ contains exactly this shape (core/buttons,
 * core/list, and the inner core/columns all separate 2+ children with such a
 * chunk). This importer detects that shape and throws
 * UnsupportedContainerShapeException rather than silently truncating,
 * merging, or dropping the separator text. Extending OracleNode to support
 * inter-child separators would fix this, but that is a change to an
 * already-accepted Phase 1a artifact and is deliberately NOT made here — see
 * the Phase 1b review package for the finding and its impact.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Oracle;

final class UnsupportedContainerShapeException extends \RuntimeException {}

final class CorpusImporter {

	/**
	 * @return OracleNode[] Top-level roots.
	 * @throws UnsupportedContainerShapeException
	 */
	public static function from_content( string $content, IdGenerator $ids ): array {
		$blocks = parse_blocks( $content );

		return array_map( static fn( array $b ) => self::convert( $b, $ids ), $blocks );
	}

	private static function convert( array $block, IdGenerator $ids ): OracleNode {
		if ( empty( $block['innerBlocks'] ) ) {
			return OracleNode::leaf( $ids->next(), $block['blockName'], $block['attrs'], '', (string) $block['innerHTML'], '' );
		}

		$chunks         = array_values( $block['innerContent'] );
		$null_positions = array();

		foreach ( $chunks as $i => $c ) {
			if ( null === $c ) {
				$null_positions[] = $i;
			}
		}

		if ( array() === $null_positions ) {
			throw new UnsupportedContainerShapeException(
				sprintf( 'Block %s has innerBlocks but no null markers in innerContent — inconsistent shape.', $block['blockName'] ?? '(null)' )
			);
		}

		$first_null = $null_positions[0];
		$last_null  = end( $null_positions );

		foreach ( $chunks as $i => $c ) {
			if ( null !== $c && $i > $first_null && $i < $last_null ) {
				throw new UnsupportedContainerShapeException(
					sprintf(
						'Block %s has a string chunk at innerContent[%d] (%s), between its first child (position %d) and last child (position %d) — an inter-child separator this container model cannot represent.',
						$block['blockName'] ?? '(null)',
						$i,
						wp_json_encode( $c ),
						$first_null,
						$last_null
					)
				);
			}
		}

		$prefix     = null !== $chunks[0] ? (string) $chunks[0] : '';
		$last_index = count( $chunks ) - 1;
		$suffix     = null !== $chunks[ $last_index ] ? (string) $chunks[ $last_index ] : '';

		$children = array_map( static fn( array $c ) => self::convert( $c, $ids ), $block['innerBlocks'] );

		return OracleNode::container( $ids->next(), $block['blockName'], $block['attrs'], $prefix, $children, $suffix );
	}
}
