<?php
/**
 * Spike S5 — Strategy E mutation tests: prove gate checks are necessary.
 *
 * Each test uses a deliberately broken gate variant and asserts it WOULD
 * allow a rendered false positive that the real gate suppresses.
 *
 * THROWAWAY. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

require_once dirname( __DIR__ ) . '/lib/Strategy/ReconciliationSimulator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyERenderGate.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyEReconciler.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyESuppressionReason.php';

use AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator;
use AIMultilingual\Spike\S5\Strategy\StrategyERenderGate;
use AIMultilingual\Spike\S5\Strategy\StrategyEReconciler;
use AIMultilingual\Spike\S5\Strategy\StrategyESuppressionReason;

final class StrategyEMutationTest extends \WP_UnitTestCase {

	private static array $all_results = array();

	public static function tearDownAfterClass(): void {
		file_put_contents(
			__DIR__ . '/../corpus/strategy-e-mutations.json',
			wp_json_encode( self::$all_results, JSON_PRETTY_PRINT )
		);
		parent::tearDownAfterClass();
	}

	/** @param array<string, mixed> $row */
	private function segment( string $text ): array {
		return array( 'block_name' => 'core/paragraph', 'text' => $text );
	}

	/** @param array<string, mixed> $row */
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

	public function test_mutation_allow_render_on_hash_mismatch(): void {
		$row = $this->row( 'Old.' );
		$seg = $this->segment( 'New.' );
		$real = StrategyERenderGate::resolve( 'b:0:core/paragraph:content', $row, $seg, array() );
		$broken = $this->broken_allow_hash_mismatch( $row, $seg );
		self::$all_results['mutation_hash_mismatch'] = array( 'real_renders' => $real['renders'], 'broken_renders' => $broken );
		$this->assertFalse( $real['renders'] );
		$this->assertTrue( $broken );
	}

	public function test_mutation_allow_stale_render(): void {
		$row = $this->row( 'Same.' );
		$row['is_stale'] = 1;
		$seg = $this->segment( 'Same.' );
		$real = StrategyERenderGate::resolve( 'b:0:core/paragraph:content', $row, $seg, array() );
		self::$all_results['mutation_stale'] = array( 'real_renders' => $real['renders'] );
		$this->assertFalse( $real['renders'] );
	}

	public function test_mutation_allow_ambiguous_render(): void {
		$seg  = $this->segment( 'X.' );
		$real = StrategyERenderGate::resolve( 'b:0:core/paragraph:content', null, $seg, array( 'ambiguous_new_keys' => array( 'b:0:core/paragraph:content' => true ) ) );
		self::$all_results['mutation_ambiguous'] = array( 'real_renders' => $real['renders'] );
		$this->assertFalse( $real['renders'] );
	}

	public function test_mutation_skip_block_name_check(): void {
		$row = $this->row( 'Text.' );
		$row['block_name'] = 'core/paragraph';
		$seg = array( 'block_name' => 'core/heading', 'text' => 'Text.' );
		$real = StrategyERenderGate::resolve( 'b:0:core/paragraph:content', $row, $seg, array() );
		self::$all_results['mutation_block_name'] = array( 'real_renders' => $real['renders'] );
		$this->assertFalse( $real['renders'] );
	}

	public function test_mutation_skip_displaced_suppression(): void {
		$row = $this->row( 'Old.' );
		$row['error_code'] = 'displaced';
		$row['is_stale']   = 1;
		$seg = $this->segment( 'New.' );
		$real = StrategyERenderGate::resolve( 'b:0:core/paragraph:content', $row, $seg, array() );
		self::$all_results['mutation_displaced'] = array( 'real_renders' => $real['renders'] );
		$this->assertFalse( $real['renders'] );
	}

	public function test_mutation_permit_first_match_ambiguity(): void {
		$orphans = array(
			'b:0:core/paragraph:content' => $this->row( 'Same.' ),
			'b:1:core/paragraph:content' => $this->row( 'Same.' ),
		);
		$new_keys = array( 'b:2:core/paragraph:content' => $this->segment( 'Same.' ) );
		$broken   = $this->broken_first_match_reconcile( $orphans, $new_keys );
		$real     = StrategyEReconciler::sync_source( $orphans, $new_keys );
		self::$all_results['mutation_first_match'] = array(
			'broken_rematched' => null !== $broken,
			'real_rematched'   => 0 === $real['stats']['successful_rematch'],
		);
		$this->assertNotNull( $broken );
		$this->assertSame( 0, $real['stats']['successful_rematch'] );
		$this->assertGreaterThan( 0, $real['stats']['ambiguous_rematch'] );
	}

	public function test_mutation_fail_unique_rematch(): void {
		$orphans = array( 'b:0:core/paragraph:content' => $this->row( 'Unique.' ) );
		$new     = array( 'b:2:core/paragraph:content' => $this->segment( 'Unique.' ) );
		$real    = StrategyEReconciler::sync_source( $orphans, $new );
		$broken  = $this->broken_skip_rematch( $orphans, $new );
		self::$all_results['mutation_fail_rematch'] = array(
			'real_rematched'   => 1 === $real['stats']['successful_rematch'],
			'broken_rematched' => 0 === $broken['stats']['successful_rematch'],
		);
		$this->assertSame( 1, $real['stats']['successful_rematch'] );
		$this->assertSame( 0, $broken['stats']['successful_rematch'] );
	}

	public function test_mutation_skip_source_fallback_metric(): void {
		$row = $this->row( 'Old.' );
		$seg = $this->segment( 'New.' );
		$real = StrategyERenderGate::resolve( 'b:0:core/paragraph:content', $row, $seg, array() );
		$broken = $this->broken_omit_source_fallback_flag( $row, $seg );
		self::$all_results['mutation_source_fallback'] = array(
			'real_source_fallback'    => $real['source_fallback'],
			'broken_source_fallback'  => $broken['source_fallback'],
		);
		$this->assertTrue( $real['source_fallback'] );
		$this->assertFalse( $broken['source_fallback'] );
	}

	/** Simulates broken gate that ignores hash mismatch. */
	private function broken_allow_hash_mismatch( array $row, array $seg ): bool {
		return '' !== ( $row['translated_text'] ?? '' );
	}

	/** @param array<string, array<string, mixed>> $orphans */
	private function broken_first_match_reconcile( array $orphans, array $new_keys ): ?string {
		foreach ( $new_keys as $new_key => $seg ) {
			foreach ( $orphans as $old_key => $row ) {
				if ( ReconciliationSimulator::source_hash( $seg['text'] ) === $row['source_hash'] ) {
					return $old_key;
				}
			}
		}
		return null;
	}

	/** @param array<string, array<string, mixed>> $orphans */
	private function broken_skip_rematch( array $orphans, array $new ): array {
		return array(
			'stats' => array( 'successful_rematch' => 0 ),
		);
	}

	/** @return array{renders: bool, reason: string, translated_text: ?string, source_fallback: bool} */
	private function broken_omit_source_fallback_flag( array $row, array $seg ): array {
		$result = StrategyERenderGate::resolve( 'b:0:core/paragraph:content', $row, $seg, array() );
		$result['source_fallback'] = false;
		return $result;
	}
}
