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
		return OracleNode::container( $ids->next(), 'core/group', array(), '<div class="wp-block-group">', $children, '</div>' );
	}

	/**
	 * @param OracleNode[] $columns
	 */
	public static function columns( IdGenerator $ids, array $columns ): OracleNode {
		return OracleNode::container( $ids->next(), 'core/columns', array(), '<div class="wp-block-columns">', $columns, '</div>' );
	}

	/**
	 * @param OracleNode[] $children
	 */
	public static function column( IdGenerator $ids, array $children ): OracleNode {
		return OracleNode::container( $ids->next(), 'core/column', array(), '<div class="wp-block-column">', $children, '</div>' );
	}

	public static function separator( IdGenerator $ids ): OracleNode {
		return OracleNode::leaf( $ids->next(), 'core/separator', array(), '', '', '<hr class="wp-block-separator"/>' );
	}
}
