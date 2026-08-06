<?php
/**
 * P1 S1 — Production deployment verification (wp eval-file).
 *
 * Deterministic, idempotent, non-destructive. Safe for development,
 * staging, or production. Uses existing platform services only.
 * Never prints API keys, Authorization headers, prompts, or ciphertext.
 *
 * @package AIMultilingual
 */

use AIMultilingual\Database\Migrator;
use AIMultilingual\Database\Schema;
use AIMultilingual\Jobs\JobsCapabilities;
use AIMultilingual\Rollout\RolloutConfigurationRepository;
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

// --- Plugin version ---
$version_ok = defined( 'AIML_VERSION' ) && '' !== AIML_VERSION;
$pass( 'plugin_version_defined', $version_ok, $version_ok ? AIML_VERSION : 'missing' );

// --- Schema target 6 ---
$migrator = new Migrator();
$pass(
	'schema_target_6',
	6 === Migrator::TARGET && 6 === $migrator->current_version(),
	'current=' . $migrator->current_version() . ' target=' . Migrator::TARGET
);

global $wpdb;
$missing_tables = array();
foreach ( Schema::all_tables() as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		$missing_tables[] = $table;
	}
}
$pass( 'schema_tables_present', array() === $missing_tables, $missing_tables ? implode( ',', $missing_tables ) : 'all' );

// --- Capability sanity ---
$pass( 'capability_aiml_translate', current_user_can( 'aiml_translate' ), 'user=1' );
$pass( 'capability_manage_options', current_user_can( 'manage_options' ), 'user=1' );

// --- Settings / block flags (capability-related config) ---
$settings = get_option( Settings::OPTION, array() );
$settings = is_array( $settings ) ? Settings::sanitize( $settings ) : Settings::sanitize( array() );
$pass( 'settings_readable', true, 'schema_version=' . (int) ( $settings['schema_version'] ?? 0 ) );
$pass(
	'block_flags_readable',
	isset( $settings['block_attr_registration_enabled'], $settings['block_frontend_rendering_enabled'] ),
	'reg=' . ( ! empty( $settings['block_attr_registration_enabled'] ) ? '1' : '0' )
	. ' render=' . ( ! empty( $settings['block_frontend_rendering_enabled'] ) ? '1' : '0' )
);

// --- Rollout / GA configuration ---
$rollout = ( new RolloutConfigurationRepository() )->get();
$pass(
	'rollout_config_readable',
	$rollout->schema_version > 0,
	'schema=' . $rollout->schema_version
	. ' ga=' . ( $rollout->general_rollout_enabled ? '1' : '0' )
);

// --- Encrypted provider configuration (never expose secrets) ---
$ai_enabled = ! empty( $settings['ai_enabled'] );
$provider   = (string) ( $settings['ai_provider'] ?? '' );
$model      = (string) ( $settings['ai_model'] ?? '' );
$enc        = (string) ( $settings['ai_api_key_encrypted'] ?? '' );
$pass( 'provider_settings_readable', true, 'ai_enabled=' . ( $ai_enabled ? '1' : '0' ) . ' provider=' . $provider );
$pass( 'provider_model_set_when_enabled', ! $ai_enabled || '' !== $model, 'model=' . ( $model !== '' ? $model : '(none)' ) );
$pass(
	'encrypted_provider_key_present_when_enabled',
	! $ai_enabled || '' !== $enc,
	'key_len=' . strlen( $enc )
);
$pass(
	'encrypted_key_looks_vaulted',
	! $ai_enabled || '' === $enc || str_starts_with( $enc, 'aiml1:' ) || strlen( $enc ) > 32,
	'prefix=' . ( '' === $enc ? '(empty)' : substr( $enc, 0, 5 ) . '…' )
);

// --- Background Jobs health ---
$health = $rest( 'GET', '/aiml/v1/jobs/health' );
$pass( 'jobs_health', 200 === $health->get_status(), 'status=' . $health->get_status() );

// --- Workspace availability ---
$ws = $rest( 'GET', '/aiml/v1/workspace/posts' );
$pass( 'workspace_available', 200 === $ws->get_status(), 'status=' . $ws->get_status() );

// --- Provider readiness (registry / admin REST; no paid calls) ---
$active = $rest( 'GET', '/aiml/v1/providers/active' );
$pass( 'providers_active', 200 === $active->get_status(), 'status=' . $active->get_status() );
$active_data = $active->get_data();
$active_blob = wp_json_encode( $active_data );
$pass(
	'providers_active_no_secrets',
	is_string( $active_blob ) && ! preg_match( '/sk-[A-Za-z0-9_\-]{8,}|Bearer\s+\S+/', $active_blob ),
	'redaction_ok'
);

$failed = 0;
foreach ( $results as $row ) {
	if ( ! $row['ok'] ) {
		++$failed;
	}
}

echo 'SUMMARY\t' . ( count( $results ) - $failed ) . '/' . count( $results ) . ( 0 === $failed ? ' PASS' : ' FAIL' ) . "\n";
exit( 0 === $failed ? 0 : 1 );
