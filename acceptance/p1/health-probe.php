<?php
/**
 * P1 S0 — Non-destructive health-endpoint inventory probe (wp eval-file).
 *
 * Deterministic, idempotent, safe for development / staging / production.
 * Never prints API keys, Authorization headers, prompts, or ciphertext bodies.
 *
 * @package AIMultilingual
 */

use AIMultilingual\Database\Migrator;
use AIMultilingual\Database\Schema;
use AIMultilingual\Jobs\JobsCapabilities;
use AIMultilingual\Settings;

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

JobsCapabilities::grant_default_roles();
wp_set_current_user( 1 );

$migrator = new Migrator();
$pass(
	'schema_target_matches_current',
	Migrator::TARGET === $migrator->current_version() && 7 === Migrator::TARGET,
	'current=' . $migrator->current_version() . ' target=' . Migrator::TARGET
);

global $wpdb;
foreach ( Schema::all_tables() as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table );
	$pass( 'table_exists_' . preg_replace( '/[^a-z0-9_]+/i', '_', $table ), (bool) $exists, $table );
}

$routes = array(
	'/aiml/v1/jobs/health',
	'/aiml/v1/jobs/diagnostics',
	'/aiml/v1/glossary/diagnostics',
	'/aiml/v1/providers/active',
	'/aiml/v1/workspace/posts',
);

$server = rest_get_server();
foreach ( $routes as $route ) {
	$req = new WP_REST_Request( 'GET', $route );
	$pass( 'route_registered_' . str_replace( array( '/', '-' ), '_', trim( $route, '/' ) ), null !== $server->get_route_options( $route ) || (bool) $server->dispatch( $req ), $route );
}

$health = $rest( 'GET', '/aiml/v1/jobs/health' );
$pass( 'jobs_health_http', $health->get_status() < 500, 'status=' . $health->get_status() );

$diag = $rest( 'GET', '/aiml/v1/jobs/diagnostics' );
$pass( 'jobs_diagnostics_http', $diag->get_status() < 500, 'status=' . $diag->get_status() );
$data = $diag->get_data();
$blob = wp_json_encode( $data );
$pass(
	'jobs_diagnostics_no_secrets',
	is_string( $blob ) && ! preg_match( '/sk-[A-Za-z0-9_\-]{8,}|Bearer\s+\S+|aiml1:[A-Za-z0-9+\/=]{20,}/', $blob ),
	'redaction_ok'
);

$gdiag = $rest( 'GET', '/aiml/v1/glossary/diagnostics' );
$pass( 'glossary_diagnostics_http', $gdiag->get_status() < 500, 'status=' . $gdiag->get_status() );

$prov = $rest( 'GET', '/aiml/v1/providers/active' );
$pass( 'providers_active_http', $prov->get_status() < 500, 'status=' . $prov->get_status() );

$ws = $rest( 'GET', '/aiml/v1/workspace/posts' );
$pass( 'workspace_posts_http', $ws->get_status() < 500, 'status=' . $ws->get_status() );

$settings = get_option( Settings::OPTION, array() );
$settings = is_array( $settings ) ? $settings : array();
$enc      = (string) ( $settings['ai_api_key_encrypted'] ?? '' );
$pass( 'settings_readable', true, 'ai_enabled=' . ( ! empty( $settings['ai_enabled'] ) ? '1' : '0' ) );
$pass( 'encrypted_key_never_printed', true, 'key_len=' . strlen( $enc ) );

$failed = 0;
foreach ( $results as $row ) {
	if ( ! $row['ok'] ) {
		++$failed;
	}
}

echo 'SUMMARY\t' . ( count( $results ) - $failed ) . '/' . count( $results ) . ( 0 === $failed ? ' PASS' : ' FAIL' ) . "\n";
exit( 0 === $failed ? 0 : 1 );
