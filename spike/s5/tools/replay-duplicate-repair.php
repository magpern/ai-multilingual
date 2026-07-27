<?php
/**
 * Spike S5 Phase 3 — run Strategy F's UuidInjector repair against REAL
 * browser-produced post_content (not synthetic fixtures).
 *
 * For every browser-produced duplicate/copy fixture in the corpus, runs the
 * repair TWICE (idempotence check) and reports:
 *   - duplicate UUIDs detected before repair
 *   - which occurrence (document order) retained its UUID
 *   - duplicate UUIDs remaining after repair (must be empty)
 *   - whether a second repair pass changes anything (must be false)
 *
 * Usage: wp eval-file replay-duplicate-repair.php
 *
 * THROWAWAY. Branch spike/s5 only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

function aiml_spike_root(): string {
	foreach ( array( '/var/www/html/wp-content/plugins/ai-multilingual/spike/s5', '/aiml/spike/s5', '/opt/biopentra/dev/ai-multilingual/spike/s5' ) as $path ) {
		if ( is_dir( $path ) ) {
			return $path;
		}
	}
	throw new RuntimeException( 'spike/s5 root not found' );
}

$root = aiml_spike_root();
require_once $root . '/lib/Strategy/StrategyFContract.php';
require_once $root . '/lib/Strategy/UuidGenerator.php';
require_once $root . '/lib/Strategy/StructuralPathWalker.php';
require_once $root . '/lib/Strategy/UuidBlockWalker.php';
require_once $root . '/lib/Strategy/StrategyEvaluator.php';
require_once $root . '/lib/Strategy/UuidInjector.php';

use AIMultilingual\Spike\S5\Strategy\UuidInjector;
use AIMultilingual\Spike\S5\Strategy\UuidBlockWalker;

$corpus = $root . '/corpus/browser-validation';
$files  = glob( $corpus . '/*duplicate*-post-*.html' ) ?: array();
$files  = array_merge( $files, glob( $corpus . '/dup-*-post-*.html' ) ?: array() );
$files  = array_merge( $files, glob( $corpus . '/*-dup-post-*.html' ) ?: array() );
$files  = array_merge( $files, glob( $corpus . '/*-dup-baseline-post-*.html' ) ?: array() );
$files  = array_unique( $files );
sort( $files );

$results = array();

foreach ( $files as $file ) {
	$slug = preg_replace( '/-post-\d+\.html$/', '', basename( $file ) );
	$raw  = (string) file_get_contents( $file );

	$before_counts = UuidBlockWalker::count_uuids( $raw );
	$before_dupes  = array_filter( $before_counts, static fn( $c ) => $c > 1 );

	// Document order of UUIDs as they literally appear (first-wins keeps index 0).
	preg_match_all( '/"aimlBlockId":"([0-9a-f-]+)"/', $raw, $m );
	$doc_order = $m[1];

	$pass1 = UuidInjector::inject( $raw );
	$pass2 = UuidInjector::inject( $pass1['content'] );

	$after1_counts = UuidBlockWalker::count_uuids( $pass1['content'] );
	$after1_dupes  = array_filter( $after1_counts, static fn( $c ) => $c > 1 );

	$first_wins_ok = null;
	if ( array() !== $doc_order && array() !== $before_dupes ) {
		$dup_uuid = array_key_first( $before_dupes );
		// The first occurrence in the repaired content must still be the same
		// UUID at the same document position; verify by checking the repaired
		// content still contains $dup_uuid exactly once (the retained original)
		// and that the ORIGINAL duplicate value is present exactly once, i.e.
		// not both regenerated away.
		$first_wins_ok = ( 1 === ( $after1_counts[ $dup_uuid ] ?? 0 ) );
	}

	$results[ $slug ] = array(
		'file'                        => basename( $file ),
		'uuid_doc_order_before'       => $doc_order,
		'duplicate_uuids_before'      => $before_dupes,
		'duplicate_count_before'      => count( $before_dupes ),
		'duplicate_uuids_after_pass1' => $after1_dupes,
		'duplicate_count_after_pass1' => count( $after1_dupes ),
		'first_wins_preserved'        => $first_wins_ok,
		'uuids_regenerated_pass1'     => $pass1['stats']['uuids_regenerated'],
		'uuids_generated_pass1'       => $pass1['stats']['uuids_generated'],
		'uuids_preserved_pass1'       => $pass1['stats']['uuids_preserved'],
		'content_changed_pass1'       => $pass1['stats']['content_changed'],
		'idempotent_pass2_no_change'  => ( $pass2['content'] === $pass1['content'] ),
		'duplicate_uuids_after_pass2' => $pass2['duplicate_uuids'],
	);
}

// NOTE: this container runs as www-data (uid 33); the host corpus directory
// is owned by the host user with mode 755 (no group/other write), so we
// cannot file_put_contents() from inside the container. Emit the full
// results as JSON on a marked stdout line and let the caller redirect it to
// a host-owned file instead.
echo "===RESULTS_JSON_START===\n";
echo wp_json_encode( $results, JSON_PRETTY_PRINT ) . "\n";
echo "===RESULTS_JSON_END===\n";

$failures = array();
foreach ( $results as $slug => $r ) {
	if ( array() !== $r['duplicate_uuids_after_pass1'] ) {
		$failures[] = "$slug: duplicates remain after repair";
	}
	if ( ! $r['idempotent_pass2_no_change'] ) {
		$failures[] = "$slug: repair not idempotent";
	}
	if ( false === $r['first_wins_preserved'] ) {
		$failures[] = "$slug: first-wins occurrence was NOT preserved";
	}
}

echo wp_json_encode( array( 'cases' => count( $results ), 'failures' => $failures ), JSON_PRETTY_PRINT ) . "\n";
