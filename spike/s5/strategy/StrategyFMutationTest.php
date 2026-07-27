<?php
/**
 * Spike S5 — Strategy F mutation tests.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

require_once dirname( __DIR__ ) . '/lib/Strategy/ReconciliationSimulator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFContract.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFRenderGate.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFSuppressionReason.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/UuidGenerator.php';

use AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator;
use AIMultilingual\Spike\S5\Strategy\StrategyFContract;
use AIMultilingual\Spike\S5\Strategy\StrategyFRenderGate;
use AIMultilingual\Spike\S5\Strategy\StrategyFSuppressionReason;
use AIMultilingual\Spike\S5\Strategy\UuidGenerator;

final class StrategyFMutationTest extends \WP_UnitTestCase {

	private static array $all_results = array();

	public static function tearDownAfterClass(): void {
		file_put_contents(
			__DIR__ . '/../corpus/strategy-f-mutations.json',
			wp_json_encode( self::$all_results, JSON_PRETTY_PRINT )
		);
		parent::tearDownAfterClass();
	}

	private function segment( string $text, string $uuid = '' ): array {
		return array( 'block_name' => 'core/paragraph', 'text' => $text, 'uuid' => $uuid );
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

	public function test_mutation_render_on_duplicate_uuid(): void {
		$uuid = UuidGenerator::v4();
		$seg  = $this->segment( 'X.', $uuid );
		$row  = $this->row( 'X.' );
		$real = StrategyFRenderGate::resolve( StrategyFContract::segment_key( $uuid ), $row, $seg, array( 'duplicate_uuids' => array( $uuid => true ) ) );
		$broken = $this->broken_ignore_duplicates( $row, $seg );
		self::$all_results['duplicate_uuid'] = array( 'real' => $real['renders'], 'broken' => $broken );
		$this->assertFalse( $real['renders'] );
		$this->assertTrue( $broken );
	}

	public function test_mutation_no_uuid_validation(): void {
		$seg  = $this->segment( 'X.', 'not-valid' );
		$row  = $this->row( 'X.' );
		$real = StrategyFRenderGate::resolve( 'b:not-valid:content', $row, $seg, array() );
		self::$all_results['no_validation'] = array( 'real' => $real['renders'] );
		$this->assertFalse( $real['renders'] );
	}

	public function test_mutation_render_unknown_uuid(): void {
		$uuid = UuidGenerator::v4();
		$seg  = $this->segment( 'X.', $uuid );
		$real = StrategyFRenderGate::resolve( StrategyFContract::segment_key( $uuid ), null, $seg, array() );
		self::$all_results['unknown_uuid'] = array( 'real' => $real['renders'] );
		$this->assertFalse( $real['renders'] );
	}

	public function test_mutation_skip_block_type_check(): void {
		$uuid = UuidGenerator::v4();
		$row  = $this->row( 'Text.' );
		$seg  = array( 'block_name' => 'core/heading', 'text' => 'Text.', 'uuid' => $uuid );
		$real = StrategyFRenderGate::resolve( StrategyFContract::segment_key( $uuid ), $row, $seg, array() );
		self::$all_results['block_type'] = array( 'real' => $real['renders'] );
		$this->assertFalse( $real['renders'] );
	}

	public function test_mutation_allow_stale_render(): void {
		$uuid = UuidGenerator::v4();
		$row  = $this->row( 'Same.' );
		$seg  = $this->segment( 'Different.', $uuid );
		$real = StrategyFRenderGate::resolve( StrategyFContract::segment_key( $uuid ), $row, $seg, array() );
		self::$all_results['stale_hash'] = array( 'real' => $real['renders'] );
		$this->assertFalse( $real['renders'] );
	}

	private function broken_ignore_duplicates( array $row, array $seg ): bool {
		return '' !== ( $row['translated_text'] ?? '' );
	}
}
