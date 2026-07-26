<?php
/**
 * Spike S5 prototype — deterministic factory helpers for constructing oracle
 * fixtures directly (no real corpus needed for these), used only to prove the
 * oracle framework's own mechanics. These are NOT authentic corpus content and
 * must never be labelled or reused as such — see docs/spikes/
 * S5-gutenberg-segment-identity.md for the distinction between this evidence
 * and the editor-authored corpus.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Oracle;

final class Builders {

	public static function paragraph( IdGenerator $ids, string $text ): OracleNode {
		return OracleNode::leaf( $ids->next(), 'core/paragraph', array(), '<p>', $text, '</p>' );
	}

	public static function heading( IdGenerator $ids, string $text, int $level = 2 ): OracleNode {
		return OracleNode::leaf( $ids->next(), 'core/heading', array( 'level' => $level ), "<h{$level}>", $text, "</h{$level}>" );
	}

	/**
	 * @param OracleNode[] $children
	 */
	public static function group( IdGenerator $ids, array $children ): OracleNode {
		return OracleNode::container( $ids->next(), 'core/group', array(), self::wrap( '<div class="wp-block-group">', $children, '</div>' ), $children );
	}

	/**
	 * @param OracleNode[] $columns
	 */
	public static function columns( IdGenerator $ids, array $columns ): OracleNode {
		return OracleNode::container( $ids->next(), 'core/columns', array(), self::wrap( '<div class="wp-block-columns">', $columns, '</div>' ), $columns );
	}

	/**
	 * @param OracleNode[] $children
	 */
	public static function column( IdGenerator $ids, array $children ): OracleNode {
		return OracleNode::container( $ids->next(), 'core/column', array(), self::wrap( '<div class="wp-block-column">', $children, '</div>' ), $children );
	}

	public static function separator( IdGenerator $ids ): OracleNode {
		return OracleNode::leaf( $ids->next(), 'core/separator', array(), '', '', '<hr class="wp-block-separator"/>' );
	}

	/**
	 * Builds a separators array with $prefix before the first child and
	 * $suffix after the last, and an empty string ('') between every other
	 * pair — these synthetic builders never needed non-trivial inter-child
	 * content, so every existing caller's output is byte-identical to before
	 * this model amendment. A concatenated single slot is used for the
	 * zero-child case, since there is only one slot to hold both.
	 *
	 * @param OracleNode[] $children
	 * @return string[]
	 */
	private static function wrap( string $prefix, array $children, string $suffix ): array {
		$n = count( $children );

		if ( 0 === $n ) {
			return array( $prefix . $suffix );
		}

		$separators    = array_fill( 0, $n + 1, '' );
		$separators[0] = $prefix;
		$separators[ $n ] = $suffix;

		return $separators;
	}
}
