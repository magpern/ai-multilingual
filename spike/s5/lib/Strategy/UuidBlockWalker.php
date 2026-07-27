<?php
/**
 * Spike S5 — extract eligible blocks with UUID from serialized Gutenberg content.
 *
 * THROWAWAY CODE. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class UuidBlockWalker {

	/**
	 * @return array<int, array{
	 *   uuid: string,
	 *   block_name: string,
	 *   text: string,
	 *   attrs: array<string, mixed>
	 * }>
	 */
	public static function walk_eligible( string $content ): array {
		$results = array();

		self::walk_blocks( parse_blocks( $content ), $results );

		return $results;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks
	 * @param array<int, array{uuid: string, block_name: string, text: string, attrs: array<string, mixed>}> $results
	 */
	private static function walk_blocks( array $blocks, array &$results ): void {
		foreach ( $blocks as $block ) {
			if ( null === ( $block['blockName'] ?? null ) ) {
				continue;
			}

			$name = (string) $block['blockName'];

			if ( in_array( $name, StructuralPathWalker::DYNAMIC_BLOCK_NAMES, true ) ) {
				continue;
			}

			$inner = $block['innerBlocks'] ?? array();

			if ( array() !== $inner ) {
				self::walk_blocks( $inner, $results );
				continue;
			}

			$text = (string) ( $block['innerHTML'] ?? '' );
			if ( '' === trim( $text ) ) {
				continue;
			}

			$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			$uuid  = (string) ( $attrs[ StrategyFContract::ATTR_NAME ] ?? '' );

			$results[] = array(
				'uuid'       => $uuid,
				'block_name' => $name,
				'text'       => $text,
				'attrs'      => $attrs,
			);
		}
	}

	/**
	 * @return array<string, int> UUID => occurrence count among eligible blocks.
	 */
	public static function count_uuids( string $content ): array {
		$counts = array();

		foreach ( self::walk_eligible( $content ) as $block ) {
			$uuid = $block['uuid'];
			if ( '' === $uuid ) {
				continue;
			}
			$counts[ $uuid ] = ( $counts[ $uuid ] ?? 0 ) + 1;
		}

		return $counts;
	}
}
