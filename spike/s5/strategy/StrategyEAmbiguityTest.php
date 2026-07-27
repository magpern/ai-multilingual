<?php
/**
 * Spike S5 — Strategy E ambiguity tests with render gate verification.
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
require_once dirname( __DIR__ ) . '/lib/Oracle/Builders.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/CorpusImporter.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/ReconciliationSimulator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyEReconciler.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyERenderGate.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyESuppressionReason.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyE.php';

use AIMultilingual\Spike\S5\Oracle\Builders;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\OracleTree;
use AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator;
use AIMultilingual\Spike\S5\Strategy\StrategyE;
use AIMultilingual\Spike\S5\Strategy\StrategyEReconciler;
use AIMultilingual\Spike\S5\Strategy\StrategyERenderGate;

final class StrategyEAmbiguityTest extends \WP_UnitTestCase {

	private static array $all_results = array();

	public static function tearDownAfterClass(): void {
		file_put_contents(
			__DIR__ . '/../corpus/strategy-e-ambiguity.json',
			wp_json_encode( self::$all_results, JSON_PRETTY_PRINT )
		);
		parent::tearDownAfterClass();
	}

	/**
	 * @param array<string, array{block_name: string, text: string}> $before_segments
	 * @param array<string, array{block_name: string, text: string}> $after_segments
	 * @return array{rows: array<string, array<string, mixed>>, stats: array<string, int>, rematch_map: array<string, string>}
	 */
	private function sync( array $before_segments, array $after_segments ): array {
		$rows = array();
		foreach ( $before_segments as $key => $seg ) {
			$rows[ $key ] = array(
				'block_name'      => $seg['block_name'],
				'source_hash'     => ReconciliationSimulator::source_hash( $seg['text'] ),
				'translated_text' => 'TRANS:' . $seg['text'],
				'status'          => 'reviewed',
				'is_stale'        => 0,
				'error_code'      => '',
			);
		}

		return StrategyEReconciler::sync_source( $rows, $after_segments );
	}

	/** @param array<string, mixed> $result */
	private function record( string $case, array $result, array $extra = array() ): void {
		self::$all_results[ $case ] = array_merge(
			$result['stats'],
			array(
				'rematch_map' => $result['rematch_map'],
				'active_keys' => array_keys( array_filter(
					$result['rows'],
					static fn( array $row ): bool => ReconciliationSimulator::STATUS_IGNORED !== $row['status']
				) ),
			),
			$extra
		);
	}

	public function test_two_orphaned_blocks_with_identical_source_hash(): void {
		$text   = 'Identical body.';
		$before = array(
			'b:0:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => $text ),
			'b:1:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => $text ),
		);
		$after  = array(
			'b:2:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => $text ),
			'b:3:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => $text ),
		);

		$result = $this->sync( $before, $after );
		$this->record( 'two_orphans_identical_hash', $result );

		$this->assertSame( 0, $result['stats']['successful_rematch'] );
		$this->assertSame( 2, $result['stats']['ambiguous_rematch'] );
	}

	public function test_one_orphan_matching_two_new_candidates(): void {
		$text   = 'Shared orphan text.';
		$before = array(
			'b:0:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => $text ),
		);
		$after  = array(
			'b:1:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => $text ),
			'b:2:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => $text ),
		);

		$result = $this->sync( $before, $after );
		$this->record( 'one_orphan_two_new_candidates', $result );

		$this->assertSame( 0, $result['stats']['successful_rematch'] );
		$this->assertSame( 2, $result['stats']['ambiguous_rematch'] );
	}

	public function test_two_candidates_matching_one_orphan(): void {
		$text   = 'Ambiguous target.';
		$before = array(
			'b:0:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Keep.' ),
			'b:1:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => $text ),
			'b:2:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => $text ),
		);
		$after  = array(
			'b:0:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Keep.' ),
			'b:3:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => $text ),
			'b:4:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Other.' ),
		);

		$result = $this->sync( $before, $after );
		$this->record( 'two_candidates_one_orphan', $result );

		$this->assertSame( 0, $result['stats']['successful_rematch'] );
		$this->assertGreaterThan( 0, $result['stats']['ambiguous_rematch'] );
	}

	public function test_duplicate_paragraphs_reorder_uses_direct_key_not_rematch(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Same.' );
		$b    = Builders::paragraph( $ids, 'Same.' );
		$tree = new OracleTree( array( $a, $b ) );

		$before = StrategyE::extract( $tree->to_content() );
		$tree->reorder( null, 0, 1 );
		$after = StrategyE::extract( $tree->to_content() );

		$result = $this->sync( $before, $after );
		$this->record( 'duplicate_paragraphs_reorder', $result, array(
			'observed' => 'keys unchanged; identical hash at each path → false continuity via direct key, not rematch',
		) );

		$this->assertSame( 0, $result['stats']['successful_rematch'] );
		$this->assertSame( 0, $result['stats']['ambiguous_rematch'] );
	}

	public function test_duplicate_headings_swap_uses_direct_key(): void {
		$ids  = new IdGenerator();
		$a    = Builders::heading( $ids, 'Title.', 2 );
		$b    = Builders::heading( $ids, 'Title.', 2 );
		$tree = new OracleTree( array( $a, $b ) );

		$before = StrategyE::extract( $tree->to_content() );
		$tree->reorder( null, 0, 1 );
		$after  = StrategyE::extract( $tree->to_content() );

		$result = $this->sync( $before, $after );
		$this->record( 'duplicate_headings_swap', $result );

		$this->assertSame( 0, $result['stats']['successful_rematch'] );
	}

	public function test_duplicate_buttons_swap_uses_direct_key(): void {
		$content = '<!-- wp:buttons -->'
			. '<div class="wp-block-buttons"><!-- wp:button -->'
			. '<div class="wp-block-button"><a class="wp-block-button__link">Buy</a></div>'
			. '<!-- /wp:button -->'
			. '<!-- wp:button -->'
			. '<div class="wp-block-button"><a class="wp-block-button__link">Buy</a></div>'
			. '<!-- /wp:button --></div><!-- /wp:buttons -->';

		$ids  = new IdGenerator();
		$tree = new OracleTree( \AIMultilingual\Spike\S5\Oracle\CorpusImporter::from_content( $content, $ids ) );

		$before = StrategyE::extract( $tree->to_content() );
		$tree->reorder( null, 0, 1 );
		$after  = StrategyE::extract( $tree->to_content() );

		$result = $this->sync( $before, $after );
		$this->record( 'duplicate_buttons_swap', $result );

		$this->assertSame( 0, $result['stats']['successful_rematch'] );
	}

	public function test_duplicate_entire_subtrees_swap_uses_direct_key(): void {
		$ids    = new IdGenerator();
		$leaf_a = Builders::paragraph( $ids, 'Subtree.' );
		$leaf_b = Builders::paragraph( $ids, 'Subtree.' );
		$tree   = new OracleTree( array(
			Builders::group( $ids, array( $leaf_a ) ),
			Builders::group( $ids, array( $leaf_b ) ),
		) );

		$before = StrategyE::extract( $tree->to_content() );
		$tree->reorder( null, 0, 1 );
		$after  = StrategyE::extract( $tree->to_content() );

		$result = $this->sync( $before, $after );
		$this->record( 'duplicate_subtrees_swap', $result );

		$this->assertSame( 0, $result['stats']['successful_rematch'] );
	}

	public function test_unique_rematch_on_path_shift(): void {
		$before = array(
			'b:0:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Alpha.' ),
			'b:1:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Beta.' ),
		);
		$after  = array(
			'b:2:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Alpha.' ),
			'b:3:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Beta.' ),
		);

		$result = $this->sync( $before, $after );
		$this->record( 'unique_rematch_on_path_shift', $result );

		$this->assertSame( 2, $result['stats']['successful_rematch'] );
		$this->assertSame( 0, $result['stats']['ambiguous_rematch'] );
	}
}
