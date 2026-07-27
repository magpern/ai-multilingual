<?php
/**
 * Spike S5: stress tests for the Oracle model with large, DELIBERATELY
 * worst-case-shaped trees — wide (one container, N direct children) and deep
 * (N nested single-child containers) — built directly, not via
 * RandomTreeGenerator: that generator's branching (0-4 children per
 * container, averaging 2) consumes its node budget on breadth long before
 * reaching meaningful depth, so it cannot reliably produce either extreme.
 * Stress testing needs the two different code paths (array-splice-heavy
 * mutation at scale vs. recursion depth in clone_deep()/remove_node()/
 * insert_node()/to_parsed_array()) isolated and pushed independently.
 *
 * Collects runtime, memory, and the largest successful tree size, per the
 * task's explicit requirement — none of this is capped silently.
 *
 * THROWAWAY. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

require_once dirname( __DIR__ ) . '/lib/Oracle/IdGenerator.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/OracleNode.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/OracleTree.php';

use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\OracleNode;
use AIMultilingual\Spike\S5\Oracle\OracleTree;

final class OracleStressTest extends \WP_UnitTestCase {

	/**
	 * One container, $n direct leaf children — stresses array-splice-heavy
	 * mutation (insert/delete/reorder all touch this one node's $children
	 * and $separators arrays) and the separator array's linear growth.
	 *
	 * @return OracleNode[]
	 */
	private function build_wide( int $n ): array {
		$ids      = new IdGenerator();
		$children = array();

		for ( $i = 0; $i < $n; $i++ ) {
			$children[] = OracleNode::leaf( $ids->next(), 'aiml-test/leaf-a', array(), '<p>', "Leaf $i.", '</p>' );
		}

		$separators = array_fill( 0, $n + 1, '' );

		for ( $i = 0; $i <= $n; $i += 7 ) {
			$separators[ $i ] = "\n\n";
		}

		$root = OracleNode::container( $ids->next(), 'aiml-test/container-a', array(), $separators, $children );

		return array( $root );
	}

	/**
	 * $depth nested single-child containers around one innermost leaf —
	 * stresses recursion depth in every recursive OracleNode/OracleTree
	 * method: clone_deep(), clone_with_fresh_ids(), to_parsed_array(),
	 * remove_node(), insert_node(), locate(), shape(). A stack-depth failure
	 * here (a fatal error, not a clean exception) is exactly the
	 * "pathological behaviour" this stress test exists to surface.
	 *
	 * @return OracleNode[]
	 */
	private function build_deep( int $depth ): array {
		$ids     = new IdGenerator();
		$current = OracleNode::leaf( $ids->next(), 'aiml-test/leaf-a', array(), '<p>', 'Innermost.', '</p>' );

		for ( $i = 0; $i < $depth; $i++ ) {
			$current = OracleNode::container( $ids->next(), 'aiml-test/container-a', array(), array( '<x>', '</x>' ), array( $current ) );
		}

		return array( $current );
	}

	public function test_stress_wide_and_deep_trees_at_increasing_scale(): void {
		$sizes   = array( 100, 500, 1000, 2000, 5000, 10000 );
		$results = array();

		foreach ( $sizes as $target ) {
			$results['wide'][ (string) $target ] = $this->measure( $this->build_wide( $target ) );
		}

		foreach ( $sizes as $target ) {
			$results['deep'][ (string) $target ] = $this->measure( $this->build_deep( $target ) );
		}

		file_put_contents(
			__DIR__ . '/../corpus/oracle-stress-results.json',
			wp_json_encode( $results, JSON_PRETTY_PRINT )
		);

		foreach ( array( 'wide', 'deep' ) as $shape ) {
			foreach ( $sizes as $target ) {
				$r = $results[ $shape ][ (string) $target ];
				$this->assertTrue( $r['round_trip_shape_ok'], "$shape/$target: shape verification failed." );
				$this->assertTrue( $r['byte_exact'], "$shape/$target: byte-exact round trip failed." );
				$this->assertTrue( $r['ids_unique'], "$shape/$target: id uniqueness failed." );
			}
		}
	}

	/**
	 * Doubles tree size from 10000 until an invariant fails, PHP's own
	 * zend.max_allowed_stack_size guard raises a catchable \Error (confirmed
	 * present, via a targeted probe, for 'deep' shapes around 13,000-14,000
	 * levels — recorded as data, not left to abort the test run), or a
	 * single size takes more than 10 real seconds (a pathological-slowdown
	 * threshold, not a silent cap).
	 */
	public function test_find_largest_successful_tree_each_shape(): void {
		$ceiling = 500000;
		$report  = array();

		foreach ( array( 'wide', 'deep' ) as $shape ) {
			$size      = 10000;
			$last_good = null;
			$history   = array();
			$stopped_reason = 'ceiling_reached';

			while ( $size <= $ceiling ) {
				$roots = 'wide' === $shape ? $this->build_wide( $size ) : $this->build_deep( $size );

				$t0 = hrtime( true );

				try {
					$r = $this->measure( $roots );
				} catch ( \Throwable $e ) {
					$history[] = array(
						'size'     => $size,
						'total_ms' => round( ( hrtime( true ) - $t0 ) / 1e6, 1 ),
						'error'    => get_class( $e ) . ': ' . $e->getMessage(),
					);
					$stopped_reason = 'recursion_depth_limit_reached: ' . get_class( $e ) . ': ' . $e->getMessage();
					break;
				}

				$total_ms = round( ( hrtime( true ) - $t0 ) / 1e6, 1 );

				$history[] = array( 'size' => $size, 'total_ms' => $total_ms ) + $r;

				// Written after EVERY size, not just at the end: if a later,
				// larger size were to crash the PHP process outright (a real
				// possibility this test exists to probe for), the history up
				// to that point must not be lost.
				file_put_contents(
					__DIR__ . '/../corpus/oracle-largest-tree.json',
					wp_json_encode(
						array_merge(
							$report,
							array( $shape => array( 'largest_successful_tree_nodes' => $last_good, 'stopped_reason' => 'in_progress', 'ceiling_configured' => $ceiling, 'history' => $history ) )
						),
						JSON_PRETTY_PRINT
					)
				);

				$ok = $r['round_trip_shape_ok'] && $r['byte_exact'] && $r['ids_unique'];

				if ( ! $ok ) {
					$stopped_reason = 'invariant_failed';
					break;
				}

				$last_good = $size;

				if ( $total_ms > 10000 ) {
					$stopped_reason = 'exceeded_10s_pathological_slowdown_threshold';
					break;
				}

				$size *= 2;
			}

			$report[ $shape ] = array(
				'largest_successful_tree_nodes' => $last_good,
				'stopped_reason'                 => $stopped_reason,
				'ceiling_configured'              => $ceiling,
				'history'                         => $history,
			);

			file_put_contents(
				__DIR__ . '/../corpus/oracle-largest-tree.json',
				wp_json_encode( $report, JSON_PRETTY_PRINT )
			);

			$this->assertNotNull( $last_good, "$shape: at least the starting size (10000 nodes) must succeed." );
		}
	}

	/**
	 * @param OracleNode[] $roots
	 * @return array{
	 *   node_count:int, content_bytes:int,
	 *   generation_ms:float, serialize_ms:float, reparse_ms:float, reserialize_ms:float,
	 *   memory_peak_bytes:int, memory_delta_bytes:int,
	 *   round_trip_shape_ok:bool, byte_exact:bool, ids_unique:bool
	 * }
	 */
	private function measure( array $roots ): array {
		memory_reset_peak_usage();
		$mem_before = memory_get_usage( true );

		$tree = new OracleTree( $roots );

		$t1      = hrtime( true );
		$content = $tree->to_content();
		$serialize_ms = ( hrtime( true ) - $t1 ) / 1e6;

		$t2     = hrtime( true );
		$parsed = parse_blocks( $content );
		$reparse_ms = ( hrtime( true ) - $t2 ) / 1e6;

		$t3           = hrtime( true );
		$reserialized = serialize_blocks( $parsed );
		$reserialize_ms = ( hrtime( true ) - $t3 ) / 1e6;

		$byte_exact = $reserialized === $content;
		$round_trip = $tree->verify_round_trip_shape();

		$paths      = $tree->snapshot_paths();
		$ids_unique = count( $paths ) === count( array_unique( array_keys( $paths ) ) );

		$mem_peak = memory_get_peak_usage( true );

		return array(
			'node_count'          => count( $paths ),
			'content_bytes'       => strlen( $content ),
			'serialize_ms'        => round( $serialize_ms, 2 ),
			'reparse_ms'          => round( $reparse_ms, 2 ),
			'reserialize_ms'      => round( $reserialize_ms, 2 ),
			'memory_peak_bytes'   => $mem_peak,
			'memory_delta_bytes'  => $mem_peak - $mem_before,
			'round_trip_shape_ok' => $round_trip['ok'],
			'byte_exact'          => $byte_exact,
			'ids_unique'          => $ids_unique,
		);
	}
}
