#!/usr/bin/env php
<?php
/**
 * TQ.0 Markdown report generator CLI.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

require dirname( __DIR__, 3 ) . '/vendor/autoload.php';

use AIMultilingual\Quality\EvidencePack;
use AIMultilingual\Quality\QualityComparer;
use AIMultilingual\Quality\QualityScorer;
use AIMultilingual\Quality\ReportWriter;

if ( ! isset( $argv[1] ) ) {
	fwrite( STDERR, "Usage: quality-report.php <pack-directory> [baseline-pack-directory]\n" );
	exit( 2 );
}

$pack_dir     = $argv[1];
$baseline_dir = $argv[2] ?? null;
$pack         = new EvidencePack( $pack_dir );
$writer       = new ReportWriter();

if ( null !== $baseline_dir ) {
	$baseline   = new EvidencePack( $baseline_dir );
	$comparer   = new QualityComparer();
	$comparison = $comparer->compare( $baseline, $pack );
	$report     = $writer->write_comparison_report( $comparison );
} else {
	$manifest = $pack->load_manifest();
	$scorer   = new QualityScorer();
	$scores   = $scorer->score_pack( $pack );
	$report   = $writer->write_score_report( $scores, $manifest );
}

file_put_contents( $pack->path() . '/REPORT.md', $report );
echo "PASS\tquality report\tpath=" . $pack->path() . "/REPORT.md\n";
exit( 0 );
