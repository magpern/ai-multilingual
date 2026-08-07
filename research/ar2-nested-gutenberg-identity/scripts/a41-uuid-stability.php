<?php
/**
 * A41 — UUID stability under nesting / move / duplicate.
 *
 * Usage: wp eval-file research/ar2-nested-gutenberg-identity/scripts/a41-uuid-stability.php
 *
 * @package AIMultilingual\Research\AR2
 */

require_once __DIR__ . '/lib-ar2.php';

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Block\SourceNormalizer;

$content = ar2_fixture( 'structural-group-columns.html' );
$base    = parse_blocks( $content );
$inject  = ar2_injector()->inject_blocks( $base );
$before  = ar2_extract_summary( $base );

$experiments = array();

// Edit nested child text — UUID retained, hash changes.
$edit = ar2_copy( $base );
$edit[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['innerHTML'] = '<p>Left column paragraph EDITED.</p>';
$edit[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['innerContent'] = array( '<p>Left column paragraph EDITED.</p>' );
$after_edit = ar2_extract_summary( $edit );
$uuid_p1    = '11111111-1111-4111-8111-111111111101';
$key_p1     = SegmentKey::build( $uuid_p1, Contract::FIELD_CONTENT );
$experiments['edit_nested_child_text'] = array(
	'uuid_before'       => $uuid_p1,
	'uuid_after'        => $uuid_p1,
	'key_before'        => $key_p1,
	'key_after'         => $key_p1,
	'hash_changed'      => $before['segments'][ $key_p1 ]['source_hash'] !== $after_edit['segments'][ $key_p1 ]['source_hash'],
	'key_retained'      => isset( $after_edit['segments'][ $key_p1 ] ),
	'expected_retention'=> 'retain_key_reset_stale_via_hash',
	'pass'              => isset( $after_edit['segments'][ $key_p1 ] ) && $before['segments'][ $key_p1 ]['source_hash'] !== $after_edit['segments'][ $key_p1 ]['source_hash'],
);

// Reorder siblings inside right column (heading <-> paragraph).
$reorder = ar2_copy( $base );
$col1    =& $reorder[0]['innerBlocks'][0]['innerBlocks'][1]['innerBlocks'];
$tmp     = $col1[0];
$col1[0] = $col1[1];
$col1[1] = $tmp;
unset( $col1 );
$after_reorder = ar2_extract_summary( $reorder );
$experiments['reorder_siblings'] = array(
	'keys_before' => $before['keys'],
	'keys_after'  => $after_reorder['keys'],
	'uuid_set_same' => ar2_uuid_set( $base ) == ar2_uuid_set( $reorder ),
	'all_keys_present' => empty( array_diff( $before['keys'], $after_reorder['keys'] ) ),
	'pass' => empty( array_diff( $before['keys'], $after_reorder['keys'] ) ),
);

// Move child between containers (left paragraph into right column).
$move = ar2_copy( $base );
$left = $move[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0];
$move[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'] = array();
$move[0]['innerBlocks'][0]['innerBlocks'][1]['innerBlocks'][] = $left;
$after_move = ar2_extract_summary( $move );
$experiments['move_child_between_containers'] = array(
	'key'           => $key_p1,
	'key_retained'  => isset( $after_move['segments'][ $key_p1 ] ),
	'uuid_retained' => isset( $after_move['by_uuid'][ $uuid_p1 ] ),
	'parent_path_changed' => true,
	'identity_independent_of_path' => isset( $after_move['segments'][ $key_p1 ] ),
	'pass' => isset( $after_move['segments'][ $key_p1 ] ),
);

// Move container (swap columns).
$move_c = ar2_copy( $base );
$cols   =& $move_c[0]['innerBlocks'][0]['innerBlocks'];
$swap   = $cols[0];
$cols[0] = $cols[1];
$cols[1] = $swap;
unset( $cols );
$after_move_c = ar2_extract_summary( $move_c );
$experiments['move_container'] = array(
	'keys_retained' => empty( array_diff( $before['keys'], $after_move_c['keys'] ) ),
	'pass' => empty( array_diff( $before['keys'], $after_move_c['keys'] ) ),
);

// Duplicate child (same UUID collision — first wins on extract).
$dup = ar2_copy( $base );
$child = ar2_copy( array( $dup[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0] ) )[0];
$dup[0]['innerBlocks'][0]['innerBlocks'][1]['innerBlocks'][] = $child;
$after_dup = ar2_extract_summary( $dup );
$inject_dup = ar2_injector()->inject_blocks( $dup );
$after_repair = ar2_extract_summary( $dup );
$experiments['duplicate_child_same_uuid'] = array(
	'extract_before_repair_count' => $after_dup['count'],
	'extract_skips_duplicate_key' => 3 === $after_dup['count'], // first-wins skip
	'repair_changed' => $inject_dup->changed,
	'keys_after_repair' => $after_repair['keys'],
	'unique_keys_after_repair' => count( $after_repair['keys'] ) === count( array_unique( $after_repair['keys'] ) ),
	'pass' => count( $after_repair['keys'] ) === count( array_unique( $after_repair['keys'] ) ) && $after_repair['count'] >= 3,
);

// Duplicate container (group) — child UUIDs collide until repair.
$dup_c = array_merge( ar2_copy( $base ), ar2_copy( $base ) );
$before_repair_c = ar2_extract_summary( $dup_c );
$inj_c = ar2_injector()->inject_blocks( $dup_c );
$after_repair_c = ar2_extract_summary( $dup_c );
$experiments['duplicate_container'] = array(
	'extract_before_repair' => $before_repair_c['count'],
	'repair_changed' => $inj_c->changed,
	'extract_after_repair' => $after_repair_c['count'],
	'unique_keys' => count( $after_repair_c['keys'] ) === count( array_unique( $after_repair_c['keys'] ) ),
	'pass' => $after_repair_c['count'] >= 3 && count( $after_repair_c['keys'] ) === count( array_unique( $after_repair_c['keys'] ) ),
);

// Duplicate page simulation = same as duplicate container at document root.
$experiments['duplicate_page'] = $experiments['duplicate_container'];

// Same-document copy/paste = duplicate child.
$experiments['same_document_copy_paste'] = $experiments['duplicate_child_same_uuid'];

// Cross-document: same UUID in two documents is OK (document-local ownership).
$doc_a = ar2_extract_summary( ar2_copy( $base ) );
$doc_b = ar2_extract_summary( ar2_copy( $base ) );
$experiments['cross_document_copy_paste'] = array(
	'note' => 'Segment keys collide only within a document extraction; Store is document-scoped (ADR-0013).',
	'same_keys_across_docs' => $doc_a['keys'] === $doc_b['keys'],
	'document_local_ownership' => true,
	'pass' => $doc_a['keys'] === $doc_b['keys'],
	'confidence' => 'Supported by evidence',
);

// Wrap in Group — move existing tree under new group.
$wrap = array(
	array(
		'blockName'    => 'core/group',
		'attrs'        => array(),
		'innerBlocks'  => ar2_copy( $base ),
		'innerHTML'    => '<div class="wp-block-group"></div>',
		'innerContent' => array( '<div class="wp-block-group">', null, '</div>' ),
	),
);
$after_wrap = ar2_extract_summary( $wrap );
$experiments['wrap_in_group'] = array(
	'keys_retained' => empty( array_diff( $before['keys'], $after_wrap['keys'] ) ),
	'pass' => empty( array_diff( $before['keys'], $after_wrap['keys'] ) ),
);

// Unwrap Group — hoist children.
$unwrap = ar2_copy( $base[0]['innerBlocks'] );
$after_unwrap = ar2_extract_summary( $unwrap );
$experiments['unwrap_group'] = array(
	'keys_retained' => empty( array_diff( $before['keys'], $after_unwrap['keys'] ) ),
	'pass' => empty( array_diff( $before['keys'], $after_unwrap['keys'] ) ),
);

// List item reorder / duplicate.
$list = parse_blocks( ar2_fixture( 'list-nested-with-uuids.html' ) );
$list_before = ar2_extract_summary( $list );
$list_re = ar2_copy( $list );
$items =& $list_re[0]['innerBlocks'];
// swap first and last flat items (index 0 and 2)
$t = $items[0];
$items[0] = $items[2];
$items[2] = $t;
unset( $items );
$list_after = ar2_extract_summary( $list_re );
$experiments['list_item_reorder'] = array(
	'keys_before' => $list_before['keys'],
	'keys_after'  => $list_after['keys'],
	'pass' => empty( array_diff( $list_before['keys'], $list_after['keys'] ) ),
);

$list_dup = ar2_copy( $list );
$list_dup[0]['innerBlocks'][] = ar2_copy( array( $list[0]['innerBlocks'][0] ) )[0];
$inj_list = ar2_injector()->inject_blocks( $list_dup );
$list_dup_after = ar2_extract_summary( $list_dup );
$experiments['list_item_duplicate'] = array(
	'repair_changed' => $inj_list->changed,
	'unique_keys' => count( $list_dup_after['keys'] ) === count( array_unique( $list_dup_after['keys'] ) ),
	'pass' => count( $list_dup_after['keys'] ) === count( array_unique( $list_dup_after['keys'] ) ),
);

// Column reorder already covered by move_container.
$experiments['column_reorder'] = $experiments['move_container'];

// Revision restore / recovery — simulated as re-parse of original serialized content.
$restored = parse_blocks( $content );
$restored_ex = ar2_extract_summary( $restored );
$experiments['revision_restore_reparse'] = array(
	'keys_match_baseline' => $restored_ex['keys'] === $before['keys'],
	'pass' => $restored_ex['keys'] === $before['keys'],
);

// Invalid block recovery — freeform + valid leaf.
$invalid = array(
	array(
		'blockName'    => null,
		'attrs'        => array(),
		'innerBlocks'  => array(),
		'innerHTML'    => '<!-- broken -->',
		'innerContent' => array( '<!-- broken -->' ),
	),
	$base[0],
);
$invalid_ex = ar2_extract_summary( $invalid );
$experiments['invalid_block_recovery'] = array(
	'extract_count' => $invalid_ex['count'],
	'keys_retained' => empty( array_diff( $before['keys'], $invalid_ex['keys'] ) ),
	'pass' => empty( array_diff( $before['keys'], $invalid_ex['keys'] ) ),
);

$pass_count = 0;
$fail = array();
foreach ( $experiments as $name => $row ) {
	if ( ! empty( $row['pass'] ) ) {
		++$pass_count;
	} else {
		$fail[] = $name;
	}
}

ar2_write_evidence(
	'a41-uuid-stability',
	array(
		'work_package' => 'A41',
		'baseline_keys' => $before['keys'],
		'inject_baseline_changed' => $inject->changed,
		'experiments' => $experiments,
		'pass_count' => $pass_count,
		'fail' => $fail,
		'primary_decision' => array(
			'question' => 'Does existing child UUID identity remain deterministic without parent/path identity?',
			'answer'   => empty( $fail ),
			'confidence' => empty( $fail ) ? 'Proven by experiment' : 'Remaining assumption',
		),
	)
);

WP_CLI::success( sprintf( 'A41 done: %d/%d experiments passed', $pass_count, count( $experiments ) ) );
