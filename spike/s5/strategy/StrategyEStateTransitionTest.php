<?php
/**
 * Spike S5 — Strategy E state transition tests.
 *
 * THROWAWAY. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

require_once dirname( __DIR__ ) . '/lib/Strategy/ReconciliationSimulator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyEReconciler.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyERenderGate.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyESuppressionReason.php';

use AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator;
use AIMultilingual\Spike\S5\Strategy\StrategyEReconciler;
use AIMultilingual\Spike\S5\Strategy\StrategyERenderGate;
use AIMultilingual\Spike\S5\Strategy\StrategyESuppressionReason;

final class StrategyEStateTransitionTest extends \WP_UnitTestCase {

	private static array $all_results = array();

	public static function tearDownAfterClass(): void {
		file_put_contents(
			__DIR__ . '/../corpus/strategy-e-state-transitions.json',
			wp_json_encode( self::$all_results, JSON_PRETTY_PRINT )
		);
		parent::tearDownAfterClass();
	}

	private function row( string $text ): array {
		return array(
			'block_name'      => 'core/paragraph',
			'source_hash'     => ReconciliationSimulator::source_hash( $text ),
			'translated_text' => 'TRANS:' . $text,
			'status'          => 'reviewed',
			'is_stale'        => 0,
			'error_code'      => '',
		);
	}

	public function test_reviewed_to_stale_to_suppressed(): void {
		$rows  = array( 'b:0:core/paragraph:content' => $this->row( 'Original.' ) );
		$after = array( 'b:0:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Edited.' ) );
		$sync  = StrategyEReconciler::sync_source( $rows, $after );
		$gate  = StrategyERenderGate::resolve( 'b:0:core/paragraph:content', $sync['rows']['b:0:core/paragraph:content'], $after['b:0:core/paragraph:content'], array() );
		self::$all_results['reviewed_stale_suppressed'] = array( 'reason' => $gate['reason'], 'renders' => $gate['renders'] );
		$this->assertFalse( $gate['renders'] );
		$this->assertSame( StrategyESuppressionReason::DISPLACED, $gate['reason'] );
	}

	public function test_reviewed_to_orphaned_to_suppressed(): void {
		$rows  = array( 'b:0:core/paragraph:content' => $this->row( 'Gone.' ) );
		$after = array();
		$sync  = StrategyEReconciler::sync_source( $rows, $after );
		$gate  = StrategyERenderGate::resolve( 'b:0:core/paragraph:content', $sync['rows']['b:0:core/paragraph:content'] ?? null, array( 'block_name' => 'core/paragraph', 'text' => 'Gone.' ), array() );
		self::$all_results['reviewed_orphan_suppressed'] = array( 'reason' => $gate['reason'] );
		$this->assertSame( StrategyESuppressionReason::IGNORED, $gate['reason'] );
	}

	public function test_reviewed_to_uniquely_rematched_to_rendered(): void {
		$rows  = array( 'b:0:core/paragraph:content' => $this->row( 'Alpha.' ) );
		$after = array( 'b:1:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Alpha.' ) );
		$sync  = StrategyEReconciler::sync_source( $rows, $after );
		$gate  = StrategyERenderGate::resolve( 'b:1:core/paragraph:content', $sync['rows']['b:1:core/paragraph:content'], $after['b:1:core/paragraph:content'], array( 'ambiguous_new_keys' => $sync['ambiguous_new_keys'], 'unresolved_new_keys' => $sync['unresolved_new_keys'] ) );
		self::$all_results['reviewed_rematched_rendered'] = array( 'renders' => $gate['renders'], 'status' => $sync['rows']['b:1:core/paragraph:content']['status'] );
		$this->assertTrue( $gate['renders'] );
		$this->assertSame( 'reviewed', $sync['rows']['b:1:core/paragraph:content']['status'] );
	}

	public function test_reviewed_to_ambiguous_to_suppressed(): void {
		$rows = array(
			'b:0:core/paragraph:content' => $this->row( 'Same.' ),
			'b:1:core/paragraph:content' => $this->row( 'Same.' ),
		);
		$after = array(
			'b:2:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Same.' ),
			'b:3:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Same.' ),
		);
		$sync = StrategyEReconciler::sync_source( $rows, $after );
		$gate = StrategyERenderGate::resolve( 'b:2:core/paragraph:content', null, $after['b:2:core/paragraph:content'], array( 'ambiguous_new_keys' => $sync['ambiguous_new_keys'], 'unresolved_new_keys' => $sync['unresolved_new_keys'] ) );
		self::$all_results['reviewed_ambiguous_suppressed'] = array( 'reason' => $gate['reason'] );
		$this->assertSame( StrategyESuppressionReason::NO_ROW, $gate['reason'] );
	}

	public function test_key_reused_unrelated_source_stays_suppressed(): void {
		$rows  = array( 'b:0:core/paragraph:content' => $this->row( 'Old.' ) );
		$after = array( 'b:0:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'New.' ) );
		$sync  = StrategyEReconciler::sync_source( $rows, $after );
		$gate  = StrategyERenderGate::resolve( 'b:0:core/paragraph:content', $sync['rows']['b:0:core/paragraph:content'], $after['b:0:core/paragraph:content'], array() );
		self::$all_results['key_reuse_suppressed'] = array( 'reason' => $gate['reason'], 'renders' => $gate['renders'] );
		$this->assertFalse( $gate['renders'] );
	}
}
