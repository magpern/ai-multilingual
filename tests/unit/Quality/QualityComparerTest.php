<?php
/**
 * QualityComparer unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Quality;

use AIMultilingual\Quality\DeterministicScorer;
use AIMultilingual\Quality\EvidencePack;
use AIMultilingual\Quality\QualityComparer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Quality\QualityComparer
 */
final class QualityComparerTest extends TestCase {

	private string $tmp;

	protected function setUp(): void {
		parent::setUp();
		$this->tmp = sys_get_temp_dir() . '/aiml-compare-' . uniqid( '', true );
		mkdir( $this->tmp, 0755, true );
	}

	protected function tearDown(): void {
		$this->remove_dir( $this->tmp );
		parent::tearDown();
	}

	public function test_detects_new_critical_regression(): void {
		$manifest         = $this->base_manifest();
		$baseline_scores  = $this->scores(
			array(
				'case_a' => $this->case_result( true, 0 ),
				'case_b' => $this->case_result( true, 0 ),
			)
		);
		$candidate_scores = $this->scores(
			array(
				'case_a' => $this->case_result( true, 0 ),
				'case_b' => $this->case_result( false, 1 ),
			)
		);

		$this->write_pack( $this->tmp . '/baseline', $manifest, $baseline_scores );
		$this->write_pack(
			$this->tmp . '/candidate',
			$manifest,
			$candidate_scores,
			array(
				array(
					'case_id'  => 'case_a',
					'category' => 'html_rich',
				),
				array(
					'case_id'  => 'case_b',
					'category' => 'protected',
				),
			)
		);

		$comparer   = new QualityComparer();
		$comparison = $comparer->compare(
			new EvidencePack( $this->tmp . '/baseline' ),
			new EvidencePack( $this->tmp . '/candidate' )
		);

		$this->assertContains( 'case_b', $comparison['regressed'] );
		$this->assertContains( 'case_b', $comparison['new_critical_regressions'] );
		$this->assertFalse( $comparer->passes_zero_new_critical_gate( $comparison ) );
	}

	public function test_incompatible_corpus_throws(): void {
		$manifest_a                   = $this->base_manifest();
		$manifest_b                   = $this->base_manifest();
		$manifest_b['corpus_version'] = 'C2.0';

		$scores = $this->scores( array( 'case_a' => $this->case_result( true, 0 ) ) );
		$this->write_pack( $this->tmp . '/baseline', $manifest_a, $scores );
		$this->write_pack( $this->tmp . '/candidate', $manifest_b, $scores );

		$this->expectException( \RuntimeException::class );
		( new QualityComparer() )->compare(
			new EvidencePack( $this->tmp . '/baseline' ),
			new EvidencePack( $this->tmp . '/candidate' )
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function base_manifest(): array {
		return array(
			'corpus_version'      => 'C1.0',
			'methodology_version' => 'M1.0',
			'scorer_version'      => DeterministicScorer::VERSION,
			'generation_label'    => 'test',
		);
	}

	/**
	 * @param array<string,array<string,mixed>> $case_results Case results.
	 * @return array<string,mixed>
	 */
	private function scores( array $case_results ): array {
		return array(
			'scorer_version' => DeterministicScorer::VERSION,
			'corpus_version' => 'C1.0',
			'case_results'   => $case_results,
			'summary'        => array(
				'total_cases'       => count( $case_results ),
				'pass_count'        => 0,
				'critical_failures' => 0,
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function case_result( bool $pass, int $critical ): array {
		return array(
			'findings'       => array(),
			'critical_count' => $critical,
			'error_count'    => 0,
			'warning_count'  => 0,
			'pass'           => $pass,
		);
	}

	/**
	 * @param array<string,mixed>       $manifest Manifest.
	 * @param array<string,mixed>       $scores   Scores.
	 * @param list<array<string,mixed>> $generations Optional generations.
	 */
	private function write_pack( string $dir, array $manifest, array $scores, array $generations = array() ): void {
		mkdir( $dir, 0755, true );
		file_put_contents( $dir . '/manifest.json', wp_json_encode( $manifest ) . "\n" );
		file_put_contents( $dir . '/scores.' . DeterministicScorer::VERSION . '.json', wp_json_encode( $scores ) . "\n" );
		if ( array() !== $generations ) {
			$lines = array_map(
				static fn( $row ) => wp_json_encode( $row ),
				$generations
			);
			file_put_contents( $dir . '/generations.jsonl', implode( "\n", $lines ) . "\n" );
		} else {
			file_put_contents( $dir . '/generations.jsonl', "\n" );
		}
	}

	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) ?: array() as $item ) {
			if ( in_array( $item, array( '.', '..' ), true ) ) {
				continue;
			}
			$path = $dir . '/' . $item;
			is_dir( $path ) ? $this->remove_dir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}
}
