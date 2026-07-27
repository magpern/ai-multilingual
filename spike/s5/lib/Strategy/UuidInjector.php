<?php
/**
 * Spike S5 — inject and repair persistent UUID attributes in Gutenberg content.
 *
 * Operates on real parse_blocks()/serialize_blocks() output. Assignment applies
 * only to eligible leaf blocks (same policy as RealBlockWalker). Containers,
 * dynamic blocks, and empty leaves are skipped — they never receive UUIDs.
 *
 * Primary repair policy (StrategyFContract::REPAIR_POLICY_FIRST_WINS):
 * keep the first eligible occurrence of a UUID in document order; regenerate
 * UUIDs on all later occurrences of the same value.
 *
 * THROWAWAY CODE. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

require_once __DIR__ . '/UuidGenerator.php';
require_once __DIR__ . '/StrategyFContract.php';
require_once __DIR__ . '/StructuralPathWalker.php';
require_once __DIR__ . '/UuidBlockWalker.php';
require_once __DIR__ . '/StrategyEvaluator.php';

final class UuidInjector {

	/**
	 * @return array{
	 *   content: string,
	 *   stats: array{
	 *     uuids_generated: int,
	 *     uuids_preserved: int,
	 *     uuids_regenerated: int,
	 *     malformed_replaced: int,
	 *     duplicate_uuids_before_repair: int,
	 *     content_changed: bool,
	 *     bytes_before: int,
	 *     bytes_after: int,
	 *     bytes_added: int
	 *   },
	 *   duplicate_uuids: array<string, int>
	 * }
	 */
	public static function inject(
		string $content,
		string $repair_policy = StrategyFContract::REPAIR_POLICY_FIRST_WINS
	): array {
		$bytes_before = strlen( $content );
		$blocks       = parse_blocks( $content );

		$stats = array(
			'uuids_generated'                 => 0,
			'uuids_preserved'                 => 0,
			'uuids_regenerated'               => 0,
			'malformed_replaced'              => 0,
			'duplicate_uuids_before_repair'   => 0,
			'content_changed'                 => false,
			'bytes_before'                    => $bytes_before,
			'bytes_after'                     => $bytes_before,
			'bytes_added'                     => 0,
		);

		$seen_valid = array();
		self::count_duplicates( $blocks, $seen_valid, $stats );

		$seen_valid = array();
		$regenerated_list = array();
		self::repair_blocks( $blocks, $seen_valid, $stats, $repair_policy, $regenerated_list );

		$new_content = serialize_blocks( $blocks );
		$stats['bytes_after']     = strlen( $new_content );
		$stats['bytes_added']     = $stats['bytes_after'] - $bytes_before;
		$stats['content_changed'] = $new_content !== $content;

		return array(
			'content'            => $new_content,
			'stats'              => $stats,
			'duplicate_uuids'    => self::duplicate_map( $new_content ),
			'regenerated_uuids'  => $regenerated_list,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks
	 * @param array<string, bool>              $seen_valid
	 * @param array<string, int>               $stats
	 */
	private static function count_duplicates( array $blocks, array &$seen_valid, array &$stats ): void {
		foreach ( $blocks as &$block ) {
			if ( null === ( $block['blockName'] ?? null ) ) {
				continue;
			}

			$name = (string) $block['blockName'];
			if ( in_array( $name, StructuralPathWalker::DYNAMIC_BLOCK_NAMES, true ) ) {
				continue;
			}

			$inner = $block['innerBlocks'] ?? array();
			if ( array() !== $inner ) {
				self::count_duplicates( $block['innerBlocks'], $seen_valid, $stats );
				continue;
			}

			if ( '' === trim( (string) ( $block['innerHTML'] ?? '' ) ) ) {
				continue;
			}

			$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			$uuid  = (string) ( $attrs[ StrategyFContract::ATTR_NAME ] ?? '' );

			if ( '' === $uuid || ! StrategyFContract::is_valid_uuid( $uuid ) ) {
				continue;
			}

			if ( isset( $seen_valid[ $uuid ] ) ) {
				++$stats['duplicate_uuids_before_repair'];
			}
			$seen_valid[ $uuid ] = true;
		}
		unset( $block );
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks
	 * @param array<string, bool>              $seen_valid
	 * @param array<string, int>               $stats
	 */
	private static function repair_blocks(
		array &$blocks,
		array &$seen_valid,
		array &$stats,
		string $repair_policy,
		array &$regenerated_list
	): void {
		foreach ( $blocks as &$block ) {
			if ( null === ( $block['blockName'] ?? null ) ) {
				continue;
			}

			$name = (string) $block['blockName'];
			if ( in_array( $name, StructuralPathWalker::DYNAMIC_BLOCK_NAMES, true ) ) {
				continue;
			}

			$inner = $block['innerBlocks'] ?? array();
			if ( array() !== $inner ) {
				self::repair_blocks( $block['innerBlocks'], $seen_valid, $stats, $repair_policy, $regenerated_list );
				continue;
			}

			if ( '' === trim( (string) ( $block['innerHTML'] ?? '' ) ) ) {
				continue;
			}

			if ( ! is_array( $block['attrs'] ?? null ) ) {
				$block['attrs'] = array();
			}

			$attrs    = $block['attrs'];
			$old_uuid = (string) ( $attrs[ StrategyFContract::ATTR_NAME ] ?? '' );
			$uuid     = $old_uuid;
			$need_new = false;
			$is_regen = false;

			if ( '' === $uuid ) {
				$need_new = true;
			} elseif ( ! StrategyFContract::is_valid_uuid( $uuid ) ) {
				$need_new = true;
				++$stats['malformed_replaced'];
			} elseif ( isset( $seen_valid[ $uuid ] ) ) {
				if ( StrategyFContract::REPAIR_POLICY_FIRST_WINS === $repair_policy ) {
					$need_new = true;
					$is_regen = true;
					++$stats['uuids_regenerated'];
				}
			}

			if ( $need_new ) {
				$uuid = UuidGenerator::v4();
				$block['attrs'][ StrategyFContract::ATTR_NAME ] = $uuid;
				++$stats['uuids_generated'];
				if ( $is_regen ) {
					$regenerated_list[ $uuid ] = true;
				}
			} else {
				++$stats['uuids_preserved'];
			}

			$seen_valid[ $uuid ] = true;
		}
		unset( $block );
	}

	/** @return array<string, int> */
	private static function duplicate_map( string $content ): array {
		$counts = UuidBlockWalker::count_uuids( $content );
		$dupes  = array();

		foreach ( $counts as $uuid => $count ) {
			if ( $count > 1 ) {
				$dupes[ $uuid ] = $count;
			}
		}

		return $dupes;
	}
}
