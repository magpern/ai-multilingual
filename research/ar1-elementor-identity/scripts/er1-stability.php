<?php
/**
 * A.R1 EXPERIMENTAL — ER1 identity stability + copy/duplicate matrix.
 *
 * Creates a disposable private Elementor page, runs mutations, records ID behaviour,
 * then trashes the disposable posts. Does not mutate real site marketing content.
 *
 * Usage: wp eval-file research/ar1-elementor-identity/scripts/er1-stability.php
 *
 * @package AIMultilingual\Research\AR1
 */


require __DIR__ . '/lib-ar1.php';

$out_dir = dirname( __DIR__ ) . '/evidence';
$results = array(
	'started_at' => gmdate( 'c' ),
	'experiments' => array(),
);

/**
 * Minimal Elementor document with heading + text + nested container + tabs-like repeater.
 *
 * @return array<int,array<string,mixed>>
 */
function ar1_build_minimal_document(): array {
	$heading_id = substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	$text_id    = substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	$btn_id     = substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	$inner_id   = substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	$cont_id    = substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	$acc_id     = substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	$row1       = substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	$row2       = substr( bin2hex( random_bytes( 4 ) ), 0, 7 );

	return array(
		array(
			'id'       => $cont_id,
			'elType'   => 'container',
			'isInner'  => false,
			'settings' => array( 'flex_direction' => 'column' ),
			'elements' => array(
				array(
					'id'         => $heading_id,
					'elType'     => 'widget',
					'widgetType' => 'heading',
					'settings'   => array(
						'title'       => 'AR1 Research Heading',
						'title_mobile'=> 'AR1 Mobile Heading',
						'header_size' => 'h2',
					),
					'elements'   => array(),
				),
				array(
					'id'         => $text_id,
					'elType'     => 'widget',
					'widgetType' => 'text-editor',
					'settings'   => array(
						'editor' => '<p>AR1 research paragraph one.</p>',
					),
					'elements'   => array(),
				),
				array(
					'id'         => $btn_id,
					'elType'     => 'widget',
					'widgetType' => 'button',
					'settings'   => array(
						'text' => 'AR1 Button',
						'link' => array( 'url' => 'https://example.com/ar1', 'is_external' => '' ),
					),
					'elements'   => array(),
				),
				array(
					'id'       => $inner_id,
					'elType'   => 'container',
					'isInner'  => true,
					'settings' => array(),
					'elements' => array(
						array(
							'id'         => $acc_id,
							'elType'     => 'widget',
							'widgetType' => 'accordion',
							'settings'   => array(
								'tabs' => array(
									array(
										'_id'         => $row1,
										'tab_title'   => 'Item A',
										'tab_content' => '<p>Content A</p>',
									),
									array(
										'_id'         => $row2,
										'tab_title'   => 'Item B',
										'tab_content' => '<p>Content B</p>',
									),
								),
							),
							'elements'   => array(),
						),
					),
				),
			),
		),
	);
}

/**
 * Persist Elementor document meta.
 *
 * @param int                                $post_id  Post.
 * @param array<int,array<string,mixed>>     $elements Tree.
 */
function ar1_save_document( int $post_id, array $elements ): void {
	update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
	update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '0' );
	update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
	delete_post_meta( $post_id, '_elementor_css' );
}

/**
 * @return int Disposable post ID.
 */
function ar1_create_disposable_page( string $title ): int {
	$id = wp_insert_post(
		array(
			'post_title'  => $title,
			'post_status' => 'private',
			'post_type'   => 'page',
			'post_content'=> '<!-- AR1 disposable research fixture — safe to delete -->',
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	update_post_meta( (int) $id, '_ar1_disposable', '1' );
	return (int) $id;
}

$source_id = ar1_create_disposable_page( 'AR1 Disposable Fixture ' . gmdate( 'Ymd-His' ) );
$doc_tree  = ar1_build_minimal_document();
ar1_save_document( $source_id, $doc_tree );
$before_ids = ar1_collect_ids( $doc_tree );
$before_map = ar1_id_path_map( $doc_tree );
$repeater_ids_before = array();
foreach ( ar1_walk_elements( $doc_tree ) as $row ) {
	if ( $row['widgetType'] === 'accordion' ) {
		$settings = null;
		// re-find settings from tree for repeater _id values
	}
}
// Capture repeater _ids from tree.
$walk_raw = json_decode( (string) get_post_meta( $source_id, '_elementor_data', true ), true );
if ( ! is_array( $walk_raw ) ) {
	$walk_raw = json_decode( wp_unslash( (string) get_post_meta( $source_id, '_elementor_data', true ) ), true );
}
$doc_loaded = is_array( $walk_raw ) ? $walk_raw : $doc_tree;
foreach ( ar1_walk_elements( $doc_loaded ) as $row ) {
	// noop — need settings; walk from loaded
}
function ar1_extract_repeater_item_ids( array $elements ): array {
	$ids = array();
	foreach ( $elements as $el ) {
		if ( ! is_array( $el ) ) {
			continue;
		}
		$settings = isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : array();
		foreach ( $settings as $k => $v ) {
			if ( is_array( $v ) && ar1_looks_like_repeater( $v ) ) {
				foreach ( $v as $item ) {
					if ( isset( $item['_id'] ) ) {
						$ids[] = array( 'control' => (string) $k, '_id' => (string) $item['_id'] );
					}
				}
			}
		}
		if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
			$ids = array_merge( $ids, ar1_extract_repeater_item_ids( $el['elements'] ) );
		}
	}
	return $ids;
}
$repeater_before = ar1_extract_repeater_item_ids( $doc_loaded );

$results['source_post_id'] = $source_id;
$results['baseline_ids']   = $before_ids;
$results['baseline_paths'] = $before_map;
$results['baseline_repeater_ids'] = $repeater_before;

// --- Experiment: text edit (no structural change) ---
$edit_tree = $doc_loaded;
$edit_tree = ar1_mutate_heading_title( $edit_tree, 'AR1 Research Heading EDITED' );
ar1_save_document( $source_id, $edit_tree );
$after_edit = ar1_load_document( $source_id );
$after_ids  = ar1_collect_ids( $after_edit['elements'] );
$results['experiments']['edit_text_only'] = array(
	'compare' => ar1_compare_ids( $before_ids, $after_ids ),
	'paths_unchanged' => ar1_id_path_map( $after_edit['elements'] ) === $before_map,
	'repeater_ids' => ar1_extract_repeater_item_ids( $after_edit['elements'] ),
);

/**
 * @param array<int,array<string,mixed>> $elements Tree.
 * @return array<int,array<string,mixed>>
 */
function ar1_mutate_heading_title( array $elements, string $title ): array {
	foreach ( $elements as &$el ) {
		if ( isset( $el['widgetType'] ) && $el['widgetType'] === 'heading' ) {
			$el['settings']['title'] = $title;
		}
		if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
			$el['elements'] = ar1_mutate_heading_title( $el['elements'], $title );
		}
	}
	unset( $el );
	return $elements;
}

/**
 * Reorder first-level children of root container.
 *
 * @param array<int,array<string,mixed>> $elements Tree.
 * @return array<int,array<string,mixed>>
 */
function ar1_reorder_root_children( array $elements ): array {
	if ( ! isset( $elements[0]['elements'] ) || ! is_array( $elements[0]['elements'] ) ) {
		return $elements;
	}
	$children = $elements[0]['elements'];
	if ( count( $children ) >= 2 ) {
		$first = array_shift( $children );
		$children[] = $first;
		$elements[0]['elements'] = array_values( $children );
	}
	return $elements;
}

// --- Experiment: reorder ---
$reordered = ar1_reorder_root_children( $after_edit['elements'] );
ar1_save_document( $source_id, $reordered );
$after_reorder = ar1_load_document( $source_id );
$results['experiments']['reorder_siblings'] = array(
	'compare' => ar1_compare_ids( $before_ids, ar1_collect_ids( $after_reorder['elements'] ) ),
	'paths_before' => $before_map,
	'paths_after'  => ar1_id_path_map( $after_reorder['elements'] ),
	'note' => 'IDs retained but structural paths change — Candidate C risk.',
);

// --- Experiment: duplicate widget (clone heading node with NEW id) ---
function ar1_duplicate_first_heading( array $elements ): array {
	foreach ( $elements as &$el ) {
		if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
			$new_children = array();
			foreach ( $el['elements'] as $child ) {
				$new_children[] = $child;
				if ( isset( $child['widgetType'] ) && $child['widgetType'] === 'heading' ) {
					$clone = $child;
					$clone['id'] = substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
					$clone['settings']['title'] = ( $clone['settings']['title'] ?? '' ) . ' (copy)';
					$new_children[] = $clone;
				}
			}
			$el['elements'] = $new_children;
		}
	}
	unset( $el );
	return $elements;
}
$dup_tree = ar1_duplicate_first_heading( $after_reorder['elements'] );
ar1_save_document( $source_id, $dup_tree );
$after_dup = ar1_load_document( $source_id );
$results['experiments']['duplicate_widget_new_id'] = array(
	'compare' => ar1_compare_ids( $before_ids, ar1_collect_ids( $after_dup['elements'] ) ),
	'note' => 'Manual duplicate with new ID (Elementor editor behaviour simulated).',
);

// --- Experiment: page duplicate (wp_insert + copy meta) ---
$dup_page = ar1_create_disposable_page( 'AR1 Disposable DUPLICATE of ' . $source_id );
$src_meta_data = get_post_meta( $source_id, '_elementor_data', true );
update_post_meta( $dup_page, '_elementor_edit_mode', 'builder' );
update_post_meta( $dup_page, '_elementor_data', $src_meta_data );
update_post_meta( $dup_page, '_elementor_template_type', 'wp-page' );
update_post_meta( $dup_page, '_ar1_disposable', '1' );
$dup_doc = ar1_load_document( $dup_page );
$results['experiments']['duplicate_page_copy_meta'] = array(
	'duplicate_post_id' => $dup_page,
	'compare_to_source_current' => ar1_compare_ids(
		ar1_collect_ids( $after_dup['elements'] ),
		ar1_collect_ids( $dup_doc['elements'] )
	),
	'note' => 'Naive page duplicate copies identical element IDs into another post — cross-document collision if identity lacks owner scope.',
);

// --- Experiment: Elementor-native document save via Plugin API if available ---
$elementor_regen = array( 'attempted' => false );
if ( class_exists( '\Elementor\Plugin' ) ) {
	$elementor_regen['attempted'] = true;
	try {
		$document = \Elementor\Plugin::$instance->documents->get( $source_id );
		if ( $document ) {
			// Re-save same data through Elementor document API.
			$document->save( array( 'elements' => $after_dup['elements'] ) );
			$after_api = ar1_load_document( $source_id );
			$elementor_regen['compare'] = ar1_compare_ids(
				ar1_collect_ids( $after_dup['elements'] ),
				ar1_collect_ids( $after_api['elements'] )
			);
			$elementor_regen['result'] = 'saved_via_document_api';
		} else {
			$elementor_regen['result'] = 'document_null';
		}
	} catch ( Throwable $e ) {
		$elementor_regen['result'] = 'exception';
		$elementor_regen['error']  = $e->getMessage();
	}
}
$results['experiments']['elementor_document_api_resave'] = $elementor_regen;

// --- Experiment: simulate import of sanitized fixture into new post (IDs preserved as-imported) ---
$import_page = ar1_create_disposable_page( 'AR1 Disposable IMPORT target' );
$fixture_files = glob( dirname( __DIR__ ) . '/fixtures/fixture-post-*-sanitized.json' ) ?: array();
$import_result = array( 'attempted' => false );
if ( $fixture_files ) {
	$payload = json_decode( (string) file_get_contents( $fixture_files[0] ), true );
	if ( is_array( $payload ) && isset( $payload['elements'] ) && is_array( $payload['elements'] ) ) {
		ar1_save_document( $import_page, $payload['elements'] );
		$imp = ar1_load_document( $import_page );
		$src_ids = ar1_collect_ids( $payload['elements'] );
		$imp_ids = ar1_collect_ids( $imp['elements'] );
		$import_result = array(
			'attempted' => true,
			'source_fixture' => basename( $fixture_files[0] ),
			'import_post_id' => $import_page,
			'compare' => ar1_compare_ids( $src_ids, $imp_ids ),
			'note' => 'Import preserving IDs as in payload — if another document already uses those IDs, owner-scope is mandatory.',
		);
	}
}
$results['experiments']['import_sanitized_fixture'] = $import_result;

// Cleanup disposable posts (trash).
$cleanup = array( $source_id, $dup_page, $import_page );
foreach ( $cleanup as $pid ) {
	if ( $pid ) {
		wp_trash_post( $pid );
	}
}
$results['cleanup_trashed'] = $cleanup;
$results['finished_at'] = gmdate( 'c' );

file_put_contents( $out_dir . '/er1-stability.json', wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
WP_CLI::success( 'ER1 stability evidence written.' );
echo wp_json_encode(
	array(
		'source' => $source_id,
		'experiments' => array_keys( $results['experiments'] ),
		'edit_all_retained' => $results['experiments']['edit_text_only']['compare']['all_retained'] ?? null,
		'page_dup_identical_ids' => ( $results['experiments']['duplicate_page_copy_meta']['compare_to_source_current']['lost'] ?? null ) === array()
			&& ( $results['experiments']['duplicate_page_copy_meta']['compare_to_source_current']['new'] ?? null ) === array(),
	),
	JSON_PRETTY_PRINT
) . "\n";
