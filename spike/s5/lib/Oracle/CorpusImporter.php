<?php
/**
 * Spike S5 prototype — converts real parse_blocks() output into OracleNode
 * trees, so corpus fixtures can be used as ScaleDocumentGenerator templates.
 *
 * Model amendment: an earlier version of this importer threw
 * UnsupportedContainerShapeException for any container with a string chunk
 * BETWEEN two children — a real, common Gutenberg shape (a "\n\n" between
 * sibling core/button, core/list-item, or core/column blocks) that the
 * OracleNode container model at the time could not represent. OracleNode now
 * models a container's separators generically (see its docblock), so this
 * importer no longer needs to detect or reject that shape — every chunk
 * between two null markers is simply concatenated into the separator at that
 * position, verbatim, with nothing trimmed, merged into a child, or inferred.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Oracle;

final class CorpusImporter {

	/**
	 * @return OracleNode[] Top-level roots.
	 */
	public static function from_content( string $content, IdGenerator $ids ): array {
		$blocks = parse_blocks( $content );

		return array_map( static fn( array $b ) => self::convert( $b, $ids ), $blocks );
	}

	private static function convert( array $block, IdGenerator $ids ): OracleNode {
		if ( empty( $block['innerBlocks'] ) ) {
			return OracleNode::leaf( $ids->next(), $block['blockName'], $block['attrs'], '', (string) $block['innerHTML'], '' );
		}

		// Walk innerContent once, accumulating string chunks into the
		// separator that precedes the next child (a null marker). Real
		// content never has two consecutive string chunks with no null
		// between them (the parser always merges adjacent HTML into one
		// substr call), but accumulating rather than assuming a single chunk
		// per gap costs nothing and stays correct even if that ever changes.
		$separators = array();
		$current    = '';

		foreach ( $block['innerContent'] as $chunk ) {
			if ( null === $chunk ) {
				$separators[] = $current;
				$current      = '';
			} else {
				$current .= (string) $chunk;
			}
		}

		$separators[] = $current; // Whatever followed the last child (or the whole content, if there turn out to be no null markers at all).

		$children = array_map( static fn( array $c ) => self::convert( $c, $ids ), $block['innerBlocks'] );

		return OracleNode::container( $ids->next(), $block['blockName'], $block['attrs'], $separators, $children );
	}
}
