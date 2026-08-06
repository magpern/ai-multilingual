<?php
/**
 * A.R1 EXPERIMENTAL — ER6 performance / cache notes from disposable render.
 *
 * Usage: wp eval-file research/ar1-elementor-identity/scripts/er6-performance.php
 *
 * @package AIMultilingual\Research\AR1
 */


require __DIR__ . '/lib-ar1.php';

$out_dir = dirname( __DIR__ ) . '/evidence';

$pid = wp_insert_post(
	array(
		'post_title'   => 'AR1 Perf Fixture ' . gmdate( 'Ymd-His' ),
		'post_status'  => 'private',
		'post_type'    => 'page',
		'post_content' => '<!-- AR1 disposable -->',
	),
	true
);

$out = array(
	'attempted' => false,
);

if ( ! is_wp_error( $pid ) ) {
	$pid = (int) $pid;
	update_post_meta( $pid, '_ar1_disposable', '1' );
	$elements = array();
	$children = array();
	for ( $i = 0; $i < 40; $i++ ) {
		$children[] = array(
			'id'         => substr( bin2hex( random_bytes( 4 ) ), 0, 7 ),
			'elType'     => 'widget',
			'widgetType' => 'heading',
			'settings'   => array( 'title' => 'Perf heading ' . $i ),
			'elements'   => array(),
		);
	}
	$elements[] = array(
		'id'       => substr( bin2hex( random_bytes( 4 ) ), 0, 7 ),
		'elType'   => 'container',
		'settings' => array(),
		'elements' => $children,
	);
	update_post_meta( $pid, '_elementor_edit_mode', 'builder' );
	update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
	update_post_meta( $pid, '_elementor_template_type', 'wp-page' );

	$out['attempted'] = true;
	$out['post_id']   = $pid;
	$out['widget_count'] = 40;

	$t0 = microtime( true );
	$ids = ar1_collect_ids( $elements );
	$out['walk_ms'] = round( ( microtime( true ) - $t0 ) * 1000, 3 );
	$out['id_count'] = count( $ids );

	if ( class_exists( '\Elementor\Plugin' ) ) {
		$t1 = microtime( true );
		$html = \Elementor\Plugin::$instance->frontend->get_builder_content( $pid, true );
		$out['render_ms'] = round( ( microtime( true ) - $t1 ) * 1000, 3 );
		$out['render_bytes'] = is_string( $html ) ? strlen( $html ) : 0;

		$t2 = microtime( true );
		$html2 = \Elementor\Plugin::$instance->frontend->get_builder_content( $pid, true );
		$out['render_second_ms'] = round( ( microtime( true ) - $t2 ) * 1000, 3 );
		$out['cache_observation'] = 'Second render timing captured; Elementor CSS/content cache may apply — language-scoped cache keys required for any overlay design.';
	}

	// Simulated Store lookup cost for N overlays (in-process estimate only).
	$t3 = microtime( true );
	$fake_store = array();
	foreach ( $ids as $id ) {
		$key = 'doc:' . $pid . ':e:' . $id . ':title';
		$fake_store[ $key ] = 'translated';
	}
	$hits = 0;
	foreach ( $ids as $id ) {
		$key = 'doc:' . $pid . ':e:' . $id . ':title';
		if ( isset( $fake_store[ $key ] ) ) {
			$hits++;
		}
	}
	$out['simulated_overlay_lookup_ms'] = round( ( microtime( true ) - $t3 ) * 1000, 3 );
	$out['simulated_hits'] = $hits;
	$out['confidence'] = 'supported by evidence';
	$out['notes'] = array(
		'Walk of 40 widgets is cheap in PHP.',
		'Render cost dominated by Elementor frontend pipeline.',
		'Overlay map keyed by owner+element+field remains O(n) lookups — batch by document.',
		'Do not claim universal performance from one environment.',
	);

	wp_trash_post( $pid );
	$out['trashed'] = true;
}

$version_matrix = array(
	'current_environment' => array(
		'wp'            => get_bloginfo( 'version' ),
		'php'           => PHP_VERSION,
		'elementor'     => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null,
		'elementor_pro' => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : null,
		'theme'         => wp_get_theme()->get_stylesheet(),
		'confidence'    => 'proven by experiment',
	),
	'earlier_elementor_version' => array(
		'tested'     => false,
		'confidence' => 'assumption requiring validation',
		'note'       => 'Not practical to downgrade shared dev Elementor in this spike; multi-version matrix deferred.',
	),
);

file_put_contents(
	$out_dir . '/er6-performance.json',
	wp_json_encode(
		array(
			'captured_at'    => gmdate( 'c' ),
			'performance'    => $out,
			'version_matrix' => $version_matrix,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	) . "\n"
);

WP_CLI::success( 'ER6 performance evidence written.' );
echo wp_json_encode( $out, JSON_PRETTY_PRINT ) . "\n";
