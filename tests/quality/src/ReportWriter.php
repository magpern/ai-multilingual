<?php
/**
 * Markdown report writer for TQ.0 evidence packs.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Quality;

/**
 * Writes human-readable REPORT.md with regressions first.
 */
final class ReportWriter {

	/**
	 * Builds a comparison report markdown string.
	 *
	 * @param array<string,mixed> $comparison QualityComparer output.
	 */
	public function write_comparison_report( array $comparison ): string {
		$lines   = array();
		$lines[] = '# TQ.0 Quality Comparison Report';
		$lines[] = '';
		$lines[] = '## Summary';
		$lines[] = '';
		$lines[] = sprintf(
			'- Baseline: `%s`',
			(string) ( $comparison['baseline_label'] ?? 'unknown' )
		);
		$lines[] = sprintf(
			'- Candidate: `%s`',
			(string) ( $comparison['candidate_label'] ?? 'unknown' )
		);
		$lines[] = sprintf(
			'- Corpus: `%s` | Methodology: `%s` | Scorer: `%s`',
			(string) ( $comparison['corpus_version'] ?? '' ),
			(string) ( $comparison['methodology_version'] ?? '' ),
			(string) ( $comparison['scorer_version'] ?? '' )
		);
		$gate_ok = (bool) ( $comparison['gate']['zero_new_critical_regressions'] ?? false );
		$lines[] = sprintf(
			'- **Zero new critical regressions gate:** %s',
			$gate_ok ? 'PASS' : 'FAIL'
		);
		$lines[] = '';

		$regressed = (array) ( $comparison['regressed'] ?? array() );
		$new_crit  = (array) ( $comparison['new_critical_regressions'] ?? array() );

		$lines[] = '## Regressions (first)';
		$lines[] = '';
		if ( array() === $regressed ) {
			$lines[] = '_No regressions detected._';
		} else {
			foreach ( $regressed as $case_id ) {
				$flag    = in_array( $case_id, $new_crit, true ) ? ' **NEW CRITICAL**' : '';
				$lines[] = sprintf( '- `%s`%s', $case_id, $flag );
			}
		}
		$lines[] = '';

		$improved = (array) ( $comparison['improved'] ?? array() );
		$lines[]  = '## Improvements';
		$lines[]  = '';
		if ( array() === $improved ) {
			$lines[] = '_None._';
		} else {
			foreach ( $improved as $case_id ) {
				$lines[] = sprintf( '- `%s`', $case_id );
			}
		}
		$lines[] = '';

		$unchanged = (array) ( $comparison['unchanged'] ?? array() );
		$lines[]   = sprintf( '## Unchanged (%d cases)', count( $unchanged ) );
		$lines[]   = '';

		$rollups = (array) ( $comparison['category_rollups'] ?? array() );
		if ( array() !== $rollups ) {
			$lines[] = '## Category rollups';
			$lines[] = '';
			$lines[] = '| Category | Improved | Regressed | Unchanged |';
			$lines[] = '|---|---:|---:|---:|';
			foreach ( $rollups as $cat => $counts ) {
				$counts  = (array) $counts;
				$lines[] = sprintf(
					'| %s | %d | %d | %d |',
					$cat,
					(int) ( $counts['improved'] ?? 0 ),
					(int) ( $counts['regressed'] ?? 0 ),
					(int) ( $counts['unchanged'] ?? 0 )
				);
			}
			$lines[] = '';
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Builds a single-pack score summary report.
	 *
	 * @param array<string,mixed> $scores   Scores payload.
	 * @param array<string,mixed> $manifest Pack manifest.
	 */
	public function write_score_report( array $scores, array $manifest ): string {
		$summary = (array) ( $scores['summary'] ?? array() );
		$lines   = array();
		$lines[] = '# TQ.0 Deterministic Score Report';
		$lines[] = '';
		$lines[] = sprintf( '- Label: `%s`', (string) ( $manifest['generation_label'] ?? '' ) );
		$lines[] = sprintf( '- Scorer: `%s`', (string) ( $scores['scorer_version'] ?? DeterministicScorer::VERSION ) );
		$lines[] = sprintf( '- Cases: %d', (int) ( $summary['total_cases'] ?? 0 ) );
		$lines[] = sprintf( '- Pass (no critical): %d', (int) ( $summary['pass_count'] ?? 0 ) );
		$lines[] = sprintf( '- Critical failures: %d', (int) ( $summary['critical_failures'] ?? 0 ) );
		$lines[] = '';

		$case_results = (array) ( $scores['case_results'] ?? array() );
		$failures     = array();
		foreach ( $case_results as $case_id => $result ) {
			$result = (array) $result;
			if ( ! (bool) ( $result['pass'] ?? false ) ) {
				$failures[] = (string) $case_id;
			}
		}

		$lines[] = '## Failures (regressions first in compare context)';
		$lines[] = '';
		if ( array() === $failures ) {
			$lines[] = '_All cases passed deterministic gate._';
		} else {
			foreach ( $failures as $case_id ) {
				$result   = (array) ( $case_results[ $case_id ] ?? array() );
				$critical = (int) ( $result['critical_count'] ?? 0 );
				$lines[]  = sprintf( '- `%s` (critical=%d)', $case_id, $critical );
			}
		}
		$lines[] = '';

		return implode( "\n", $lines ) . "\n";
	}
}
