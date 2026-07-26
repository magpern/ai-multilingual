<?php
/**
 * Uninstall handler.
 *
 * Retention is all-or-nothing (invariant I5). With `remove_data_on_uninstall`
 * disabled — the default — this file is a no-op: every table, row, option and
 * capability survives so a later reinstall resumes exactly where it left off.
 * Deleting the schema version while retaining tables, or deleting settings
 * while retaining translations, would leave the plugin in a state it cannot
 * reason about, so partial removal is never performed.
 *
 * With the setting enabled, all plugin-owned state is removed. Canonical
 * WordPress and WooCommerce content is never touched under either branch.
 *
 * @package AIMultilingual
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$aiml_autoload = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $aiml_autoload ) ) {
	require_once $aiml_autoload;
}

$aiml_settings = get_option( \AIMultilingual\Settings::OPTION );
$aiml_settings = \AIMultilingual\Settings::sanitize( $aiml_settings );

if ( empty( $aiml_settings['remove_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// 1. Per-language cache counters must be enumerated before the table holding
// the language IDs is dropped.
$aiml_language_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	'SELECT language_id FROM ' . \AIMultilingual\Database\Schema::languages() // phpcs:ignore WordPress.DB.PreparedSQL
);

foreach ( (array) $aiml_language_ids as $aiml_language_id ) {
	delete_option( 'aiml_lang_version_' . (int) $aiml_language_id );
}

// 2. Remove the capability this plugin created from every role holding it.
$aiml_roles = wp_roles();
foreach ( array_keys( $aiml_roles->roles ) as $aiml_role_name ) {
	$aiml_role = $aiml_roles->get_role( $aiml_role_name );
	if ( $aiml_role instanceof WP_Role && $aiml_role->has_cap( \AIMultilingual\Plugin::CAPABILITY ) ) {
		$aiml_role->remove_cap( \AIMultilingual\Plugin::CAPABILITY );
	}
}

// 3. Plugin options. Milestone 3 adds aiml_glossary_version here, and
// unschedules the aiml_run_job / aiml_jobs_sweep actions before this point.
delete_option( \AIMultilingual\Settings::OPTION );
delete_option( \AIMultilingual\Database\Migrator::OPTION );
delete_option( \AIMultilingual\Cache\Cache::VERSION_OPTION );

// 4. Plugin-owned tables.
foreach ( \AIMultilingual\Database\Schema::all_tables() as $aiml_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $aiml_table ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
}

// 5. Drop anything this plugin left in the object cache. Group flushing is
// optional in the object-cache API, so fall back to leaving the entries to
// expire: they are unreachable once the tables and version counters are gone.
if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
	wp_cache_flush_group( \AIMultilingual\Cache\Cache::GROUP );
}
