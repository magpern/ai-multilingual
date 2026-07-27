<?php
/**
 * Spike S5 — sync injected UUIDs from serialized content onto OracleNode attrs.
 *
 * Matches parser eligible blocks to oracle leaves by block_name + source_hash,
 * falling back to document order only among still-unmatched leaves (handles
 * empty column wrappers visible to the parser but absent from the oracle).
 *
 * THROWAWAY CODE. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

use AIMultilingual\Spike\S5\Oracle\OracleTree;

final class StrategyFUuidSync {

	/**
	 * Write aimlBlockId from injected content onto eligible oracle leaves.
	 */
	public static function apply( OracleTree $tree, string $injected_content ): void {
		$blocks    = UuidBlockWalker::walk_eligible( $injected_content );
		$leaf_pool = self::leaf_pool( $tree );

		foreach ( $blocks as $block ) {
			$uuid = $block['uuid'];
			if ( '' === $uuid || ! StrategyFContract::is_valid_uuid( $uuid ) ) {
				continue;
			}

			$hash  = ReconciliationSimulator::source_hash( $block['text'] );
			$match = self::match_leaf( $leaf_pool, $block['block_name'], $hash );

			if ( null === $match ) {
				continue;
			}

			$node = $tree->find( $match );
			if ( null !== $node && $node->is_leaf() ) {
				$node->attrs[ StrategyFContract::ATTR_NAME ] = $uuid;
			}
		}
	}

	/** @return array<int, array{id: int, block_name: string, hash: string, used: bool}> */
	private static function leaf_pool( OracleTree $tree ): array {
		$pool = array();

		foreach ( StrategyEvaluator::leaf_ids_in_document_order( $tree ) as $id ) {
			$node = $tree->find( $id );
			if ( null === $node || ! $node->is_leaf() ) {
				continue;
			}

			$pool[] = array(
				'id'         => $id,
				'block_name' => (string) $node->block_name,
				'hash'       => ReconciliationSimulator::source_hash(
					(string) $node->prefix . (string) $node->text . (string) $node->suffix
				),
				'used'       => false,
			);
		}

		return $pool;
	}

	/**
	 * @param array<int, array{id: int, block_name: string, hash: string, used: bool}> $pool
	 */
	private static function match_leaf( array &$pool, string $block_name, string $hash ): ?int {
		foreach ( $pool as &$leaf ) {
			if ( $leaf['used'] ) {
				continue;
			}
			if ( $leaf['block_name'] !== $block_name || $leaf['hash'] !== $hash ) {
				continue;
			}
			$leaf['used'] = true;
			return $leaf['id'];
		}

		return null;
	}
}
