<?php
/**
 * Spike S5 — analyze aimlBlockId UUID state in Gutenberg post_content.
 *
 * @package AIMultilingualSpike
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
$baseline_arg = isset( $args[1] ) ? (string) $args[1] : '';

if ( $post_id <= 0 ) {
	fwrite( STDERR, "Usage: wp eval-file analyze-aiml-content.php <post_id> [baseline_post_id|baseline_content_file]\n" );
	exit( 1 );
}

// PHASE 3 FIX: a baseline arg that is a live post ID pointing at the SAME
// post being analyzed is a self-comparison bug (current DB row vs itself,
// fetched twice at the same instant) — it trivially reports "preserved" and
// "content_identical" regardless of what actually happened in the browser.
// Phase 3 tests must snapshot the pre-operation content to a FILE before any
// browser interaction, then pass that file path here so the comparison is
// against a genuine frozen "before" state. Numeric args are still accepted
// for comparisons against a genuinely distinct WordPress post (e.g. a
// pristine duplicate never touched by the editor).
$baseline_is_file = '' !== $baseline_arg && ! ctype_digit( $baseline_arg ) && file_exists( $baseline_arg );
$baseline_id       = ( '' !== $baseline_arg && ctype_digit( $baseline_arg ) ) ? (int) $baseline_arg : 0;

if ( $baseline_id === $post_id ) {
	fwrite( STDERR, "WARNING: baseline_post_id equals post_id — this is a self-comparison and will trivially report 'preserved'. Use a baseline content file instead.\n" );
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
require_once $spike_root . '/UuidBlockWalker.php';
require_once $spike_root . '/UuidInjector.php';
require_once $spike_root . '/UuidGenerator.php';
require_once $spike_root . '/StructuralPathWalker.php';
require_once $spike_root . '/StrategyEvaluator.php';

use AIMultilingual\Spike\S5\Strategy\StrategyFContract;
use AIMultilingual\Spike\S5\Strategy\UuidBlockWalker;

$content  = (string) $post->post_content;
$blocks   = UuidBlockWalker::walk_eligible( $content );
$counts   = UuidBlockWalker::count_uuids( $content );
$dupes    = array();
foreach ( $counts as $uuid => $count ) {
	if ( $count > 1 ) {
		$dupes[ $uuid ] = $count;
	}
}

$analysis = array(
	'post_id'           => $post_id,
	'post_status'       => $post->post_status,
	'post_modified_gmt' => $post->post_modified_gmt,
	'content_bytes'     => strlen( $content ),
	'content_sha1'      => sha1( $content ),
	'eligible_blocks'   => count( $blocks ),
	'blocks'            => array_map(
		static function ( array $b ): array {
			return array(
				'uuid'       => $b['uuid'],
				'valid_uuid' => StrategyFContract::is_valid_uuid( $b['uuid'] ),
				'block_name' => $b['block_name'],
				'text_sha1'  => sha1( $b['text'] ),
			);
		},
		$blocks
	),
	'uuid_counts'       => $counts,
	'duplicate_uuids'   => $dupes,
	'has_aimlBlockId'   => str_contains( $content, '"aimlBlockId"' ),
);

if ( $baseline_is_file || $baseline_id > 0 ) {
	$before = null;
	if ( $baseline_is_file ) {
		$before = (string) file_get_contents( $baseline_arg );
		$analysis['baseline_source'] = 'file:' . $baseline_arg;
	} else {
		$baseline = get_post( $baseline_id );
		if ( $baseline instanceof WP_Post ) {
			$before = (string) $baseline->post_content;
			$analysis['baseline_source'] = 'post:' . $baseline_id;
		}
	}
	if ( null !== $before ) {
		$before_blocks = UuidBlockWalker::walk_eligible( $before );
		$before_map = array();
		foreach ( $before_blocks as $i => $b ) {
			$before_map[ $i ] = array(
				'uuid'       => $b['uuid'],
				'block_name' => $b['block_name'],
				'text_sha1'  => sha1( $b['text'] ),
			);
		}
		$analysis['baseline_post_id']    = $baseline_is_file ? null : $baseline_id;
		$analysis['content_byte_diff']   = strlen( $content ) - strlen( $before );
		$analysis['content_identical']   = $content === $before;
		$analysis['baseline_blocks']     = $before_map;
		$analysis['uuid_preservation']   = array();
		foreach ( $blocks as $i => $b ) {
			$prev = $before_map[ $i ] ?? null;
			if ( null === $prev ) {
				$analysis['uuid_preservation'][ $i ] = 'new_block';
				continue;
			}
			if ( $prev['uuid'] === $b['uuid'] ) {
				$analysis['uuid_preservation'][ $i ] = 'preserved';
			} elseif ( '' === $b['uuid'] ) {
				$analysis['uuid_preservation'][ $i ] = 'removed';
			} else {
				$analysis['uuid_preservation'][ $i ] = 'mutated';
			}
		}
	}
}

echo wp_json_encode( $analysis, JSON_PRETTY_PRINT ) . "\n";
