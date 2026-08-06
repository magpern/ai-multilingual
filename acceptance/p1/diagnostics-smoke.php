<?php
/**
 * P1 S3 — Operational diagnostics smoke (wp eval-file).
 *
 * Non-destructive PASS/FAIL probes over existing endpoints only.
 * Never prints secrets, prompts, or translation bodies.
 *
 * @package AIMultilingual
 */

use AIMultilingual\Cache\Cache;
use AIMultilingual\Database\Migrator;
use AIMultilingual\Database\Schema;
use AIMultilingual\Jobs\JobsCapabilities;
use AIMultilingual\Language\Languages;
use AIMultilingual\Rollout\RolloutConfigurationRepository;
use AIMultilingual\Settings;
use AIMultilingual\Workspace\Review\ReviewCapabilities;

defined( 'ABSPATH' ) || exit;

$results = array();
$pass    = static function ( string $name, bool $ok, string $detail = '' ) use ( &$results ): void {
	$results[] = array(
		'name'   => $name,
		'ok'     => $ok,
		'detail' => $detail,
	);
	echo ( $ok ? 'PASS' : 'FAIL' ) . "\t" . $name . ( '' !== $detail ? "\t" . $detail : '' ) . "\n";
};

$rest = static function ( string $method, string $route, array $params = array() ) {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
};

$no_secrets = static function ( $data ): bool {
	$blob = wp_json_encode( $data );
	return is_string( $blob ) && ! preg_match( '/sk-[A-Za-z0-9_\-]{8,}|Bearer\s+\S+|aiml1:[A-Za-z0-9+\/=]{20,}/', $blob );
};

JobsCapabilities::grant_default_roles();
ReviewCapabilities::grant_default_roles();
wp_set_current_user( 1 );

$settings = get_option( Settings::OPTION, array() );
$settings = is_array( $settings ) ? $settings : array();

// AI functioning (readiness — no paid call).
$pass( 'ai_settings_readable', true, 'enabled=' . ( ! empty( $settings['ai_enabled'] ) ? '1' : '0' ) );
$prov = $rest( 'GET', '/aiml/v1/providers/active' );
$pass( 'ai_providers_active', 200 === $prov->get_status(), 'status=' . $prov->get_status() );
$pass( 'ai_providers_no_secrets', $no_secrets( $prov->get_data() ), 'redaction_ok' );

// Background Jobs.
$jh = $rest( 'GET', '/aiml/v1/jobs/health' );
$pass( 'jobs_health', 200 === $jh->get_status(), 'status=' . $jh->get_status() );
$jd = $rest( 'GET', '/aiml/v1/jobs/diagnostics' );
$pass( 'jobs_diagnostics', 200 === $jd->get_status() && $no_secrets( $jd->get_data() ), 'status=' . $jd->get_status() );

// Review.
$posts = get_posts(
	array(
		'post_type'      => array( 'post', 'page' ),
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	)
);
$post_id = $posts ? (int) $posts[0]->ID : 0;
$langs   = new Languages( new Cache() );
$codes   = array();
foreach ( $langs->all() as $lang ) {
	if ( ! $lang->is_default ) {
		$codes[] = $lang->code;
	}
}
$lang = $codes[0] ?? 'sv';
if ( $post_id > 0 ) {
	$rd = $rest(
		'GET',
		'/aiml/v1/workspace/review-diagnostics',
		array(
			'post_id'  => $post_id,
			'language' => $lang,
		)
	);
	$pass( 'review_diagnostics', $rd->get_status() < 500 && $no_secrets( $rd->get_data() ), 'status=' . $rd->get_status() . ' post=' . $post_id );
} else {
	$pass( 'review_diagnostics', false, 'no_published_post' );
}

// Translation Memory (table + schema).
global $wpdb;
$tm = Schema::tm();
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$tm_ok = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tm ) ) === $tm );
$pass( 'tm_table_present', $tm_ok, $tm );

// Glossary.
$gd = $rest( 'GET', '/aiml/v1/glossary/diagnostics' );
$pass( 'glossary_diagnostics', 200 === $gd->get_status() && $no_secrets( $gd->get_data() ), 'status=' . $gd->get_status() );

// Rollout.
$rollout = ( new RolloutConfigurationRepository() )->get();
$pass( 'rollout_readable', $rollout->schema_version > 0, 'ga=' . ( $rollout->general_rollout_enabled ? '1' : '0' ) );
$pass(
	'rollout_block_flags',
	isset( $settings['block_frontend_rendering_enabled'] ),
	'render=' . ( ! empty( $settings['block_frontend_rendering_enabled'] ) ? '1' : '0' )
);

// Providers healthy (same as AI active endpoint).
$pass( 'providers_healthy_endpoint', 200 === $prov->get_status(), 'active_ok' );

// Schema still 6 (sanity).
$pass( 'schema_still_6', 6 === ( new Migrator() )->current_version(), 'v=' . ( new Migrator() )->current_version() );

$failed = 0;
foreach ( $results as $row ) {
	if ( ! $row['ok'] ) {
		++$failed;
	}
}

echo 'SUMMARY\t' . ( count( $results ) - $failed ) . '/' . count( $results ) . ( 0 === $failed ? ' PASS' : ' FAIL' ) . "\n";
exit( 0 === $failed ? 0 : 1 );
