<?php
/**
 * Spike S5 — inject aimlBlockId UUIDs into a post's post_content via WP-CLI.
 *
 * Usage (from WordPress compose dir):
 *   wp eval-file /path/to/inject-aiml-block-ids.php <post_id>
 *
 * THROWAWAY. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
if ( $post_id <= 0 ) {
	fwrite( STDERR, "Usage: wp eval-file inject-aiml-block-ids.php <post_id>\n" );
	exit( 1 );
}

$post = get_post( $post_id );
if ( ! $post instanceof WP_Post ) {
	fwrite( STDERR, "Post not found: {$post_id}\n" );
	exit( 1 );
}

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

$spike_root = aiml_spike_strategy_root();
require_once $spike_root . '/StrategyFContract.php';
require_once $spike_root . '/UuidGenerator.php';
require_once $spike_root . '/StructuralPathWalker.php';
require_once $spike_root . '/UuidBlockWalker.php';
require_once $spike_root . '/StrategyEvaluator.php';
require_once $spike_root . '/UuidInjector.php';

use AIMultilingual\Spike\S5\Strategy\UuidInjector;

$before   = (string) $post->post_content;
$result   = UuidInjector::inject( $before );
$after    = $result['content'];
$changed  = $after !== $before;

if ( $changed ) {
	wp_update_post(
		array(
			'ID'           => $post_id,
			'post_content' => $after,
		),
		true
	);
}

echo wp_json_encode(
	array(
		'post_id'        => $post_id,
		'content_changed' => $changed,
		'stats'          => $result['stats'],
		'duplicate_uuids' => $result['duplicate_uuids'],
		'bytes_before'   => strlen( $before ),
		'bytes_after'    => strlen( $after ),
	),
	JSON_PRETTY_PRINT
) . "\n";
