#!/usr/bin/env php
<?php
/**
 * TQ.0 official baseline replay + fingerprint verification (CI-safe, no writes).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

require dirname( __DIR__, 3 ) . '/vendor/autoload.php';

use AIMultilingual\Quality\EvidencePack;
use AIMultilingual\Quality\FrozenEvidenceGuard;
use AIMultilingual\Quality\QualityComparer;
use AIMultilingual\Quality\QualityScorer;

$root = dirname( __DIR__, 3 );
$pack_dir = $argv[1] ?? ( $root . '/tests/quality/baselines/baseline-v1.1.0' );

if ( ! is_dir( $pack_dir ) ) {
	fwrite( STDERR, "FAIL\tbaseline pack missing: {$pack_dir}\n" );
	exit( 1 );
}

$pack  = new EvidencePack( $pack_dir );
$guard = new FrozenEvidenceGuard();
$check = $guard->verify( $pack );
if ( ! $check['ok'] ) {
	fwrite( STDERR, "FAIL\tfingerprint mutations detected\n" );
	foreach ( $check['violations'] as $v ) {
		fwrite( STDERR, sprintf( "\t%s expected=%s actual=%s\n", $v['file'], $v['expected'], $v['actual'] ) );
	}
	exit( 1 );
}

$frozen = $pack->load_scores();
$scorer = new QualityScorer();
$replay = $scorer->score_pack( $pack );

$frozen_summary = (array) ( $frozen['summary'] ?? array() );
$replay_summary = (array) ( $replay['summary'] ?? array() );

if ( (int) ( $frozen_summary['critical_failures'] ?? -1 ) !== (int) ( $replay_summary['critical_failures'] ?? -2 )
	|| (int) ( $frozen_summary['pass_count'] ?? -1 ) !== (int) ( $replay_summary['pass_count'] ?? -2 )
	|| (int) ( $frozen_summary['total_cases'] ?? -1 ) !== (int) ( $replay_summary['total_cases'] ?? -2 ) ) {
	fwrite( STDERR, "FAIL\treplay summary mismatch vs frozen scores.H1.0.json\n" );
	exit( 1 );
}

// Self-compare must pass zero-new-critical gate.
$comparer   = new QualityComparer();
$comparison = $comparer->compare( $pack, $pack );
if ( ! $comparer->passes_zero_new_critical_gate( $comparison ) ) {
	fwrite( STDERR, "FAIL\tbaseline self-compare critical gate\n" );
	exit( 1 );
}

$human = $pack->load_human( 'B1.0' );
if ( null === $human ) {
	fwrite( STDERR, "FAIL\tmissing human.B1.0.json\n" );
	exit( 1 );
}
$reviews = (array) ( $human['reviews'] ?? array() );
$dual    = (array) ( $human['dual_review'] ?? array() );
if ( count( $reviews ) < 60 || count( $dual ) < 12 ) {
	fwrite( STDERR, sprintf( "FAIL\thuman review incomplete primary=%d dual=%d\n", count( $reviews ), count( $dual ) ) );
	exit( 1 );
}

echo sprintf(
	"PASS\tbaseline verify\tcases=%d critical=%d dual=%d fingerprints=ok\n",
	(int) ( $replay_summary['total_cases'] ?? 0 ),
	(int) ( $replay_summary['critical_failures'] ?? 0 ),
	count( $dual )
);
exit( 0 );
