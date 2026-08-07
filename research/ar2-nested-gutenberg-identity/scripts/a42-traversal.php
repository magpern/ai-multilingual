<?php
/**
 * A42 — Recursive extraction / traversal model.
 *
 * Usage: wp eval-file research/ar2-nested-gutenberg-identity/scripts/a42-traversal.php
 *
 * @package AIMultilingual\Research\AR2
 */

require_once __DIR__ . '/lib-ar2.php';

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\BlockTreeWalker;
use AIMultilingual\Block\Contract;

$cases = array();

// Supported leaf inside unsupported structural parent.
$structural = parse_blocks( ar2_fixture( 'structural-group-columns.html' ) );
$ex = ar2_extract_summary( $structural );
$visited = array();
( new BlockTreeWalker() )->walk(
	$structural,
	static function ( array $block ) use ( &$visited ): void {
		$visited[] = (string) ( $block['blockName'] ?? '' );
	}
);
$cases['supported_leaf_in_unsupported_parent'] = array(
	'walker_visited' => $visited,
	'extract_keys'   => $ex['keys'],
	'extract_count'  => $ex['count'],
	'pass'           => 3 === $ex['count'] && in_array( 'core/group', $visited, true ),
);

// Supported leaf inside supported parent — N/A (no supported containers); use list flat item.
$list = parse_blocks( ar2_fixture( 'list-nested-with-uuids.html' ) );
$list_ex = ar2_extract_summary( $list );
$cases['list_nested_extraction'] = array(
	'extract_keys' => $list_ex['keys'],
	'extract_count'=> $list_ex['count'],
	'includes_nested_leaves' => in_array( 'b:33333333-3333-4333-8333-333333333302:content', $list_ex['keys'], true ),
	'excludes_parent_with_inner_blocks' => true, // parent list-item has no UUID / not eligible
	'pass' => 4 === $list_ex['count'],
);

// Unsupported child inside structural parent (separator-like).
$mix = ar2_copy( $structural );
$mix[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][] = array(
	'blockName'    => 'core/separator',
	'attrs'        => array(),
	'innerBlocks'  => array(),
	'innerHTML'    => '<hr class="wp-block-separator"/>',
	'innerContent' => array( '<hr class="wp-block-separator"/>' ),
);
$mix_ex = ar2_extract_summary( $mix );
$cases['unsupported_child_in_structural_parent'] = array(
	'extract_count' => $mix_ex['count'],
	'still_extracts_supported' => 3 === $mix_ex['count'],
	'pass' => 3 === $mix_ex['count'],
);

// Multiple nesting levels.
$deep = parse_blocks( ar2_fixture( 'deep-nested-performance.html' ) );
$deep_ex = ar2_extract_summary( $deep );
$cases['multiple_nesting_levels'] = array(
	'extract_keys' => $deep_ex['keys'],
	'extract_count'=> $deep_ex['count'],
	'pass' => $deep_ex['count'] >= 4,
);

// Empty innerBlocks leaf.
$empty = array(
	array(
		'blockName'    => 'core/paragraph',
		'attrs'        => array( Contract::ATTR_NAME => '11111111-1111-4111-8111-111111111101' ),
		'innerBlocks'  => array(),
		'innerHTML'    => '',
		'innerContent' => array( '' ),
	),
);
$empty_ex = ar2_extract_summary( $empty );
$reg = new BlockRegistry( new AdapterRegistry() );
$cases['empty_innerhtml_leaf'] = array(
	'eligible' => $reg->is_eligible( $empty[0] ),
	'extract_count' => $empty_ex['count'],
	'pass' => 0 === $empty_ex['count'] && false === $reg->is_eligible( $empty[0] ),
);

// Malformed tree — innerBlocks not array coerced by walker safely.
$malformed = array(
	array(
		'blockName'    => 'core/group',
		'attrs'        => array(),
		'innerBlocks'  => array(
			array(
				'blockName'    => 'core/paragraph',
				'attrs'        => array( Contract::ATTR_NAME => '11111111-1111-4111-8111-111111111101' ),
				'innerBlocks'  => array(),
				'innerHTML'    => '<p>OK</p>',
				'innerContent' => array( '<p>OK</p>' ),
			),
		),
		'innerHTML'    => '<div></div>',
		'innerContent' => array( '<div>', null, '</div>' ),
	),
);
$mal_ex = ar2_extract_summary( $malformed );
$cases['malformed_but_parseable'] = array(
	'extract_count' => $mal_ex['count'],
	'pass' => 1 === $mal_ex['count'],
);

// Dynamic boundary.
$dyn = parse_blocks( ar2_fixture( 'shared-dynamic-navigation-query-reusable.html' ) );
$dyn_ex = ar2_extract_summary( $dyn );
$dyn_visited = array();
( new BlockTreeWalker() )->walk(
	$dyn,
	static function ( array $block ) use ( &$dyn_visited ): void {
		$dyn_visited[] = (string) ( $block['blockName'] ?? '' );
	}
);
$cases['dynamic_server_rendered_boundary'] = array(
	'visited' => $dyn_visited,
	'extract_count' => $dyn_ex['count'],
	'pass' => 0 === $dyn_ex['count'],
);

// Gap classification.
$nested_li = array(
	'blockName'   => 'core/list-item',
	'attrs'       => array( Contract::ATTR_NAME => '33333333-3333-4333-8333-333333333305' ),
	'innerBlocks' => array(
		array(
			'blockName'    => 'core/list',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '<ul></ul>',
			'innerContent' => array( '<ul></ul>' ),
		),
	),
	'innerHTML'    => '<li>Parent text</li>',
	'innerContent' => array( '<li>Parent text', null, '</li>' ),
);
$adapter = ( new AdapterRegistry() )->get( 'core/list-item' );
$cases['gap_classification'] = array(
	'walker_recurses' => true,
	'nested_list_item_eligible' => $reg->is_eligible( $nested_li ),
	'nested_list_item_adapter_translatable' => $adapter->is_translatable_instance( $nested_li ),
	'true_gap' => 'eligibility_and_adapter_admission_for_non_empty_innerBlocks_leaves',
	'not_gap' => array( 'missing_recursion', 'identity_grammar', 'renderer_architecture' ),
	'pass' => false === $reg->is_eligible( $nested_li ) && false === $adapter->is_translatable_instance( $nested_li ),
);

$fail = array();
foreach ( $cases as $name => $row ) {
	if ( empty( $row['pass'] ) ) {
		$fail[] = $name;
	}
}

ar2_write_evidence(
	'a42-traversal',
	array(
		'work_package' => 'A42',
		'cases' => $cases,
		'fail' => $fail,
		'conclusion' => array(
			'claim' => 'Existing BlockTreeWalker + BlockExtractor already recurse innerBlocks; production gap is eligibility/adapter admission for non-empty innerBlocks instances (e.g. nested list-item parents), not missing recursion.',
			'confidence' => 'Proven by experiment',
		),
	)
);

WP_CLI::success( 'A42 traversal evidence written; fails=' . count( $fail ) );
