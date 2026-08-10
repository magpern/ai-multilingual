<?php
/**
 * Official baseline-v1.1.0 evidence pack tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Quality;

use AIMultilingual\Quality\EvidencePack;
use AIMultilingual\Quality\FrozenEvidenceGuard;
use AIMultilingual\Quality\QualityComparer;
use AIMultilingual\Quality\QualityScorer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Quality\EvidencePack
 * @covers \AIMultilingual\Quality\FrozenEvidenceGuard
 */
final class BaselineV110PackTest extends TestCase {

	private string $pack_dir;

	protected function setUp(): void {
		parent::setUp();
		$this->pack_dir = dirname( __DIR__, 2 ) . '/quality/baselines/baseline-v1.1.0';
		if ( ! is_dir( $this->pack_dir ) ) {
			$this->markTestSkipped( 'Official baseline-v1.1.0 pack not present.' );
		}
	}

	public function test_pack_loads_and_fingerprints_match(): void {
		$pack     = new EvidencePack( $this->pack_dir );
		$manifest = $pack->load_manifest();
		$this->assertSame( 'baseline-v1.1.0', $manifest['pack_label'] ?? '' );
		$this->assertSame( 'C1.0', $manifest['corpus_version'] ?? '' );
		$this->assertSame( 'v1.1.0', $manifest['subject_ref'] ?? '' );
		$this->assertSame( 60, (int) ( $manifest['cases_evaluated'] ?? 0 ) );
		$this->assertFalse( (bool) ( $manifest['field_semantics_in_prompt'] ?? true ) );
		$this->assertFalse( (bool) ( $manifest['store_writes'] ?? true ) );

		$gens = $pack->load_generations();
		$this->assertCount( 60, $gens );

		$guard  = new FrozenEvidenceGuard();
		$result = $guard->verify( $pack );
		$this->assertTrue( $result['ok'], 'Frozen fingerprints must match.' );
	}

	public function test_deterministic_replay_matches_frozen_summary(): void {
		$pack   = new EvidencePack( $this->pack_dir );
		$frozen = $pack->load_scores();
		$replay = ( new QualityScorer() )->score_pack( $pack );
		$this->assertSame(
			(int) ( $frozen['summary']['critical_failures'] ?? -1 ),
			(int) ( $replay['summary']['critical_failures'] ?? -2 )
		);
		$this->assertSame(
			(int) ( $frozen['summary']['pass_count'] ?? -1 ),
			(int) ( $replay['summary']['pass_count'] ?? -2 )
		);
	}

	public function test_human_b10_primary_and_dual_preserved(): void {
		$pack  = new EvidencePack( $this->pack_dir );
		$human = $pack->load_human( 'B1.0' );
		$this->assertIsArray( $human );
		$reviews = (array) ( $human['reviews'] ?? array() );
		$dual    = (array) ( $human['dual_review'] ?? array() );
		$this->assertCount( 60, $reviews );
		$this->assertGreaterThanOrEqual( 12, count( $dual ) );
		foreach ( $dual as $case_id => $pair ) {
			$pair = (array) $pair;
			$this->assertArrayHasKey( 'primary', $pair, $case_id );
			$this->assertArrayHasKey( 'secondary', $pair, $case_id );
			$this->assertSame( 'reviewer_a', $pair['primary']['reviewer'] ?? '' );
			$this->assertSame( 'reviewer_b', $pair['secondary']['reviewer'] ?? '' );
		}
	}

	public function test_self_compare_passes_critical_gate(): void {
		$pack       = new EvidencePack( $this->pack_dir );
		$comparer   = new QualityComparer();
		$comparison = $comparer->compare( $pack, $pack );
		$this->assertTrue( $comparer->passes_zero_new_critical_gate( $comparison ) );
	}
}
