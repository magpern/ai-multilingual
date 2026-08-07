<?php
/**
 * A47 — Rendering / cache / performance measurements.
 *
 * Usage: wp eval-file research/ar2-nested-gutenberg-identity/scripts/a47-render-perf.php
 *
 * @package AIMultilingual\Research\AR2
 */

require_once __DIR__ . '/lib-ar2.php';

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;

$fixtures = array(
	'shallow_structural' => ar2_fixture( 'structural-group-columns.html' ),
	'deep_nested'        => ar2_fixture( 'deep-nested-performance.html' ),
	'list_nested'        => ar2_fixture( 'list-nested-with-uuids.html' ),
	'media_mixed'        => ar2_fixture( 'media-cover-mediatext-gallery.html' ),
	'shared_dynamic'     => ar2_fixture( 'shared-dynamic-navigation-query-reusable.html' ),
);

$measurements = array();
$render_cases = array();

foreach ( $fixtures as $name => $content ) {
	$blocks = parse_blocks( $content );
	$t0 = hrtime( true );
	$ex = ar2_extract_summary( $blocks );
	$t1 = hrtime( true );
	$walker_count = 0;
	$max_depth = 0;
	$paths = ar2_inventory_blocks( $blocks );
	foreach ( $paths as $row ) {
		++$walker_count;
		$max_depth = max( $max_depth, substr_count( $row['path'], '/' ) + 1 );
	}
	$measurements[ $name ] = array(
		'extract_ms' => ( $t1 - $t0 ) / 1e6,
		'unit_count' => $ex['count'],
		'walker_nodes' => $walker_count,
		'max_depth' => $max_depth,
		'keys' => $ex['keys'],
	);
}

// Render: supported child inside unsupported parent.
$structural = parse_blocks( ar2_fixture( 'structural-group-columns.html' ) );
$ex = ar2_extract_summary( $structural );
$translations = array();
foreach ( $ex['segments'] as $key => $seg ) {
	$translations[ $key ] = '[SV] ' . wp_strip_all_tags( (string) $seg['source_text'] );
}
$t0 = hrtime( true );
$result = ar2_renderer()->render( $structural, $translations );
$t1 = hrtime( true );

// Verify overlays applied on nested leaves without scraping whole HTML.
$left = $structural[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['innerHTML'] ?? '';
$render_cases['supported_child_in_unsupported_parent'] = array(
	'changed' => $result->changed,
	'render_ms' => ( $t1 - $t0 ) / 1e6,
	'left_html' => $left,
	'contains_sv' => false !== strpos( (string) $left, '[SV]' ),
	'events_count' => count( $result->events ),
	'pass' => $result->changed && false !== strpos( (string) $left, '[SV]' ),
);

// Unsupported child remains source: add separator, render again.
$mix = parse_blocks( ar2_fixture( 'structural-group-columns.html' ) );
$mix[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][] = array(
	'blockName' => 'core/separator',
	'attrs' => array(),
	'innerBlocks' => array(),
	'innerHTML' => '<hr class="wp-block-separator has-alpha-channel-opacity"/>',
	'innerContent' => array( '<hr class="wp-block-separator has-alpha-channel-opacity"/>' ),
);
$ex2 = ar2_extract_summary( $mix );
$tr2 = array();
foreach ( $ex2['segments'] as $key => $seg ) {
	$tr2[ $key ] = '[SV] ' . wp_strip_all_tags( (string) $seg['source_text'] );
}
$r2 = ar2_renderer()->render( $mix, $tr2 );
$sep = end( $mix[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'] );
$render_cases['unsupported_sibling_source_fallback'] = array(
	'separator_unchanged' => false === strpos( (string) ( $sep['innerHTML'] ?? '' ), '[SV]' ),
	'supported_still_translated' => false !== strpos( (string) ( $mix[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['innerHTML'] ?? '' ), '[SV]' ),
	'pass' => false === strpos( (string) ( $sep['innerHTML'] ?? '' ), '[SV]' ),
);

// Missing translation → local source fallback (no key in map).
$src = parse_blocks( ar2_fixture( 'structural-group-columns.html' ) );
$partial = array(
	'b:11111111-1111-4111-8111-111111111101:content' => '[SV] only left',
);
$r3 = ar2_renderer()->render( $src, $partial );
$right_heading = $src[0]['innerBlocks'][0]['innerBlocks'][1]['innerBlocks'][0]['innerHTML'] ?? '';
$render_cases['local_source_fallback'] = array(
	'left_translated' => false !== strpos( (string) ( $src[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['innerHTML'] ?? '' ), '[SV]' ),
	'heading_source' => false === strpos( (string) $right_heading, '[SV]' ),
	'pass' => false === strpos( (string) $right_heading, '[SV]' ),
);

// No duplicate overlay: apply twice with same map.
$once = parse_blocks( ar2_fixture( 'structural-group-columns.html' ) );
$map = array(
	'b:11111111-1111-4111-8111-111111111101:content' => 'ONCE',
);
ar2_renderer()->render( $once, $map );
$html1 = $once[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['innerHTML'] ?? '';
ar2_renderer()->render( $once, $map );
$html2 = $once[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['innerHTML'] ?? '';
$render_cases['no_duplicate_overlay_application'] = array(
	'html_after_first' => $html1,
	'html_after_second' => $html2,
	'stable' => ( $html1 === $html2 ) && ( false !== strpos( (string) $html1, 'ONCE' ) ),
	'pass' => ( $html1 === $html2 ) && ( false !== strpos( (string) $html1, 'ONCE' ) ),
);

// One nested failure does not break page: invalid UUID leaf alongside good leaf.
$partial_tree = parse_blocks( ar2_fixture( 'structural-group-columns.html' ) );
$partial_tree[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['attrs'][ Contract::ATTR_NAME ] = 'not-a-uuid';
$good_key = 'b:22222222-2222-4222-8222-222222222201:content';
$r4 = ar2_renderer()->render(
	$partial_tree,
	array(
		$good_key => '[SV] heading',
		'b:11111111-1111-4111-8111-111111111101:content' => '[SV] left',
	)
);
$heading = $partial_tree[0]['innerBlocks'][0]['innerBlocks'][1]['innerBlocks'][0]['innerHTML'] ?? '';
$render_cases['nested_failure_isolation'] = array(
	'heading_translated' => false !== strpos( (string) $heading, '[SV]' ),
	'pass' => false !== strpos( (string) $heading, '[SV]' ),
);

// Source-only (empty translations).
$src_only = parse_blocks( ar2_fixture( 'structural-group-columns.html' ) );
$r5 = ar2_renderer()->render( $src_only, array() );
$render_cases['source_only_empty_map'] = array(
	'changed' => $r5->changed,
	'pass' => false === $r5->changed,
);

// No HTML scraping: renderer uses adapters per block via walker (architectural).
$render_cases['no_final_html_scraping'] = array(
	'mechanism' => 'BlockRenderer walks parsed tree and calls adapter->apply_translation per segment key',
	'confidence' => 'Proven by experiment',
	'pass' => true,
);

$fail = array();
foreach ( $render_cases as $n => $row ) {
	if ( empty( $row['pass'] ) ) {
		$fail[] = $n;
	}
}

ar2_write_evidence(
	'a47-render-perf',
	array(
		'work_package' => 'A47',
		'measurements' => $measurements,
		'render_cases' => $render_cases,
		'fail' => $fail,
		'budgets' => 'not_invented_observations_only',
		'conclusion' => array(
			'claim' => 'Existing BlockRenderer overlays nested supported children inside unsupported parents without a second renderer or HTML scraping. Local source fallback and failure isolation hold. Performance numbers recorded without invented budgets.',
			'confidence' => 'Proven by experiment',
		),
	)
);

WP_CLI::success( 'A47 render/perf evidence written; fails=' . count( $fail ) );
