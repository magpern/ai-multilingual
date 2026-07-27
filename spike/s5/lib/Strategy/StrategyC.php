<?php
/**
 * Spike S5 — Strategy C: structural path + block name + field, no reconciliation.
 *
 * Per docs/plans/AI_MULTILINGUAL_PLANNING.md § Strategies:
 *
 *   Key shape: b:<structural_path>:<block_name>:content
 *   Example:   b:1.2.0:core/paragraph:content
 *
 * Key inputs: structural path (StructuralPathWalker), block_name, literal
 * field suffix `content`. Excluded: source text, source_hash, attrs_fingerprint,
 * position among eligible-only blocks, UUID, registry id, reconciliation memory.
 * Recomputed fresh every extraction.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class StrategyC {

	public const NAME = 'C';

	public const FIELD_SUFFIX = 'content';

	public static function segment_key( string $structural_path, string $block_name ): string {
		return 'b:' . $structural_path . ':' . $block_name . ':' . self::FIELD_SUFFIX;
	}

	/**
	 * @return array<string, array{block_name: string, text: string, path: string}>
	 */
	public static function extract( string $content ): array {
		$eligible = RealBlockWalker::walk_eligible( $content );
		$segments = array();

		foreach ( $eligible as $block ) {
			$key = self::segment_key( $block['path'], $block['block_name'] );
			$segments[ $key ] = array(
				'block_name' => $block['block_name'],
				'text'       => $block['text'],
				'path'       => $block['path'],
			);
		}

		return $segments;
	}
}
