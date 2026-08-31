<?php
/**
 * Deactivation and uninstall retention.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Cache\Cache;
use AIMultilingual\Database\Migrator;
use AIMultilingual\Database\Schema;
use AIMultilingual\Plugin;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Extractor;

/**
 * Retention is all-or-nothing (invariant I5). Deactivation removes nothing at
 * all, and uninstall removes nothing unless the setting is explicitly on —
 * because removing half the state would leave a reinstall unable to reason
 * about what it found.
 *
 * The destructive branch is deliberately not exercised here: dropping the
 * tables mid-suite would take the remaining tests with it, since DDL is not
 * transactional. It is verified structurally in PluginGuardTest and end to end
 * in the hardening milestone.
 */
final class LifecycleTest extends AimlTestCase {

	public function test_no_deactivation_hook_is_registered(): void {
		$plugin = plugin_basename( dirname( __DIR__, 2 ) . '/universal-multilingual.php' );

		$this->assertFalse(
			has_action( 'deactivate_' . $plugin ),
			'Deactivation must remove nothing, so there is nothing for it to hook.'
		);
	}

	public function test_retention_is_the_default(): void {
		$settings = new Settings( array() );

		$this->assertFalse( $settings->remove_data_on_uninstall() );
	}

	public function test_uninstall_keeps_everything_when_retention_is_on(): void {
		global $wpdb;

		$swedish = $this->add_language();
		$post    = $this->create_page( 'About Us' );

		$this->translate( $post, $swedish, Extractor::FIELD_TITLE, 'Om oss' );

		update_option( Settings::OPTION, Settings::sanitize( array( 'remove_data_on_uninstall' => false ) ) );

		$this->run_uninstall();

		// Tables.
		foreach ( Schema::all_tables() as $table ) {
			$this->assertSame(
				$table,
				$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ),
				"Uninstall must not drop {$table} while retention is on."
			);
		}

		// Rows.
		$this->assertNotNull( $this->languages->find_by_code( 'sv' ) );
		$this->assertSame(
			'Om oss',
			$this->store->translated_value( 'post', (int) $post->ID, (int) $swedish->language_id, Extractor::FIELD_TITLE )
		);

		// Options: the schema version in particular, without which a reinstall
		// would try to re-run migrations against existing tables.
		$this->assertSame( Migrator::TARGET, (int) get_option( Migrator::OPTION ) );
		$this->assertNotFalse( get_option( Settings::OPTION ) );

		// Capability.
		$this->assertTrue( get_role( 'administrator' )->has_cap( Plugin::CAPABILITY ) );
	}

	public function test_reinstall_after_retained_uninstall_finds_its_translations(): void {
		$swedish = $this->add_language();
		$post    = $this->create_page( 'About Us' );

		$this->translate( $post, $swedish, Extractor::FIELD_TITLE, 'Om oss' );

		update_option( Settings::OPTION, Settings::sanitize( array( 'remove_data_on_uninstall' => false ) ) );

		$this->run_uninstall();

		// Reinstalling is just activation again.
		Plugin::activate();

		$languages = new \AIMultilingual\Language\Languages( new Cache() );

		$this->assertNotNull( $languages->find_by_code( 'sv' ), 'The language should still be configured.' );
		$this->assertSame(
			'Om oss',
			$this->store->translated_value( 'post', (int) $post->ID, (int) $swedish->language_id, Extractor::FIELD_TITLE )
		);
	}

	/**
	 * Runs uninstall.php the way WordPress would.
	 */
	private function run_uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			// WordPress defines this before including uninstall.php; the name is
			// its contract, not ours to prefix.
			define( 'WP_UNINSTALL_PLUGIN', 'universal-multilingual/universal-multilingual.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		}

		require dirname( __DIR__, 2 ) . '/uninstall.php';
	}
}
