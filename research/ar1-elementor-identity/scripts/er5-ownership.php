<?php
/**
 * A.R1 EXPERIMENTAL — ER5 template/ownership analysis from live library posts.
 *
 * Usage: wp eval-file research/ar1-elementor-identity/scripts/er5-ownership.php
 *
 * @package AIMultilingual\Research\AR1
 */


require __DIR__ . '/lib-ar1.php';

$out_dir = dirname( __DIR__ ) . '/evidence';

$q = new WP_Query(
	array(
		'post_type'      => 'elementor_library',
		'post_status'    => array( 'publish', 'private', 'draft' ),
		'posts_per_page' => 50,
		'fields'         => 'ids',
	)
);

$templates = array();
foreach ( $q->posts as $pid ) {
	$pid = (int) $pid;
	$doc = ar1_load_document( $pid );
	$templates[] = array(
		'ID'            => $pid,
		'title'         => get_the_title( $pid ),
		'template_type' => $doc['template_type'],
		'edit_mode'     => $doc['edit_mode'],
		'raw_bytes'     => $doc['raw_bytes'],
		'element_count' => $doc['error'] ? 0 : count( ar1_walk_elements( $doc['elements'] ) ),
		'error'         => $doc['error'],
	);
}

// Scan pages for template/global widget references.
$page_q = new WP_Query(
	array(
		'post_type'      => 'page',
		'post_status'    => array( 'publish', 'private' ),
		'posts_per_page' => 40,
		'meta_key'       => '_elementor_edit_mode',
		'meta_value'     => 'builder',
		'fields'         => 'ids',
	)
);

$refs = array();
foreach ( $page_q->posts as $pid ) {
	$pid = (int) $pid;
	$doc = ar1_load_document( $pid );
	if ( $doc['error'] ) {
		continue;
	}
	foreach ( ar1_walk_elements( $doc['elements'] ) as $row ) {
		if ( $row['template_ref'] || in_array( $row['widgetType'], array( 'template', 'global' ), true ) ) {
			$refs[] = array(
				'consuming_post' => $pid,
				'element_id'     => $row['id'],
				'widgetType'     => $row['widgetType'],
				'template_ref'   => $row['template_ref'],
			);
		}
	}
}

// Ownership decision matrix (evidence-informed defaults).
$matrix = array(
	array(
		'content_kind'     => 'Ordinary page Elementor widgets',
		'ownership_scope'  => 'document-owned',
		'collision_rule'   => 'Identity must include owning post/document ID',
		'confidence'       => 'supported by evidence',
		'notes'            => 'ER1 page duplicate copies identical element IDs across posts.',
	),
	array(
		'content_kind'     => 'elementor_library saved template (definition)',
		'ownership_scope'  => 'shared-definition-owned',
		'collision_rule'   => 'Translate against library post ID when reliably referenced',
		'confidence'       => 'inferred',
		'notes'            => 'Requires stable reference semantics from consuming documents.',
	),
	array(
		'content_kind'     => 'Theme Builder header/footer/single (library)',
		'ownership_scope'  => 'shared-definition-owned',
		'collision_rule'   => 'One overlay set for definition; not per consuming URL',
		'confidence'       => 'supported by evidence',
		'notes'            => 'Library posts observed with template_type header/footer/etc.',
	),
	array(
		'content_kind'     => 'Template widget reference into page',
		'ownership_scope'  => 'shared-definition-owned when template_id present; else unsupported',
		'collision_rule'   => 'Do not bind translation to consuming page element ID alone',
		'confidence'       => 'supported by evidence',
		'notes'            => 'Refs observed: ' . count( $refs ),
	),
	array(
		'content_kind'     => 'Copied template content (paste / insert as copy)',
		'ownership_scope'  => 'consuming-document-owned (independent)',
		'collision_rule'   => 'New document owns copied IDs; do not share overlays with definition',
		'confidence'       => 'inferred',
		'notes'            => 'Elementor copy creates independent tree; silent sharing forbidden.',
	),
	array(
		'content_kind'     => 'Global widget without clear reference id',
		'ownership_scope'  => 'explicitly unsupported (ambiguous ownership)',
		'collision_rule'   => 'Leave source',
		'confidence'       => 'assumption requiring validation',
		'notes'            => 'Deny reason: ownership ambiguity',
	),
	array(
		'content_kind'     => 'Dynamic-tag driven values',
		'ownership_scope'  => 'explicitly unsupported',
		'collision_rule'   => 'Leave source',
		'confidence'       => 'supported by evidence',
		'notes'            => 'Deny reason: dynamic runtime value',
	),
);

file_put_contents(
	$out_dir . '/er5-ownership.json',
	wp_json_encode(
		array(
			'captured_at'      => gmdate( 'c' ),
			'library_templates'=> $templates,
			'template_refs_in_pages' => $refs,
			'ownership_decision_matrix' => $matrix,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	) . "\n"
);

WP_CLI::success( 'ER5 ownership evidence written.' );
echo wp_json_encode(
	array(
		'library_count' => count( $templates ),
		'page_refs'     => count( $refs ),
		'matrix_rows'   => count( $matrix ),
	),
	JSON_PRETTY_PRINT
) . "\n";
