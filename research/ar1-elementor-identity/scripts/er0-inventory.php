<?php
/**
 * A.R1 EXPERIMENTAL — ER0 inventory + document inspection.
 *
 * Usage: wp eval-file research/ar1-elementor-identity/scripts/er0-inventory.php
 *
 * @package AIMultilingual\Research\AR1
 */


require __DIR__ . '/lib-ar1.php';

$out_dir = dirname( __DIR__ ) . '/evidence';
if ( ! is_dir( $out_dir ) ) {
	wp_mkdir_p( $out_dir );
}

$fixture_dir = dirname( __DIR__ ) . '/fixtures';
if ( ! is_dir( $fixture_dir ) ) {
	wp_mkdir_p( $fixture_dir );
}

$env = array(
	'captured_at'           => gmdate( 'c' ),
	'wp_version'            => get_bloginfo( 'version' ),
	'php_version'           => PHP_VERSION,
	'elementor_slug'        => 'elementor',
	'elementor_version'     => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null,
	'elementor_pro_slug'    => 'elementor-pro',
	'elementor_pro_version' => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : null,
	'theme'                 => wp_get_theme()->get_stylesheet(),
	'theme_version'         => wp_get_theme()->get( 'Version' ),
	'parent_theme'          => wp_get_theme()->get_template(),
	'feature_flags'         => array(
		'elementor_experiment-container'          => get_option( 'elementor_experiment-container' ),
		'elementor_experiment-nested-elements'    => get_option( 'elementor_experiment-nested-elements' ),
		'elementor_experiment-e_optimized_markup' => get_option( 'elementor_experiment-e_optimized_markup' ),
		'elementor_cpt_support'                   => get_option( 'elementor_cpt_support' ),
	),
);

file_put_contents( $out_dir . '/er0-environment.json', wp_json_encode( $env, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );

$q = new WP_Query(
	array(
		'post_type'      => array( 'page', 'elementor_library' ),
		'post_status'    => array( 'publish', 'draft', 'private' ),
		'posts_per_page' => 80,
		'meta_key'       => '_elementor_edit_mode',
		'meta_value'     => 'builder',
		'orderby'        => 'ID',
		'order'          => 'DESC',
		'fields'         => 'ids',
	)
);

$inventory = array();
$widget_freq = array();
$id_collisions_global = array(); // id => list of post_ids (cross-doc)

foreach ( $q->posts as $post_id ) {
	$post_id = (int) $post_id;
	$doc     = ar1_load_document( $post_id );
	$rows    = $doc['error'] ? array() : ar1_walk_elements( $doc['elements'] );
	$ids     = array();
	$widgets = array();
	foreach ( $rows as $row ) {
		if ( $row['id'] !== '' ) {
			$ids[] = $row['id'];
			if ( ! isset( $id_collisions_global[ $row['id'] ] ) ) {
				$id_collisions_global[ $row['id'] ] = array();
			}
			$id_collisions_global[ $row['id'] ][] = $post_id;
		}
		if ( $row['widgetType'] !== '' ) {
			$widgets[ $row['widgetType'] ] = ( $widgets[ $row['widgetType'] ] ?? 0 ) + 1;
			$widget_freq[ $row['widgetType'] ] = ( $widget_freq[ $row['widgetType'] ] ?? 0 ) + 1;
		}
	}
	$id_unique = array_unique( $ids );
	$inventory[] = array(
		'ID'              => $post_id,
		'title'           => get_the_title( $post_id ),
		'post_type'       => get_post_type( $post_id ),
		'status'          => get_post_status( $post_id ),
		'template_type'   => $doc['template_type'],
		'raw_bytes'       => $doc['raw_bytes'],
		'element_count'   => count( $rows ),
		'id_count'        => count( $ids ),
		'id_unique'       => count( $id_unique ),
		'intra_doc_dupes' => count( $ids ) - count( $id_unique ),
		'widget_types'    => $widgets,
		'error'           => $doc['error'],
	);
}

arsort( $widget_freq );
$cross = array();
foreach ( $id_collisions_global as $eid => $posts ) {
	$posts = array_values( array_unique( $posts ) );
	if ( count( $posts ) > 1 ) {
		$cross[] = array(
			'element_id' => $eid,
			'post_ids'   => $posts,
			'count'      => count( $posts ),
		);
	}
}

file_put_contents( $out_dir . '/er0-inventory.json', wp_json_encode( $inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
file_put_contents( $out_dir . '/er0-widget-frequency.json', wp_json_encode( $widget_freq, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
file_put_contents( $out_dir . '/er0-cross-document-id-collisions.json', wp_json_encode( $cross, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );

// Sanitized fixture exports for a small representative set (dev research pages preferred).
$export_ids = array();
foreach ( $inventory as $row ) {
	if ( $row['error'] || $row['element_count'] < 3 ) {
		continue;
	}
	// Prefer library templates + a few content pages; skip huge rollback drafts by title.
	if ( false !== stripos( (string) $row['title'], 'rollback' ) ) {
		continue;
	}
	$export_ids[] = (int) $row['ID'];
	if ( count( $export_ids ) >= 8 ) {
		break;
	}
}

$exports = array();
foreach ( $export_ids as $pid ) {
	$doc  = ar1_load_document( $pid );
	$tree = ar1_sanitize_tree( $doc['elements'] );
	$walk = ar1_walk_elements( $tree );
	$file = sprintf( 'fixture-post-%d-sanitized.json', $pid );
	file_put_contents(
		$fixture_dir . '/' . $file,
		wp_json_encode(
			array(
				'provenance' => array(
					'source_post_id'   => $pid,
					'source_post_type' => get_post_type( $pid ),
					'template_type'    => $doc['template_type'],
					'elementor'        => $env['elementor_version'],
					'elementor_pro'    => $env['elementor_pro_version'],
					'exported_at'      => gmdate( 'c' ),
					'sanitized'        => true,
					'note'             => 'A.R1 research fixture — emails/tokens redacted; not for production.',
				),
				'elements'   => $tree,
				'walk_summary' => array(
					'nodes'         => count( $walk ),
					'widget_types'  => array_values( array_unique( array_filter( array_column( $walk, 'widgetType' ) ) ) ),
					'dynamic_nodes' => count( array_filter( $walk, static fn( $r ) => ! empty( $r['has_dynamic'] ) ) ),
					'repeater_nodes'=> count( array_filter( $walk, static fn( $r ) => $r['repeater_keys'] !== array() ) ),
				),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		) . "\n"
	);
	$exports[] = $file;
}

$summary = array(
	'environment_file'           => 'er0-environment.json',
	'inventory_count'            => count( $inventory ),
	'cross_document_id_collisions' => count( $cross ),
	'top_widgets'                => array_slice( $widget_freq, 0, 25, true ),
	'exported_fixtures'          => $exports,
);
file_put_contents( $out_dir . '/er0-summary.json', wp_json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );

WP_CLI::success( 'ER0 inventory written to research/ar1-elementor-identity/evidence/' );
echo wp_json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
