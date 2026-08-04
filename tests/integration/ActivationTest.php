<?php
/**
 * Activation and migrations.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Database\Migrator;
use AIMultilingual\Database\Schema;
use AIMultilingual\Plugin;

/**
 * Activation is what turns an inert plugin into a working one, and it runs in
 * two places — the activation hook and the drift check — so it has to be
 * repeatable.
 */
final class ActivationTest extends AimlTestCase {

	public function test_both_milestone_one_tables_exist(): void {
		global $wpdb;

		foreach ( array( Schema::languages(), Schema::translations(), Schema::tm() ) as $table ) {
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			$this->assertSame( $table, $found, "Table {$table} should exist after activation." );
		}
	}

	public function test_table_names_use_the_site_prefix(): void {
		global $wpdb;

		$this->assertStringStartsWith( $wpdb->prefix, Schema::languages() );
		$this->assertStringStartsWith( $wpdb->prefix, Schema::translations() );
		$this->assertSame( $wpdb->prefix . 'aiml_languages', Schema::languages() );
	}

	public function test_schema_version_is_recorded(): void {
		$this->assertSame( Migrator::TARGET, (int) get_option( Migrator::OPTION ) );
	}

	public function test_default_language_is_seeded_from_the_site_locale(): void {
		$default = $this->languages->default();

		$this->assertNotNull( $default );
		$this->assertTrue( (bool) $default->is_default );
		$this->assertSame( 'en', $default->code );
		$this->assertSame( 'published', $default->status );
	}

	public function test_translation_capability_is_granted(): void {
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );

			$this->assertNotNull( $role );
			$this->assertTrue(
				$role->has_cap( Plugin::CAPABILITY ),
				"Role {$role_name} should be able to translate."
			);
		}
	}

	public function test_subscribers_cannot_translate(): void {
		$role = get_role( 'subscriber' );

		$this->assertNotNull( $role );
		$this->assertFalse( $role->has_cap( Plugin::CAPABILITY ) );
	}

	public function test_reactivation_is_idempotent(): void {
		global $wpdb;

		$before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::languages() ); // phpcs:ignore WordPress.DB

		Plugin::activate();
		Plugin::activate();

		$after = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::languages() ); // phpcs:ignore WordPress.DB

		$this->assertSame( $before, $after, 'Re-activating must not duplicate the default language.' );
		$this->assertSame( Migrator::TARGET, (int) get_option( Migrator::OPTION ) );
	}

	/**
	 * The dev environment deploys by bind mount, where file updates never fire
	 * the activation hook. The drift check is the only thing that migrates
	 * there, so it has to work from a stale recorded version.
	 */
	public function test_drift_check_migrates_from_an_older_recorded_version(): void {
		update_option( Migrator::OPTION, 0 );

		$migrator = new Migrator();

		$this->assertSame( 0, $migrator->current_version() );

		$migrator->maybe_migrate();

		$this->assertSame( Migrator::TARGET, $migrator->current_version() );
	}

	public function test_drift_check_is_a_no_op_when_current(): void {
		$migrator = new Migrator();
		$migrator->maybe_migrate();

		$this->assertSame( Migrator::TARGET, $migrator->current_version() );
	}

	public function test_translations_table_enforces_segment_identity(): void {
		global $wpdb;

		$indexes = $wpdb->get_results( 'SHOW INDEX FROM ' . Schema::translations() ); // phpcs:ignore WordPress.DB

		$names = array();
		foreach ( $indexes as $index ) {
			$names[ $index->Key_name ] = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		$this->assertArrayHasKey( 'segment_identity', $names );
		$this->assertArrayHasKey( 'object_lang', $names );
		$this->assertArrayHasKey( 'lang_status', $names );
	}
}
