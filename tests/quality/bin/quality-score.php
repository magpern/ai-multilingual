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
use AIMultilingual\Quality\QualityScorerH11;
use AIMultilingual\Quality\ReportWriter;

if ( ! isset( $argv[1] ) ) {
	fwrite( STDERR, "Usage: quality-score.php <pack-directory> [--scorer=H1.0|H1.1] [--write-report]\n" );
	exit( 2 );
}

$pack_dir     = $argv[1];
$write_report = in_array( '--write-report', $argv, true );
$scorer_arg   = 'H1.0';

foreach ( $argv as $arg ) {
	if ( is_string( $arg ) && str_starts_with( $arg, '--scorer=' ) ) {
		$scorer_arg = substr( $arg, strlen( '--scorer=' ) );
	}
}

if ( ! in_array( $scorer_arg, array( 'H1.0', 'H1.1' ), true ) ) {
	fwrite( STDERR, "ERROR: --scorer must be H1.0 or H1.1\n" );
	exit( 2 );
}

$pack = new EvidencePack( $pack_dir );
if ( 'H1.1' === $scorer_arg ) {
	$scores = ( new QualityScorerH11() )->score_pack( $pack );
} else {
	$scores = ( new QualityScorer() )->score_pack( $pack );
}

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
	"PASS\tquality score\tscorer=%s pass=%d/%d critical_failures=%d not_applicable=%d\n",
	(string) $scorer_version,
	(int) ( $summary['pass_count'] ?? 0 ),
	(int) ( $summary['total_cases'] ?? 0 ),
	(int) ( $summary['critical_failures'] ?? 0 ),
	(int) ( $summary['not_applicable_count'] ?? 0 )
);
exit( 0 );
