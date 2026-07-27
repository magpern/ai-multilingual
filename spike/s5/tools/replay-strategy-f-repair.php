<?php
/**
 * Replay Strategy F UuidInjector repair on browser-exported post_content file.
 *
 * @package AIMultilingualSpike
 */
if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

$file = $args[0] ?? '';
if ( '' === $file || ! is_readable( $file ) ) {
	fwrite( STDERR, "Usage: wp eval-file replay-strategy-f-repair.php /path/to/content.html\n" );
	exit( 1 );
}

$content = (string) file_get_contents( $file );
function aiml_spike_strategy_root(): string {
	$candidates = array(
		'/aiml/spike/s5/lib/Strategy',
		'/opt/biopentra/dev/ai-multilingual/spike/s5/lib/Strategy',
	);
	foreach ( $candidates as $path ) {
		if ( is_dir( $path ) ) {
			return $path;
		}
	}
	throw new RuntimeException( 'Strategy spike lib not found' );
}

$spike = aiml_spike_strategy_root();
require_once $spike . '/StrategyFContract.php';
require_once $spike . '/UuidGenerator.php';
require_once $spike . '/StructuralPathWalker.php';
require_once $spike . '/UuidBlockWalker.php';
require_once $spike . '/StrategyEvaluator.php';
require_once $spike . '/UuidInjector.php';

use AIMultilingual\Spike\S5\Strategy\UuidBlockWalker;
use AIMultilingual\Spike\S5\Strategy\UuidInjector;

$before_dupes = array();
foreach ( UuidBlockWalker::count_uuids( $content ) as $uuid => $count ) {
	if ( $count > 1 ) {
		$before_dupes[ $uuid ] = $count;
	}
}

$result   = UuidInjector::inject( $content );
$after    = $result['content'];

echo wp_json_encode(
	array(
		'duplicate_uuids_before' => $before_dupes,
		'duplicate_uuids_after'  => $result['duplicate_uuids'],
		'stats'                  => $result['stats'],
		'content_changed'        => $result['stats']['content_changed'],
		'bytes_added'            => $result['stats']['bytes_added'],
	),
	JSON_PRETTY_PRINT
) . "\n";
