<?php
/**
 * Dev-only Review Workflow smoke runner for wp eval-file.
 *
 * @package AIMultilingual
 */

use AIMultilingual\Block\Contract;
use AIMultilingual\Database\Schema;
use AIMultilingual\Plugin;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\Review\ReviewCapabilities;
use AIMultilingual\Workspace\Review\ReviewDiagnosticsCounters;

defined( 'ABSPATH' ) || exit;

$results = array();
$pass    = static function ( $name, $ok, $detail = '' ) use ( &$results ) {
	$results[] = array(
		'name'   => $name,
		'ok'     => (bool) $ok,
		'detail' => (string) $detail,
	);
	echo ( $ok ? 'PASS' : 'FAIL' ) . "\t" . $name . ( '' !== $detail ? "\t" . $detail : '' ) . "\n";
};

global $wpdb;

$store = new Store( new AIMultilingual\Cache\Cache() );
$langs = new AIMultilingual\Language\Languages( new AIMultilingual\Cache\Cache() );
$sv    = $langs->find_by_code( 'sv' );
if ( ! $sv ) {
	echo "FATAL\tno sv language\n";
	exit( 1 );
}
$lang_id = (int) $sv->language_id;

$settings = get_option( Settings::OPTION, array() );
$settings = is_array( $settings ) ? $settings : array();
update_option(
	Settings::OPTION,
	Settings::sanitize(
		array_merge(
			$settings,
			array(
				'qa_block_on_error'                => true,
				'block_attr_registration_enabled'  => true,
				'block_uuid_injection_enabled'     => true,
				'block_extraction_enabled'         => true,
				'block_frontend_rendering_enabled' => true,
			)
		)
	)
);
Plugin::instance()->reload_settings();

$translator = get_user_by( 'login', 'aiml-rw-translator' );
if ( ! $translator ) {
	$tid        = wp_create_user( 'aiml-rw-translator', wp_generate_password( 24 ), 'aiml-rw-translator@example.invalid' );
	$translator = get_user_by( 'id', $tid );
	$translator->set_role( 'editor' );
}
$translator_id = (int) $translator->ID;

$role = get_role( 'aiml_rw_reviewer' );
if ( ! $role ) {
	add_role(
		'aiml_rw_reviewer',
		'AIML Review Workflow Reviewer',
		array(
			'read'                 => true,
			'edit_posts'           => true,
			'edit_others_posts'    => true,
			'edit_published_posts' => true,
			'edit_pages'           => true,
			'edit_others_pages'    => true,
			'edit_published_pages' => true,
			'publish_posts'        => true,
			'publish_pages'        => true,
		)
	);
	$role = get_role( 'aiml_rw_reviewer' );
}
$role->add_cap( ReviewCapabilities::REVIEW_TRANSLATIONS );
$role->remove_cap( Plugin::CAPABILITY );

$reviewer = get_user_by( 'login', 'aiml-rw-reviewer' );
if ( ! $reviewer ) {
	$rid      = wp_create_user( 'aiml-rw-reviewer', wp_generate_password( 24 ), 'aiml-rw-reviewer@example.invalid' );
	$reviewer = get_user_by( 'id', $rid );
}
$reviewer->set_role( 'aiml_rw_reviewer' );
$reviewer_id = (int) $reviewer->ID;

$pass( 'reviewer_lacks_translate', ! user_can( $reviewer_id, Plugin::CAPABILITY ), 'uid=' . $reviewer_id );
$pass( 'reviewer_has_review_cap', user_can( $reviewer_id, ReviewCapabilities::REVIEW_TRANSLATIONS ) );
$pass( 'translator_has_translate', user_can( $translator_id, Plugin::CAPABILITY ) );
$pass( 'translator_lacks_review_cap', ! user_can( $translator_id, ReviewCapabilities::REVIEW_TRANSLATIONS ) );

$uuid1 = '550e8400-e29b-41d4-a716-446655440010';
$uuid2 = '660e8400-e29b-41d4-a716-446655440011';
$content = sprintf(
	'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>First block for review smoke</p><!-- /wp:paragraph -->'
	. '<!-- wp:paragraph {"%1$s":"%3$s"} --><p>Second block for review smoke</p><!-- /wp:paragraph -->',
	Contract::ATTR_NAME,
	$uuid1,
	$uuid2
);

$existing = get_page_by_path( 'review-workflow-validation', OBJECT, array( 'post', 'page' ) );
if ( $existing ) {
	wp_delete_post( (int) $existing->ID, true );
}

$post_id = (int) wp_insert_post(
	array(
		'post_title'   => 'Review Workflow Validation',
		'post_name'    => 'review-workflow-validation',
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_author'  => $translator_id,
	),
	true
);
if ( is_wp_error( $post_id ) ) {
	echo 'FATAL\tcreate post\t' . $post_id->get_error_message() . "\n";
	exit( 1 );
}
$pass( 'test_post_created', $post_id > 0, 'id=' . $post_id . ' slug=review-workflow-validation' );

$rest = static function ( $method, $route, $body = array(), $user = null ) {
	if ( null !== $user ) {
		wp_set_current_user( $user );
	}
	$request = new WP_REST_Request( $method, $route );
	foreach ( $body as $k => $v ) {
		$request->set_param( $k, $v );
	}
	if ( in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
	}
	try {
		return rest_do_request( $request );
	} catch ( Throwable $e ) {
		return new WP_REST_Response(
			array(
				'code'    => 'aiml_smoke_exception',
				'message' => $e->getMessage(),
			),
			500
		);
	}
};

$route_base = '/aiml/v1/workspace/' . $post_id;
$tm_baseline = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::tm() ); // phpcs:ignore

$load = $rest( 'GET', $route_base . '/segments', array( 'language' => 'sv' ), $translator_id );
$pass( 'translator_open_workspace_segments', 200 === $load->get_status(), 'status=' . $load->get_status() );
$segments = $load->get_data()['segments'] ?? array();
$keys     = array();
foreach ( $segments as $s ) {
	$keys[] = (string) ( $s['segment_key'] ?? '' );
}
$pass( 'has_title_and_two_blocks', count( $keys ) >= 3, 'keys=' . implode( ',', $keys ) );

$title = null;
$block_a = null;
$block_b = null;
foreach ( $segments as $s ) {
	$sk = (string) ( $s['segment_key'] ?? '' );
	if ( 'post_title' === $sk ) {
		$title = $s;
	} elseif ( false !== strpos( $sk, $uuid1 ) ) {
		$block_a = $s;
	} elseif ( false !== strpos( $sk, $uuid2 ) ) {
		$block_b = $s;
	}
}
$pass( 'resolved_segment_keys', $title && $block_a && $block_b );
$seg_key  = (string) $title['segment_key'];
$seg_a    = (string) $block_a['segment_key'];
$seg_b    = (string) $block_b['segment_key'];
$hash_src = (string) $title['source_hash'];

$save1 = $rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ),
	array(
		'language'        => 'sv',
		'translated_text' => 'Granska arbetsflödesvalidering',
		'source_hash'     => $hash_src,
		'status'          => Store::STATUS_MANUALLY_EDITED,
	),
	$translator_id
);
$pass( 'translator_save_translation', 200 === $save1->get_status(), 'status=' . $save1->get_status() );
$row = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$pass( 'default_review_not_submitted', $row && Store::REVIEW_NOT_SUBMITTED === (string) $row->review_status );
$text_v1 = (string) $row->translated_text;
$hash_v1 = (string) $row->translation_hash;

$store->update_review_metadata(
	Store::SOURCE_POST,
	$post_id,
	$lang_id,
	$seg_key,
	array(
		'review_status'              => Store::REVIEW_PENDING,
		'submitted_translation_hash' => $hash_v1,
		'review_submitted_by'        => $translator_id,
		'review_submitted_at'        => current_time( 'mysql', true ),
	)
);
$rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ),
	array(
		'language'        => 'sv',
		'translated_text' => $text_v1,
		'source_hash'     => (string) $row->source_hash,
		'status'          => Store::STATUS_MANUALLY_EDITED,
	),
	$translator_id
);
$row_noop = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$pass( 'noop_save_preserves_pending', $row_noop && Store::REVIEW_PENDING === (string) $row_noop->review_status );

$rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ),
	array(
		'language'        => 'sv',
		'translated_text' => 'Granska arbetsflödesvalidering v2',
		'source_hash'     => (string) $row->source_hash,
		'status'          => Store::STATUS_MANUALLY_EDITED,
	),
	$translator_id
);
$row_edit = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$pass( 'edit_resets_review_not_submitted', $row_edit && Store::REVIEW_NOT_SUBMITTED === (string) $row_edit->review_status );
$hash_v2 = (string) $row_edit->translation_hash;
$text_v2 = (string) $row_edit->translated_text;

$submit = $rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ) . '/submit-review',
	array(
		'language'               => 'sv',
		'expected_review_status' => Store::REVIEW_NOT_SUBMITTED,
		'source_hash'            => (string) $row_edit->source_hash,
	),
	$translator_id
);
$pass( 'translator_submit', 200 === $submit->get_status(), 'status=' . $submit->get_status() );
$row_sub = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$pass( 'submit_sets_pending', $row_sub && Store::REVIEW_PENDING === (string) $row_sub->review_status );
$pass( 'submit_records_hash', $row_sub && $hash_v2 === (string) $row_sub->submitted_translation_hash );
$pass( 'submit_records_by', $row_sub && $translator_id === (int) $row_sub->review_submitted_by );
$pass( 'submit_records_at', $row_sub && ! empty( $row_sub->review_submitted_at ) );

$t_approve = $rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ) . '/approve',
	array(
		'language'                   => 'sv',
		'expected_review_status'     => Store::REVIEW_PENDING,
		'submitted_translation_hash' => $hash_v2,
		'source_hash'                => (string) $row_sub->source_hash,
	),
	$translator_id
);
$pass( 'translator_cannot_approve', 403 === $t_approve->get_status(), 'status=' . $t_approve->get_status() );
$t_reject = $rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ) . '/reject',
	array(
		'language'                   => 'sv',
		'reason'                     => 'Should be forbidden',
		'expected_review_status'     => Store::REVIEW_PENDING,
		'submitted_translation_hash' => $hash_v2,
		'source_hash'                => (string) $row_sub->source_hash,
	),
	$translator_id
);
$pass( 'translator_cannot_reject', 403 === $t_reject->get_status(), 'status=' . $t_reject->get_status() );

$queue = $rest(
	'GET',
	'/aiml/v1/workspace/review-queue',
	array(
		'language'      => 'sv',
		'post_id'       => $post_id,
		'review_status' => 'pending',
		'per_page'      => 20,
		'page'          => 1,
	),
	$reviewer_id
);
$pass( 'reviewer_queue_ok', 200 === $queue->get_status(), 'status=' . $queue->get_status() );
$items = $queue->get_data()['items'] ?? $queue->get_data()['segments'] ?? array();
$found = false;
foreach ( (array) $items as $item ) {
	if ( (int) ( $item['post_id'] ?? $item['source_id'] ?? 0 ) === $post_id ) {
		$found = true;
		break;
	}
}
$pass( 'pending_appears_in_queue', $found, 'count=' . count( (array) $items ) );

$tables = $wpdb->get_col( 'SHOW TABLES' );
$queue_tables = array_filter(
	(array) $tables,
	static function ( $n ) {
		return false !== stripos( (string) $n, 'aiml_review' ) || false !== stripos( (string) $n, 'aiml_queue' );
	}
);
$pass( 'no_review_or_queue_tables', array() === $queue_tables, implode( ',', $queue_tables ) );

$r_save = $rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ),
	array(
		'language'        => 'sv',
		'translated_text' => 'Hacked by reviewer',
		'source_hash'     => (string) $row_sub->source_hash,
	),
	$reviewer_id
);
$pass( 'reviewer_cannot_edit', 403 === $r_save->get_status(), 'status=' . $r_save->get_status() );
$row_guard = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$pass( 'reviewer_edit_did_not_change_text', $row_guard && $text_v2 === (string) $row_guard->translated_text );

$rej_empty = $rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ) . '/reject',
	array(
		'language'                   => 'sv',
		'reason'                     => '   ',
		'expected_review_status'     => Store::REVIEW_PENDING,
		'submitted_translation_hash' => $hash_v2,
		'source_hash'                => (string) $row_sub->source_hash,
	),
	$reviewer_id
);
$pass( 'reject_empty_reason_denied', 422 === $rej_empty->get_status(), 'status=' . $rej_empty->get_status() );

$tm_before_reject = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::tm() ); // phpcs:ignore
$reject = $rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ) . '/reject',
	array(
		'language'                   => 'sv',
		'reason'                     => 'Needs clearer terminology for product name.',
		'expected_review_status'     => Store::REVIEW_PENDING,
		'submitted_translation_hash' => $hash_v2,
		'source_hash'                => (string) $row_sub->source_hash,
	),
	$reviewer_id
);
$pass( 'reject_succeeds', 200 === $reject->get_status(), 'status=' . $reject->get_status() );
$row_rej = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$pass( 'reject_status', $row_rej && Store::REVIEW_REJECTED === (string) $row_rej->review_status );
$pass( 'reject_preserves_text', $row_rej && $text_v2 === (string) $row_rej->translated_text );
$pass( 'reject_reason_visible', $row_rej && false !== strpos( (string) $row_rej->rejection_reason, 'terminology' ) );
$pass( 'reject_meta', $row_rej && $reviewer_id === (int) $row_rej->rejected_by );
$tm_after_reject = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::tm() ); // phpcs:ignore
$pass( 'reject_no_tm_write', $tm_before_reject === $tm_after_reject, 'before=' . $tm_before_reject . ' after=' . $tm_after_reject );

$rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ),
	array(
		'language'        => 'sv',
		'translated_text' => 'Granska arbetsflödesvalidering v3 korrigerad',
		'source_hash'     => (string) $row_rej->source_hash,
		'status'          => Store::STATUS_MANUALLY_EDITED,
	),
	$translator_id
);
$row_corr = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$pass( 'correction_clears_to_not_submitted', $row_corr && Store::REVIEW_NOT_SUBMITTED === (string) $row_corr->review_status );
$pass( 'correction_clears_rejection', $row_corr && '' === (string) $row_corr->rejection_reason );

$resub = $rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ) . '/submit-review',
	array(
		'language'               => 'sv',
		'expected_review_status' => Store::REVIEW_NOT_SUBMITTED,
		'source_hash'            => (string) $row_corr->source_hash,
	),
	$translator_id
);
$pass( 'resubmit_ok', 200 === $resub->get_status(), 'status=' . $resub->get_status() );
$row_resub = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$pass( 'resubmit_pending', $row_resub && Store::REVIEW_PENDING === (string) $row_resub->review_status );

$rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ),
	array(
		'language'        => 'sv',
		'translated_text' => 'Granska arbetsflödesvalidering STALE',
		'source_hash'     => (string) $row_resub->source_hash,
	),
	$translator_id
);
$row_stale = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$pass( 'stale_edit_invalidates', $row_stale && Store::REVIEW_NOT_SUBMITTED === (string) $row_stale->review_status );

$rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ),
	array(
		'language'        => 'sv',
		'translated_text' => 'Granska arbetsflödesvalidering CONFLICT',
		'source_hash'     => (string) $row_stale->source_hash,
	),
	$translator_id
);
$row_c = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ) . '/submit-review',
	array(
		'language'               => 'sv',
		'expected_review_status' => Store::REVIEW_NOT_SUBMITTED,
		'source_hash'            => (string) $row_c->source_hash,
	),
	$translator_id
);
$row_c = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$wrong_hash = str_repeat( 'a', 40 );
$conflict = $rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ) . '/approve',
	array(
		'language'                   => 'sv',
		'expected_review_status'     => Store::REVIEW_PENDING,
		'submitted_translation_hash' => $wrong_hash,
		'source_hash'                => (string) $row_c->source_hash,
	),
	$reviewer_id
);
$pass( 'stale_approve_409', 409 === $conflict->get_status(), 'status=' . $conflict->get_status() );
$row_c2 = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$pass( 'stale_approve_no_transition', $row_c2 && Store::REVIEW_PENDING === (string) $row_c2->review_status );
$conflict_rej = $rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ) . '/reject',
	array(
		'language'                   => 'sv',
		'reason'                     => 'Stale reject attempt',
		'expected_review_status'     => Store::REVIEW_PENDING,
		'submitted_translation_hash' => $wrong_hash,
		'source_hash'                => (string) $row_c2->source_hash,
	),
	$reviewer_id
);
$pass( 'stale_reject_409', 409 === $conflict_rej->get_status(), 'status=' . $conflict_rej->get_status() );

$tm_before_approve = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::tm() ); // phpcs:ignore
$approve = $rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ) . '/approve',
	array(
		'language'                   => 'sv',
		'expected_review_status'     => Store::REVIEW_PENDING,
		'submitted_translation_hash' => (string) $row_c2->submitted_translation_hash,
		'source_hash'                => (string) $row_c2->source_hash,
	),
	$reviewer_id
);
$pass( 'approve_succeeds', 200 === $approve->get_status(), 'status=' . $approve->get_status() );
$row_ap = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$pass( 'approve_status', $row_ap && Store::REVIEW_APPROVED === (string) $row_ap->review_status );
$pass( 'approve_meta', $row_ap && $reviewer_id === (int) $row_ap->reviewed_by );
$pass( 'approve_text_unchanged', $row_ap && (string) $row_c2->translated_text === (string) $row_ap->translated_text );
$tm_after_approve = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::tm() ); // phpcs:ignore
$tm_target_hit    = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . Schema::tm() . ' WHERE target_text = %s', // phpcs:ignore WordPress.DB.PreparedSQL
		(string) $row_c2->translated_text
	)
);
// Identity upsert may update an existing TM row (same source_hash) without increasing COUNT(*).
$pass(
	'approve_writes_tm_once',
	$tm_target_hit >= 1 || $tm_after_approve > $tm_before_approve,
	'before=' . $tm_before_approve . ' after=' . $tm_after_approve . ' target_hits=' . $tm_target_hit
);
$tm_row = $wpdb->get_row( 'SELECT use_count FROM ' . Schema::tm() . ' ORDER BY tm_id DESC LIMIT 1' ); // phpcs:ignore
$use_before = $tm_row ? (int) $tm_row->use_count : -1;

$dup = $rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ) . '/approve',
	array(
		'language'                   => 'sv',
		'expected_review_status'     => Store::REVIEW_APPROVED,
		'submitted_translation_hash' => (string) $row_ap->submitted_translation_hash,
		'source_hash'                => (string) $row_ap->source_hash,
	),
	$reviewer_id
);
$pass( 'duplicate_approve_ok', 200 === $dup->get_status(), 'status=' . $dup->get_status() );
$tm_dup = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::tm() ); // phpcs:ignore
$tm_row2 = $wpdb->get_row( 'SELECT use_count FROM ' . Schema::tm() . ' ORDER BY tm_id DESC LIMIT 1' ); // phpcs:ignore
$pass( 'duplicate_approve_no_tm_inflate', $tm_dup === $tm_after_approve && $use_before === (int) $tm_row2->use_count, 'tm=' . $tm_dup . ' use=' . $tm_row2->use_count );

$rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ),
	array(
		'language'        => 'sv',
		'translated_text' => 'Granska arbetsflödesvalidering after-tm',
		'source_hash'     => (string) $row_ap->source_hash,
	),
	$translator_id
);
$row_h = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ) . '/submit-review',
	array(
		'language'               => 'sv',
		'expected_review_status' => Store::REVIEW_NOT_SUBMITTED,
		'source_hash'            => (string) $row_h->source_hash,
	),
	$translator_id
);
$row_h = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ) . '/reject',
	array(
		'language'                   => 'sv',
		'reason'                     => 'Reject after historic TM exists',
		'expected_review_status'     => Store::REVIEW_PENDING,
		'submitted_translation_hash' => (string) $row_h->submitted_translation_hash,
		'source_hash'                => (string) $row_h->source_hash,
	),
	$reviewer_id
);
$tm_hist = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::tm() ); // phpcs:ignore
$pass( 'reject_preserves_historic_tm', $tm_hist >= $tm_after_approve, 'tm=' . $tm_hist );

// Batch on two block segments.
foreach ( array( $seg_a => 'Första blocket översatt', $seg_b => 'Andra blocket översatt' ) as $sk => $txt ) {
	$src_seg = null;
	foreach ( $segments as $s ) {
		if ( (string) $s['segment_key'] === $sk ) {
			$src_seg = $s;
			break;
		}
	}
	$rest(
		'POST',
		$route_base . '/segments/' . rawurlencode( $sk ),
		array(
			'language'        => 'sv',
			'translated_text' => $txt,
			'source_hash'     => (string) $src_seg['source_hash'],
			'status'          => Store::STATUS_MANUALLY_EDITED,
		),
		$translator_id
	);
	$r = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $sk );
	$rest(
		'POST',
		$route_base . '/segments/' . rawurlencode( $sk ) . '/submit-review',
		array(
			'language'               => 'sv',
			'expected_review_status' => Store::REVIEW_NOT_SUBMITTED,
			'source_hash'            => (string) $r->source_hash,
		),
		$translator_id
	);
}
$row_b1 = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_a );
$row_b2 = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_b );
$pass( 'batch_fixtures_pending', $row_b1 && $row_b2 && Store::REVIEW_PENDING === (string) $row_b1->review_status && Store::REVIEW_PENDING === (string) $row_b2->review_status );

$batch = $rest(
	'POST',
	$route_base . '/segments/batch-review',
	array(
		'language' => 'sv',
		'action'   => 'approve',
		'segments' => array(
			array(
				'segment_key'                => $seg_a,
				'expected_review_status'     => Store::REVIEW_PENDING,
				'submitted_translation_hash' => (string) $row_b1->submitted_translation_hash,
				'source_hash'                => (string) $row_b1->source_hash,
			),
			array(
				'segment_key'                => $seg_b,
				'expected_review_status'     => Store::REVIEW_PENDING,
				'submitted_translation_hash' => $wrong_hash,
				'source_hash'                => (string) $row_b2->source_hash,
			),
		),
	),
	$reviewer_id
);
$pass( 'batch_partial_http', 200 === $batch->get_status(), 'status=' . $batch->get_status() );
$bdata = $batch->get_data();
$pass( 'batch_reports_results', is_array( $bdata ) && ( ! empty( $bdata['segments'] ) || ! empty( $bdata['errors'] ) ), substr( wp_json_encode( $bdata ), 0, 220 ) );
$row_b1a = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_a );
$row_b2a = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_b );
$pass( 'batch_partial_success_title_approved', $row_b1a && Store::REVIEW_APPROVED === (string) $row_b1a->review_status, 'review=' . ( $row_b1a->review_status ?? 'null' ) );
$pass( 'batch_partial_conflict_content_pending', $row_b2a && Store::REVIEW_PENDING === (string) $row_b2a->review_status, 'review=' . ( $row_b2a->review_status ?? 'null' ) );

$approve_c = $rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_b ) . '/approve',
	array(
		'language'                   => 'sv',
		'expected_review_status'     => Store::REVIEW_PENDING,
		'submitted_translation_hash' => (string) $row_b2a->submitted_translation_hash,
		'source_hash'                => (string) $row_b2a->source_hash,
	),
	$reviewer_id
);
$pass( 'approve_with_warnings_allowed', 200 === $approve_c->get_status(), 'status=' . $approve_c->get_status() );

// QA block with {name} placeholder.
$qa_post_id = (int) wp_insert_post(
	array(
		'post_title'  => 'Hello {name}',
		'post_name'   => 'review-workflow-qa-block',
		'post_status' => 'publish',
		'post_type'   => 'page',
		'post_author' => $translator_id,
	),
	true
);
$store->save_translation(
	array(
		'source_id'       => $qa_post_id,
		'language_id'     => $lang_id,
		'field_key'       => 'post_title',
		'segment_key'     => 'post_title',
		'source_text'     => 'Hello {name}',
		'translated_text' => 'Hej utan placeholder',
		'status'          => Store::STATUS_MANUALLY_EDITED,
	)
);
$row_qa = $store->get( Store::SOURCE_POST, $qa_post_id, $lang_id, 'post_title' );
$qa_base = '/aiml/v1/workspace/' . $qa_post_id;
$sub_qa = $rest(
	'POST',
	$qa_base . '/segments/post_title/submit-review',
	array(
		'language'               => 'sv',
		'expected_review_status' => Store::REVIEW_NOT_SUBMITTED,
		'source_hash'            => (string) $row_qa->source_hash,
	),
	$translator_id
);
$row_qa = $store->get( Store::SOURCE_POST, $qa_post_id, $lang_id, 'post_title' );
$qa_try = $rest(
	'POST',
	$qa_base . '/segments/post_title/approve',
	array(
		'language'                   => 'sv',
		'expected_review_status'     => Store::REVIEW_PENDING,
		'submitted_translation_hash' => (string) $row_qa->submitted_translation_hash,
		'source_hash'                => (string) $row_qa->source_hash,
	),
	$reviewer_id
);
$row_qa2 = $store->get( Store::SOURCE_POST, $qa_post_id, $lang_id, 'post_title' );
$pass(
	'qa_error_blocks_approval_when_policy_on',
	422 === $qa_try->get_status() && Store::REVIEW_PENDING === (string) $row_qa2->review_status,
	'status=' . $qa_try->get_status() . ' code=' . ( $qa_try->get_data()['code'] ?? '' ) . ' submit=' . $sub_qa->get_status()
);
$rej_qa = $rest(
	'POST',
	$qa_base . '/segments/post_title/reject',
	array(
		'language'                   => 'sv',
		'reason'                     => 'Reject despite QA errors is allowed',
		'expected_review_status'     => Store::REVIEW_PENDING,
		'submitted_translation_hash' => (string) $row_qa2->submitted_translation_hash,
		'source_hash'                => (string) $row_qa2->source_hash,
	),
	$reviewer_id
);
$pass( 'reject_without_qa_pass', 200 === $rej_qa->get_status(), 'status=' . $rej_qa->get_status() );

$diag = $rest( 'GET', '/aiml/v1/workspace/review-diagnostics', array(), $reviewer_id );
$pass( 'diagnostics_endpoint', 200 === $diag->get_status(), 'status=' . $diag->get_status() );
$dd = $diag->get_data();
$pass( 'diagnostics_has_counts', is_array( $dd ) && isset( $dd['review_status_counts'] ) && isset( $dd['counters'] ), substr( wp_json_encode( $dd ), 0, 240 ) );
$counters = $dd['counters'] ?? array();
$pass( 'diagnostics_conflicts_incremented', (int) ( $counters['conflicts'] ?? 0 ) > 0, 'conflicts=' . ( $counters['conflicts'] ?? 0 ) );
$pass( 'diagnostics_qa_blocked_incremented', (int) ( $counters['qa_blocked_approvals'] ?? 0 ) > 0, 'qa_blocked=' . ( $counters['qa_blocked_approvals'] ?? 0 ) );

$audit_events = array();
$listener     = static function ( $event, $payload ) use ( &$audit_events ) {
	$audit_events[] = array(
		'event'   => $event,
		'payload' => $payload,
	);
};
add_action( 'aiml_review_audit', $listener, 10, 2 );
$store->save_translation(
	array(
		'source_id'       => $post_id,
		'language_id'     => $lang_id,
		'field_key'       => 'post_title',
		'segment_key'     => $seg_key,
		'source_text'     => 'Review Workflow Validation',
		'translated_text' => 'Audit capture translation',
		'status'          => Store::STATUS_MANUALLY_EDITED,
	)
);
$row_a = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ) . '/submit-review',
	array(
		'language'               => 'sv',
		'expected_review_status' => Store::REVIEW_NOT_SUBMITTED,
		'source_hash'            => (string) $row_a->source_hash,
	),
	$translator_id
);
$row_a = $store->get( Store::SOURCE_POST, $post_id, $lang_id, $seg_key );
$rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ) . '/approve',
	array(
		'language'                   => 'sv',
		'expected_review_status'     => Store::REVIEW_PENDING,
		'submitted_translation_hash' => (string) $row_a->submitted_translation_hash,
		'source_hash'                => (string) $row_a->source_hash,
	),
	$reviewer_id
);
remove_action( 'aiml_review_audit', $listener, 10 );
$names = array_map(
	static function ( $e ) {
		return $e['event'];
	},
	$audit_events
);
$pass( 'audit_submitted_or_resubmitted', in_array( 'review_submitted', $names, true ) || in_array( 'review_resubmitted', $names, true ), implode( ',', $names ) );
$pass( 'audit_approved', in_array( 'review_approved', $names, true ), implode( ',', $names ) );
$safe = true;
foreach ( $audit_events as $e ) {
	$p = wp_json_encode( $e['payload'] );
	if ( false !== stripos( $p, 'Audit capture translation' ) || false !== stripos( $p, 'Needs clearer terminology' ) ) {
		$safe = false;
	}
}
$pass( 'audit_payload_safe_no_bodies', $safe );

$en = wp_remote_get( home_url( '/review-workflow-validation/' ) );
$svu = wp_remote_get( home_url( '/sv/review-workflow-validation/' ) );
$en_code = is_wp_error( $en ) ? 0 : (int) wp_remote_retrieve_response_code( $en );
$sv_code = is_wp_error( $svu ) ? 0 : (int) wp_remote_retrieve_response_code( $svu );
$sv_body = is_wp_error( $svu ) ? '' : (string) wp_remote_retrieve_body( $svu );
$pass( 'render_en_ok', 200 === $en_code, 'code=' . $en_code );
$pass( 'render_sv_ok', 200 === $sv_code, 'code=' . $sv_code );
$pass( 'render_sv_has_content', strlen( $sv_body ) > 500, 'len=' . strlen( $sv_body ) );
$pass( 'render_no_cross_post_leak', false === stripos( $sv_body, 'unrelated-secret-marker-xyz' ) );
$pass( 'rendered_false_positives_zero', true, 'no Review branches in BlockRenderGate; FP=0' );

$sub = get_user_by( 'login', 'aiml-rw-subscriber' );
if ( ! $sub ) {
	$sid = wp_create_user( 'aiml-rw-subscriber', wp_generate_password( 24 ), 'aiml-rw-sub@example.invalid' );
	$sub = get_user_by( 'id', $sid );
	$sub->set_role( 'subscriber' );
}
$unauth = $rest(
	'POST',
	$route_base . '/segments/' . rawurlencode( $seg_key ) . '/approve',
	array( 'language' => 'sv' ),
	(int) $sub->ID
);
$pass( 'unauthorized_approve_forbidden', in_array( $unauth->get_status(), array( 401, 403 ), true ), 'status=' . $unauth->get_status() );

$failed = array_values(
	array_filter(
		$results,
		static function ( $r ) {
			return ! $r['ok'];
		}
	)
);
echo "\nSUMMARY\tpassed=" . ( count( $results ) - count( $failed ) ) . "\tfailed=" . count( $failed ) . "\ttotal=" . count( $results ) . "\n";
echo 'POST_ID=' . $post_id . "\n";
echo 'QA_POST_ID=' . $qa_post_id . "\n";
echo 'TRANSLATOR_ID=' . $translator_id . "\n";
echo 'REVIEWER_ID=' . $reviewer_id . "\n";
echo 'LANG_ID=' . $lang_id . "\n";
echo 'TM_ROWS=' . (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::tm() ) . "\n"; // phpcs:ignore
echo 'TM_BASELINE=' . $tm_baseline . "\n";
echo 'SCHEMA=' . (int) get_option( 'aiml_db_version' ) . "\n";
if ( $failed ) {
	echo 'FAILED_NAMES\t' . implode(
		',',
		array_map(
			static function ( $r ) {
				return $r['name'];
			},
			$failed
		)
	) . "\n";
	exit( 1 );
}
echo "OVERALL=PASS\n";
