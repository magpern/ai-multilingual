<?php
/**
 * Versioned schema migrations.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Database;

/**
 * Runs ordered, explicit SQL migration steps and records the schema version.
 *
 * The version lives in its own option (`aiml_db_version`), separate from the
 * settings array, so a settings reset can never be mistaken for a schema
 * reset.
 *
 * Migrations run from two places: the activation hook, and an `admin_init`
 * drift check. The second is not redundant — the target environment deploys
 * this plugin as a bind mount, and updating files in place never fires an
 * activation hook. Without the drift check a schema upgrade would silently
 * never happen there.
 *
 * Every step is idempotent so a partially applied migration can be re-run
 * safely.
 */
final class Migrator {

	/**
	 * Option holding the applied schema version.
	 */
	public const OPTION = 'aiml_db_version';

	/**
	 * Schema version this build expects.
	 */
	public const TARGET = 3;

	/**
	 * Applies any migration steps newer than the recorded version.
	 *
	 * The version is written after each individual step, so an interrupted run
	 * resumes from the step that failed rather than replaying from zero.
	 */
	public function migrate(): void {
		$current = $this->current_version();

		foreach ( $this->steps() as $version => $step ) {
			if ( $version <= $current ) {
				continue;
			}

			$step();

			update_option( self::OPTION, $version, true );
		}
	}

	/**
	 * Runs migrations only when the recorded version is behind this build.
	 */
	public function maybe_migrate(): void {
		if ( $this->current_version() >= self::TARGET ) {
			return;
		}

		$this->migrate();
	}

	/**
	 * Schema version currently applied to the database.
	 */
	public function current_version(): int {
		return (int) get_option( self::OPTION, 0 );
	}

	/**
	 * Ordered migration steps, keyed by the version they produce.
	 *
	 * @return array<int, callable():void>
	 */
	private function steps(): array {
		return array(
			1 => array( $this, 'step_1_initial_tables' ),
			2 => array( $this, 'step_2_translation_memory' ),
			3 => array( $this, 'step_3_rollout_metrics_daily' ),
		);
	}

	/**
	 * Step 1 — the Milestone 1 tables.
	 *
	 * Only `aiml_languages` and `aiml_translations` are created. The slug
	 * projection, jobs, glossary, translation memory, usage and string registry
	 * tables arrive with the milestones that use them, each as its own step.
	 */
	private function step_1_initial_tables(): void {
		global $wpdb;

		$wpdb->query( Schema::create_languages() );    // phpcs:ignore WordPress.DB.PreparedSQL
		$wpdb->query( Schema::create_translations() ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Step 2 — F11 translation memory catalogue (`aiml_tm`).
	 */
	private function step_2_translation_memory(): void {
		global $wpdb;

		$wpdb->query( Schema::create_tm() ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Step 3 — F12 daily rollout metrics aggregates.
	 */
	private function step_3_rollout_metrics_daily(): void {
		global $wpdb;

		$wpdb->query( Schema::create_metrics_daily() ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
}
