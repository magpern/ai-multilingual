<?php
/**
 * Dev-only Background Translation Jobs smoke runner for wp eval-file.
 *
 * @package AIMultilingual
 */

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Cache\Cache;
use AIMultilingual\Database\Migrator;
use AIMultilingual\Database\Schema;
use AIMultilingual\Jobs\BackgroundTranslationJobAuditEvents;
use AIMultilingual\Jobs\BackgroundTranslationScheduler;
use AIMultilingual\Jobs\ItemStatuses;
use AIMultilingual\Jobs\JobStatuses;
use AIMultilingual\Jobs\JobTypes;
use AIMultilingual\Jobs\JobsCapabilities;
use AIMultilingual\Language\Languages;
use AIMultilingual\Plugin;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Store;

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

$migrator = new Migrator();
$pass( 'schema_target_6', 6 === Migrator::TARGET && 6 === $migrator->current_version(), 'version=' . $migrator->current_version() );

$jobs_table  = Schema::jobs();
$items_table = Schema::job_items();
$pass( 'table_aiml_jobs', $jobs_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $jobs_table ) ) );
$pass( 'table_aiml_job_items', $items_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $items_table ) ) );

JobsCapabilities::grant_default_roles();
$admin = get_role( 'administrator' );
$pass( 'cap_view', $admin && $admin->has_cap( JobsCapabilities::VIEW_JOBS ) );
$pass( 'cap_manage', $admin && $admin->has_cap( JobsCapabilities::MANAGE_JOBS ) );
$pass( 'cap_run', $admin && $admin->has_cap( JobsCapabilities::RUN_JOBS ) );
$pass( 'cap_cancel', $admin && $admin->has_cap( JobsCapabilities::CANCEL_JOBS ) );

$scheduler = new BackgroundTranslationScheduler();
$health    = $scheduler->health();
$pass( 'as_health', ! empty( $health['available'] ), (string) ( $health['message'] ?? '' ) );

$settings = get_option( Settings::OPTION, array() );
$settings = is_array( $settings ) ? $settings : array();
update_option(
	Settings::OPTION,
	Settings::sanitize(
		array_merge(
			$settings,
			array(
				'block_attr_registration_enabled'  => true,
				'block_uuid_injection_enabled'     => true,
				'block_extraction_enabled'         => true,
				'block_frontend_rendering_enabled' => true,
			)
		)
	)
);
Plugin::instance()->reload_settings();

$langs = new Languages( new Cache() );
$sv    = $langs->find_by_code( 'sv' );
if ( ! $sv ) {
	echo "FATAL\tno sv language\n";
	exit( 1 );
}
$lang_id = (int) $sv->language_id;

$uuid = '550e8400-e29b-41d4-a716-446655440099';
$body = '<!-- wp:paragraph {"' . Contract::ATTR_NAME . '":"' . $uuid . '"} -->'
	. '<p>Jobs smoke source paragraph for automated validation.</p>'
	. '<!-- /wp:paragraph -->';

$post_id = wp_insert_post(
	array(
		'post_title'   => 'AIML Jobs Smoke ' . gmdate( 'YmdHis' ),
		'post_content' => $body,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	),
	true
);
if ( is_wp_error( $post_id ) ) {
	echo 'FATAL\tpost_create\t' . $post_id->get_error_message() . "\n";
	exit( 1 );
}
$post_id = (int) $post_id;
$pass( 'fixture_post', $post_id > 0, 'post_id=' . $post_id );

wp_set_current_user( 1 );

$rest = static function ( string $method, string $route, array $params = array() ) {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && $params ) {
		$request->set_body_params( $params );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
	}
	return rest_do_request( $request );
};

$res = $rest( 'GET', '/aiml/v1/jobs/health' );
$pass( 'rest_health', 200 === $res->get_status(), 'status=' . $res->get_status() );

$res = $rest( 'GET', '/aiml/v1/jobs/diagnostics' );
$diag = $res->get_data();
$pass( 'rest_diagnostics', 200 === $res->get_status() && is_array( $diag ) && isset( $diag['status_counts'] ), 'status=' . $res->get_status() );

$res = $rest( 'GET', '/aiml/v1/jobs' );
$pass( 'rest_list', 200 === $res->get_status(), 'status=' . $res->get_status() );

$audit_events = array();
$audit_cb     = static function ( $event, $payload ) use ( &$audit_events ) {
	$audit_events[] = array(
		'event'   => (string) $event,
		'payload' => is_array( $payload ) ? $payload : array(),
	);
};
add_action( 'aiml_translation_job_audit', $audit_cb, 10, 2 );

$token = 'smoke-' . wp_generate_password( 12, false );
$res   = $rest(
	'POST',
	'/aiml/v1/jobs',
	array(
		'job_type'             => JobTypes::TRANSLATE_MISSING,
		'source_type'          => Store::SOURCE_POST,
		'source_id'            => $post_id,
		'language_id'          => $lang_id,
		'client_token'         => $token,
		'budget_max_requests'  => 50,
		'budget_max_tokens'    => 100000,
	)
);
$create_data = $res->get_data();
$job_id      = is_array( $create_data ) ? (int) ( $create_data['job_id'] ?? $create_data['id'] ?? 0 ) : 0;
if ( ! $job_id && is_array( $create_data ) && isset( $create_data['job']['job_id'] ) ) {
	$job_id = (int) $create_data['job']['job_id'];
}
$pass( 'create_translate_missing', in_array( $res->get_status(), array( 200, 201 ), true ) && $job_id > 0, 'status=' . $res->get_status() . ' job_id=' . $job_id . ' body=' . substr( wp_json_encode( $create_data ), 0, 300 ) );

$res_dup = $rest(
	'POST',
	'/aiml/v1/jobs',
	array(
		'job_type'     => JobTypes::TRANSLATE_MISSING,
		'source_type'  => Store::SOURCE_POST,
		'source_id'    => $post_id,
		'language_id'  => $lang_id,
		'client_token' => $token,
	)
);
$pass( 'create_idempotent', in_array( $res_dup->get_status(), array( 200, 201, 409 ), true ), 'status=' . $res_dup->get_status() );

if ( $job_id > 0 ) {
	$res = $rest( 'GET', '/aiml/v1/jobs/' . $job_id );
	$pass( 'rest_show', 200 === $res->get_status(), 'status=' . $res->get_status() );

	$res = $rest( 'POST', '/aiml/v1/jobs/' . $job_id . '/run', array( 'sync' => true ) );
	$pass( 'run_sync', in_array( $res->get_status(), array( 200, 202 ), true ), 'status=' . $res->get_status() . ' body=' . substr( wp_json_encode( $res->get_data() ), 0, 240 ) );

	$res  = $rest( 'GET', '/aiml/v1/jobs/' . $job_id );
	$job  = $res->get_data();
	$job  = is_array( $job ) && isset( $job['job'] ) ? $job['job'] : $job;
	$stat = is_array( $job ) ? (string) ( $job['status'] ?? '' ) : '';
	$pass( 'progress_after_run', '' !== $stat, 'status=' . $stat );

	// Pause / resume cycle on a fresh queued job if possible.
	$token2      = 'smoke-pause-' . wp_generate_password( 8, false );
	$segment_key = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
	$res2        = $rest(
		'POST',
		'/aiml/v1/jobs',
		array(
			'job_type'     => JobTypes::TRANSLATE_SELECTED,
			'source_type'  => Store::SOURCE_POST,
			'source_id'    => $post_id,
			'language_id'  => $lang_id,
			'segment_keys' => array( $segment_key ),
			'client_token' => $token2,
		)
	);
	$d2     = $res2->get_data();
	$job2   = 0;
	if ( is_array( $d2 ) ) {
		$job2 = (int) ( $d2['job_id'] ?? $d2['id'] ?? ( $d2['job']['job_id'] ?? 0 ) );
	}
	$pass( 'create_translate_selected', in_array( $res2->get_status(), array( 200, 201 ), true ) && $job2 > 0, 'status=' . $res2->get_status() . ' job_id=' . $job2 );

	if ( $job2 > 0 ) {
		$res = $rest( 'POST', '/aiml/v1/jobs/' . $job2 . '/pause' );
		$pass( 'pause', in_array( $res->get_status(), array( 200, 202 ), true ), 'status=' . $res->get_status() );
		$j = $res->get_data();
		$j = is_array( $j ) && isset( $j['job'] ) ? $j['job'] : ( is_array( $j ) ? $j : array() );
		$pass( 'paused_after_boundary', JobStatuses::PAUSED === (string) ( $j['status'] ?? '' ), 'status=' . ( $j['status'] ?? '' ) );
		$res = $rest( 'POST', '/aiml/v1/jobs/' . $job2 . '/resume' );
		$pass( 'resume', in_array( $res->get_status(), array( 200, 202 ), true ), 'status=' . $res->get_status() . ' body=' . substr( wp_json_encode( $res->get_data() ), 0, 200 ) );
		$res = $rest( 'POST', '/aiml/v1/jobs/' . $job2 . '/cancel' );
		$pass( 'cancel', in_array( $res->get_status(), array( 200, 202 ), true ), 'status=' . $res->get_status() );
		$res = $rest( 'GET', '/aiml/v1/jobs/' . $job2 );
		$j   = $res->get_data();
		$j   = is_array( $j ) && isset( $j['job'] ) ? $j['job'] : $j;
		$pass( 'cancelled_terminal', is_array( $j ) && JobStatuses::CANCELLED === (string) ( $j['status'] ?? '' ), 'status=' . ( is_array( $j ) ? ( $j['status'] ?? '' ) : '' ) );
	}

	$res = $rest( 'POST', '/aiml/v1/jobs/' . $job_id . '/retry-failed' );
	$pass( 'retry_failed_endpoint', in_array( $res->get_status(), array( 200, 202, 409, 422 ), true ), 'status=' . $res->get_status() );
}

// Bulk batch grouping
$segment_key = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
$token3      = 'smoke-bulk-' . wp_generate_password( 8, false );
$res         = $rest(
	'POST',
	'/aiml/v1/jobs',
	array(
		'job_type'     => JobTypes::BULK_TRANSLATE,
		'language_id'  => $lang_id,
		'posts'        => array(
			array(
				'source_type'  => Store::SOURCE_POST,
				'source_id'    => $post_id,
				'segment_keys' => array( $segment_key ),
			),
		),
		'client_token' => $token3,
	)
);
$bulk = $res->get_data();
$pass( 'create_bulk', in_array( $res->get_status(), array( 200, 201 ), true ), 'status=' . $res->get_status() . ' body=' . substr( wp_json_encode( $bulk ), 0, 240 ) );
$batch_id = '';
if ( is_array( $bulk ) ) {
	$batch_id = (string) ( $bulk['batch_id'] ?? ( $bulk['jobs'][0]['batch_id'] ?? '' ) );
}
if ( '' !== $batch_id ) {
	$res = $rest( 'GET', '/aiml/v1/jobs/batch/' . $batch_id );
	$pass( 'batch_status', 200 === $res->get_status(), 'status=' . $res->get_status() );
} else {
	$pass( 'batch_status', false, 'no batch_id in create response' );
}

// Capability restriction: subscriber cannot manage
$sub = get_user_by( 'login', 'aiml-jobs-subscriber' );
if ( ! $sub ) {
	$sid = wp_create_user( 'aiml-jobs-subscriber', wp_generate_password( 24 ), 'aiml-jobs-subscriber@example.invalid' );
	$sub = get_user_by( 'id', $sid );
	$sub->set_role( 'subscriber' );
}
wp_set_current_user( (int) $sub->ID );
$res = $rest( 'GET', '/aiml/v1/jobs' );
$pass( 'cap_subscriber_denied', in_array( $res->get_status(), array( 401, 403 ), true ), 'status=' . $res->get_status() );
wp_set_current_user( 1 );

// Store / TM / Glossary / Review regression spot checks
$store = new Store( new Cache() );
$pass( 'store_table_intact', Schema::translations() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::translations() ) ) );
$pass( 'tm_table_intact', Schema::tm() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::tm() ) ) );
$pass( 'glossary_table_intact', Schema::glossary() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::glossary() ) ) );
$pass( 'review_column_intact', Schema::column_exists( Schema::translations(), 'review_status' ) );

// Audit privacy: payloads must not contain bodies/prompts
$bad = false;
foreach ( $audit_events as $ev ) {
	$json = wp_json_encode( $ev['payload'] );
	if ( false !== stripos( (string) $json, 'Jobs smoke source' ) || false !== stripos( (string) $json, 'api_key' ) || false !== stripos( (string) $json, 'prompt_text' ) ) {
		$bad = true;
	}
}
$pass( 'audit_privacy', ! $bad && count( $audit_events ) > 0, 'events=' . count( $audit_events ) );
$pass( 'audit_created_event', (bool) array_filter( $audit_events, static function ( $e ) {
	return BackgroundTranslationJobAuditEvents::CREATED === $e['event'];
} ) );

// Render smoke: published page with /sv/ should not fatally error
$permalink = get_permalink( $post_id );
$pass( 'fixture_permalink', is_string( $permalink ) && '' !== $permalink, (string) $permalink );

// Cleanup retention endpoint via CLI service if available
if ( class_exists( '\\AIMultilingual\\Jobs\\BackgroundTranslationRetentionCleanup' ) ) {
	$cleanup = new AIMultilingual\Jobs\BackgroundTranslationRetentionCleanup(
		new AIMultilingual\Jobs\BackgroundTranslationJobRepository(),
		new AIMultilingual\Jobs\BackgroundTranslationItemRepository()
	);
	$stats   = $cleanup->run();
	$pass( 'cleanup_run', is_array( $stats ), wp_json_encode( $stats ) );
} else {
	$pass( 'cleanup_run', false, 'cleanup class missing' );
}

remove_action( 'aiml_translation_job_audit', $audit_cb, 10 );

$failed = array_filter( $results, static function ( $r ) {
	return ! $r['ok'];
} );
$total  = count( $results );
$okn    = $total - count( $failed );
echo "SUMMARY\t{$okn}/{$total} PASS\n";
if ( $failed ) {
	echo "FAILED\n";
	foreach ( $failed as $f ) {
		echo ' - ' . $f['name'] . ( '' !== $f['detail'] ? ' (' . $f['detail'] . ')' : '' ) . "\n";
	}
	exit( 1 );
}
echo "ALL PASS\n";
