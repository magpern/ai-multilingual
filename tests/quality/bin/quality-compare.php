#!/usr/bin/env php
<?php
/**
 * TQ.0 baseline vs candidate comparison CLI.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

require dirname( __DIR__, 3 ) . '/vendor/autoload.php';

use AIMultilingual\Quality\EvidencePack;
use AIMultilingual\Quality\QualityComparer;
use AIMultilingual\Quality\ReportWriter;

if ( ! isset( $argv[1], $argv[2] ) ) {
	fwrite( STDERR, "Usage: quality-compare.php <baseline-pack> <candidate-pack> [--write-report]\n" );
	exit( 2 );
}

$baseline_dir = $argv[1];
$candidate_dir = $argv[2];
$write_report  = in_array( '--write-report', $argv, true );

$baseline  = new EvidencePack( $baseline_dir );
$candidate = new EvidencePack( $candidate_dir );
$comparer  = new QualityComparer();

try {
	$comparison = $comparer->compare( $baseline, $candidate );
} catch ( \RuntimeException $e ) {
	fwrite( STDERR, 'ERROR: ' . $e->getMessage() . "\n" );
	exit( 1 );
}

$encoded = json_encode( $comparison, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
if ( false === $encoded ) {
	fwrite( STDERR, "ERROR: failed to encode comparison\n" );
	exit( 1 );
}
file_put_contents( $candidate->path() . '/comparison.json', $encoded . "\n" );

if ( $write_report ) {
	$writer = new ReportWriter();
	$report = $writer->write_comparison_report( $comparison );
	file_put_contents( $candidate->path() . '/REPORT.md', $report );
}

$gate_ok = $comparer->passes_zero_new_critical_gate( $comparison );
$new_crit = count( (array) ( $comparison['new_critical_regressions'] ?? array() ) );
$regressed = count( (array) ( $comparison['regressed'] ?? array() ) );

echo sprintf(
	"%s\tquality compare\tregressed=%d new_critical=%d\n",
	$gate_ok ? 'PASS' : 'FAIL',
	$regressed,
	$new_crit
);
exit( $gate_ok ? 0 : 1 );
