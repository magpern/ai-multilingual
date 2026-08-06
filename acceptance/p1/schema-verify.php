<?php
/**
 * P1 S4 — Schema / upgrade verification (wp eval-file).
 *
 * Deterministic, idempotent, non-destructive by default.
 * Set AIML_P1_SIMULATE_UPGRADE=1 to temporarily set aiml_db_version behind
 * TARGET and call maybe_migrate() (idempotent steps). Safe on staging; use
 * deliberately on production.
 *
 * @package AIMultilingual
 */

use AIMultilingual\Database\Migrator;
use AIMultilingual\Database\Schema;

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

$migrator = new Migrator();
$pass( 'target_is_6', 6 === Migrator::TARGET, 'target=' . Migrator::TARGET );

$before = $migrator->current_version();
$pass( 'current_is_6_before', 6 === $before, 'current=' . $before );

global $wpdb;
foreach ( Schema::all_tables() as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table );
	$pass( 'table_' . preg_replace( '/[^a-z0-9_]+/i', '_', $table ), (bool) $exists, $table );
}

$pass(
	'review_status_column',
	Schema::column_exists( Schema::translations(), 'review_status' ),
	'review_status'
);

$pass(
	'glossary_version_option',
	false !== get_option( Schema::GLOSSARY_VERSION_OPTION, false ) || null !== get_option( Schema::GLOSSARY_VERSION_OPTION, null ),
	Schema::GLOSSARY_VERSION_OPTION
);

// Idempotent migrate on current version (fresh-path equivalent when already at TARGET).
$migrator->maybe_migrate();
$pass( 'maybe_migrate_noop_at_target', 6 === $migrator->current_version(), 'current=' . $migrator->current_version() );

$simulate = ( '1' === (string) getenv( 'AIML_P1_SIMULATE_UPGRADE' ) );
if ( $simulate ) {
	update_option( Migrator::OPTION, 5, true );
	$pass( 'simulate_set_behind', 5 === (int) get_option( Migrator::OPTION, 0 ), 'set=5' );
	( new Migrator() )->maybe_migrate();
	$after = ( new Migrator() )->current_version();
	$pass( 'simulate_upgrade_to_6', 6 === $after, 'current=' . $after );
} else {
	$pass( 'simulate_upgrade_skipped', true, 'set AIML_P1_SIMULATE_UPGRADE=1 to exercise drift path' );
}

$failed = 0;
foreach ( $results as $row ) {
	if ( ! $row['ok'] ) {
		++$failed;
	}
}

echo 'SUMMARY\t' . ( count( $results ) - $failed ) . '/' . count( $results ) . ( 0 === $failed ? ' PASS' : ' FAIL' ) . "\n";
exit( 0 === $failed ? 0 : 1 );
