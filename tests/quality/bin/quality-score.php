#!/usr/bin/env php
<?php
/**
 * TQ.0 deterministic scoring CLI.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

require dirname( __DIR__, 3 ) . '/vendor/autoload.php';

use AIMultilingual\Quality\EvidencePack;
use AIMultilingual\Quality\QualityScorer;
use AIMultilingual\Quality\ReportWriter;

if ( ! isset( $argv[1] ) ) {
	fwrite( STDERR, "Usage: quality-score.php <pack-directory> [--write-report]\n" );
	exit( 2 );
}

$pack_dir     = $argv[1];
$write_report = in_array( '--write-report', $argv, true );

$pack   = new EvidencePack( $pack_dir );
$scorer = new QualityScorer();
$scores = $scorer->score_pack( $pack );

$manifest       = $pack->load_manifest();
$scorer_version = $scores['scorer_version'];
$scores_path    = $pack->path() . '/scores.' . $scorer_version . '.json';

$encoded = json_encode( $scores, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
if ( false === $encoded ) {
	fwrite( STDERR, "ERROR: failed to encode scores\n" );
	exit( 1 );
}
file_put_contents( $scores_path, $encoded . "\n" );

if ( $write_report ) {
	$writer = new ReportWriter();
	$report = $writer->write_score_report( $scores, $manifest );
	file_put_contents( $pack->path() . '/REPORT.md', $report );
}

$summary = (array) ( $scores['summary'] ?? array() );
echo sprintf(
	"PASS\tquality score\tpass=%d/%d critical_failures=%d\n",
	(int) ( $summary['pass_count'] ?? 0 ),
	(int) ( $summary['total_cases'] ?? 0 ),
	(int) ( $summary['critical_failures'] ?? 0 )
);
exit( 0 );
