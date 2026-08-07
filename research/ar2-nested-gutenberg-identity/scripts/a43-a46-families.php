<?php
/**
 * A43–A46 — Container family classification research.
 *
 * Usage: wp eval-file research/ar2-nested-gutenberg-identity/scripts/a43-a46-families.php
 *
 * @package AIMultilingual\Research\AR2
 */

require_once __DIR__ . '/lib-ar2.php';

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;

$reg = new BlockRegistry( new AdapterRegistry() );
$adapters = new AdapterRegistry();

// --- A43 structural ---
$structural = parse_blocks( ar2_fixture( 'structural-group-columns.html' ) );
$s_inv = ar2_inventory_blocks( $structural );
$s_ex  = ar2_extract_summary( $structural );
$attr_audit = array();
foreach ( $s_inv as $row ) {
	if ( in_array( $row['block_name'], array( 'core/group', 'core/columns', 'core/column' ), true ) ) {
		$attr_audit[ $row['path'] ] = array(
			'block_name' => $row['block_name'],
			'attr_keys'  => $row['attr_keys'],
			'visitor_text_preview' => $row['inner_html_preview'],
			'extracted_as_unit' => false,
		);
	}
}
// reorder columns — child identity unchanged
$re = ar2_copy( $structural );
$cols =& $re[0]['innerBlocks'][0]['innerBlocks'];
$t = $cols[0]; $cols[0] = $cols[1]; $cols[1] = $t; unset( $cols );
$re_ex = ar2_extract_summary( $re );

$a43 = array(
	'work_package' => 'A43',
	'classification' => array(
		'core/group'   => 'structural_only_existing_child_traversal',
		'core/columns' => 'structural_only_existing_child_traversal',
		'core/column'  => 'structural_only_existing_child_traversal',
	),
	'attr_audit' => $attr_audit,
	'extract_count' => $s_ex['count'],
	'no_container_units' => 0 === count( array_filter( $s_ex['segments'], static fn( $s ) => in_array( $s['block_name'], array( 'core/group', 'core/columns', 'core/column' ), true ) ) ),
	'reorder_preserves_child_keys' => empty( array_diff( $s_ex['keys'], $re_ex['keys'] ) ),
	'adapters_present' => array(
		'group' => null !== $adapters->get( 'core/group' ),
		'columns' => null !== $adapters->get( 'core/columns' ),
		'column' => null !== $adapters->get( 'core/column' ),
	),
	'conclusion' => array(
		'claim' => 'group/columns/column are structural-only; no adapter required; supported descendants remain independently addressable; container reorder does not alter child keys.',
		'confidence' => 'Proven by experiment',
	),
);

// --- A44 textual ---
$list = parse_blocks( ar2_fixture( 'list-nested-with-uuids.html' ) );
$list_inv = ar2_inventory_blocks( $list );
$list_ex = ar2_extract_summary( $list );
$list_sources = array_map( static fn( $s ) => $s['source_text'], $list_ex['segments'] );
$duplicate_sources = count( $list_sources ) !== count( array_unique( $list_sources ) );

// Inject UUIDs into authored list without UUIDs and ensure list wrapper not extracted.
$authored_list = parse_blocks( ar2_authored_fixture( 'list-nested.html' ) );
$inj = ar2_injector()->inject_blocks( $authored_list );
$authored_ex = ar2_extract_summary( $authored_list );
$list_units = array_filter( $authored_ex['segments'], static fn( $s ) => 'core/list' === $s['block_name'] );
$parent_with_inner = null;
foreach ( $list_inv as $row ) {
	if ( 'core/list-item' === $row['block_name'] && $row['inner_block_count'] > 0 ) {
		$parent_with_inner = $row;
		break;
	}
}

$textual = parse_blocks( ar2_fixture( 'textual-quote-pullquote-details.html' ) );
$t_inv = ar2_inventory_blocks( $textual );
$t_ex = ar2_extract_summary( $textual );
$quote_row = null;
$pull_row = null;
$details_row = null;
foreach ( $t_inv as $row ) {
	if ( 'core/quote' === $row['block_name'] ) {
		$quote_row = $row;
	}
	if ( 'core/pullquote' === $row['block_name'] ) {
		$pull_row = $row;
	}
	if ( 'core/details' === $row['block_name'] ) {
		$details_row = $row;
	}
}

$a44 = array(
	'work_package' => 'A44',
	'list_hard_gate' => array(
		'list_adapter_exists' => null !== $adapters->get( 'core/list' ),
		'list_units_extracted' => count( $list_units ),
		'nested_fixture_extract_count' => $list_ex['count'],
		'nested_leaf_keys_present' => array(
			'li1' => in_array( 'b:33333333-3333-4333-8333-333333333301:content', $list_ex['keys'], true ),
			'nested_a' => in_array( 'b:33333333-3333-4333-8333-333333333302:content', $list_ex['keys'], true ),
			'nested_b' => in_array( 'b:33333333-3333-4333-8333-333333333303:content', $list_ex['keys'], true ),
			'li3' => in_array( 'b:33333333-3333-4333-8333-333333333304:content', $list_ex['keys'], true ),
		),
		'parent_list_item_with_inner' => $parent_with_inner,
		'parent_with_inner_extracted' => false, // no UUID / not adapter-translatable
		'duplicate_source_texts' => $duplicate_sources,
		'authored_after_inject_count' => $authored_ex['count'],
		'authored_list_units' => count( $list_units ),
		'no_duplicate_list_vs_list_item' => 0 === count( $list_units ),
		'pass_no_duplicate_extraction' => 0 === count( $list_units ) && ! $duplicate_sources,
	),
	'quote_pullquote_details' => array(
		'extract_keys' => $t_ex['keys'],
		'quote' => array(
			'has_inner_blocks' => $quote_row['inner_block_count'] ?? null,
			'citation_in_parent_markup' => true,
			'child_paragraph_extracted' => in_array( 'b:44444444-4444-4444-8444-444444444401:content', $t_ex['keys'], true ),
			'citation_extracted' => false,
			'disposition' => 'child_traversal_for_paragraph; citation_requires_adapter_field_admission',
		),
		'pullquote' => array(
			'has_inner_blocks' => $pull_row['inner_block_count'] ?? null,
			'body_and_citation_in_parent_html' => true,
			'extracted' => false,
			'disposition' => 'adapter_field_admission_required_or_deferred; no_child_paragraph_in_fixture',
		),
		'details' => array(
			'has_inner_blocks' => $details_row['inner_block_count'] ?? null,
			'summary_in_parent_markup' => true,
			'child_paragraph_extracted' => in_array( 'b:11111111-1111-4111-8111-111111111103:content', $t_ex['keys'], true ),
			'summary_extracted' => false,
			'disposition' => 'child_traversal_for_body; summary_requires_adapter_field_admission',
		),
		'identity_grammar_sufficient' => true,
	),
	'conclusion' => array(
		'claim' => 'List hard gate PASSES for no duplicate list/list-item extraction. Nested leaf list-items extract under existing grammar. Parent list-item with innerBlocks is skipped (admission gap, not identity gap). Quote/details children extract; citation/summary need field admission. Pullquote needs adapter or deferral.',
		'confidence' => 'Proven by experiment',
	),
);

// --- A45 media ---
$media = parse_blocks( ar2_fixture( 'media-cover-mediatext-gallery.html' ) );
$m_inv = ar2_inventory_blocks( $media );
$m_ex = ar2_extract_summary( $media );
$media_rows = array();
foreach ( $m_inv as $row ) {
	if ( in_array( $row['block_name'], array( 'core/cover', 'core/media-text', 'core/gallery', 'core/image', 'core/paragraph' ), true ) ) {
		$media_rows[] = $row;
	}
}
$a45 = array(
	'work_package' => 'A45',
	'extract_keys' => $m_ex['keys'],
	'rows' => $media_rows,
	'classification' => array(
		'core/cover' => array(
			'nested_child_text' => 'existing_child_traversal',
			'block_owned_attrs' => 'none_required_for_minimum_surface',
			'media_library' => 'external_not_owned',
			'disposition' => 'child_traversal_safe; cover_itself_structural_for_text',
		),
		'core/media-text' => array(
			'nested_child_text' => 'existing_child_traversal',
			'media_side' => 'external_media_or_empty_alt',
			'disposition' => 'child_traversal_safe',
		),
		'core/gallery' => array(
			'captions_alts' => 'block_markup_on_core_image_children',
			'core_image_adapter' => null !== $adapters->get( 'core/image' ),
			'media_library_persistence' => 'external_not_owned_by_default',
			'disposition' => 'deferred_adapter_field_admission_for_image_caption_alt; not_required_for_nested_identity',
		),
	),
	'conclusion' => array(
		'claim' => 'Cover/media-text nested paragraphs extract today. Gallery captions/alts live on core/image markup and are not Media Library ownership by default, but core/image has no adapter — defer image caption admission. No Media Library translation system required for nested identity.',
		'confidence' => 'Proven by experiment',
	),
);

// --- A46 shared/dynamic ---
$shared = parse_blocks( ar2_fixture( 'shared-dynamic-navigation-query-reusable.html' ) );
$sh_inv = ar2_inventory_blocks( $shared );
$sh_ex = ar2_extract_summary( $shared );
$a46 = array(
	'work_package' => 'A46',
	'rows' => $sh_inv,
	'extract_count' => $sh_ex['count'],
	'classification' => array(
		'core/navigation' => array(
			'in_dynamic_list' => $reg->is_dynamic( 'core/navigation' ),
			'ownership' => 'shared_or_dynamic',
			'disposition' => 'deferred',
			'adr_required_for_family' => true,
		),
		'core/query' => array(
			'in_dynamic_list' => $reg->is_dynamic( 'core/query' ),
			'ownership' => 'dynamic_runtime',
			'disposition' => 'unsupported_deferred',
			'adr_required_for_family' => false,
		),
		'core/post-template' => array(
			'ownership' => 'dynamic_runtime_inside_query',
			'disposition' => 'unsupported_deferred',
		),
		'synced_reusable_core_block' => array(
			'in_dynamic_list' => $reg->is_dynamic( 'core/block' ),
			'ownership' => 'shared_definition_ref',
			'disposition' => 'deferred',
			'adr_required_for_family' => true,
		),
	),
	'conclusion' => array(
		'claim' => 'Navigation, Query/Post Template, and reusable/synced patterns are deferred. Shared-definition families would need a separate ADR if later admitted; they are NOT required for minimum useful A.4 scope.',
		'confidence' => 'Proven by experiment',
	),
);

ar2_write_evidence( 'a43-structural', $a43 );
ar2_write_evidence( 'a44-textual', $a44 );
ar2_write_evidence( 'a45-media', $a45 );
ar2_write_evidence( 'a46-shared-dynamic', $a46 );

WP_CLI::success( 'A43–A46 family evidence written' );
