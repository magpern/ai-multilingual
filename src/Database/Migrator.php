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
	public const TARGET = 7;

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
			4 => array( $this, 'step_4_glossary' ),
			5 => array( $this, 'step_5_review_workflow' ),
			6 => array( $this, 'step_6_background_jobs' ),
			7 => array( $this, 'step_7_publication_axis' ),
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

	/**
	 * Step 4 — Glossary MVP lexicon table and version option (ADR-0014).
	 */
	private function step_4_glossary(): void {
		global $wpdb;

		$wpdb->query( Schema::create_glossary() ); // phpcs:ignore WordPress.DB.PreparedSQL

		if ( false === get_option( Schema::GLOSSARY_VERSION_OPTION, false ) ) {
			add_option( Schema::GLOSSARY_VERSION_OPTION, 0, '', true );
		}
	}

	/**
	 * Step 5 — Review Workflow additive columns on the Store (ADR-0015).
	 *
	 * Existing rows keep translation content and TM links. Review defaults to
	 * `not_submitted` (fail closed for legacy `status=reviewed`). Columns are
	 * added one at a time so an interrupted upgrade can resume safely.
	 */
	private function step_5_review_workflow(): void {
		global $wpdb;

		$table         = Schema::translations();
		$escaped_table = str_replace( '`', '``', $table );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema table identifier only.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"ALTER TABLE `{$escaped_table}` ROW_FORMAT=DYNAMIC"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$columns = array(
			'review_status'              => "VARCHAR(24) NOT NULL DEFAULT 'not_submitted'",
			'review_submitted_by'        => 'BIGINT UNSIGNED NULL',
			'review_submitted_at'        => 'DATETIME NULL',
			'submitted_translation_hash' => "CHAR(40) NOT NULL DEFAULT ''",
			'rejection_reason'           => "VARCHAR(512) NOT NULL DEFAULT ''",
			'rejected_by'                => 'BIGINT UNSIGNED NULL',
			'rejected_at'                => 'DATETIME NULL',
		);

		foreach ( $columns as $name => $definition ) {
			if ( Schema::column_exists( $table, $name ) ) {
				continue;
			}

			$escaped_table = str_replace( '`', '``', $table );
			$escaped_name  = str_replace( '`', '``', $name );

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- additive DDL; identifiers from Schema only.
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"ALTER TABLE `{$escaped_table}` ADD COLUMN `{$escaped_name}` {$definition}"
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( ! Schema::index_exists( $table, 'lang_review_queue' ) ) {
			$escaped_table = str_replace( '`', '``', $table );

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- additive DDL; identifiers from Schema only.
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"ALTER TABLE `{$escaped_table}` ADD KEY lang_review_queue (language_id, review_status, review_submitted_at)"
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Step 6 — Background Translation Jobs tables (ADR-0011 / J1).
	 *
	 * Creates `aiml_jobs` and `aiml_job_items` only. Action Scheduler hooks
	 * (`aiml_run_job`, `aiml_jobs_sweep`) are registered in J4 — not here.
	 */
	private function step_6_background_jobs(): void {
		global $wpdb;

		$wpdb->query( Schema::create_jobs() );      // phpcs:ignore WordPress.DB.PreparedSQL
		$wpdb->query( Schema::create_job_items() ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Step 7 — Segment publication axis on the Store (ADR-0020 / TI.7).
	 *
	 * Additive columns default to `unpublished` for new writes. Existing rows
	 * that were publicly overlayable under the most permissive pre-TI.7 path
	 * (non-empty translated_text; status not ignored/missing) are backfilled
	 * to `published` so upgrades do not hide currently-visible translations.
	 */
	private function step_7_publication_axis(): void {
		global $wpdb;

		$table         = Schema::translations();
		$escaped_table = str_replace( '`', '``', $table );

		// Ensure DYNAMIC row format before additive ALTERs (InnoDB row-size headroom).
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema table identifier only.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"ALTER TABLE `{$escaped_table}` ROW_FORMAT=DYNAMIC"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$columns = array(
			'publish_status' => "VARCHAR(24) NOT NULL DEFAULT 'unpublished'",
			'published_at'   => 'DATETIME NULL',
			'published_by'   => 'BIGINT UNSIGNED NULL',
		);

		foreach ( $columns as $name => $definition ) {
			if ( Schema::column_exists( $table, $name ) ) {
				continue;
			}

			$escaped_name = str_replace( '`', '``', $name );

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- additive DDL; identifiers from Schema only.
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"ALTER TABLE `{$escaped_table}` ADD COLUMN `{$escaped_name}` {$definition}"
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( ! Schema::index_exists( $table, 'lang_publish_status' ) ) {
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- additive DDL; identifiers from Schema only.
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"ALTER TABLE `{$escaped_table}` ADD KEY lang_publish_status (language_id, publish_status, published_at)"
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- backfill uses Schema table name only.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"UPDATE `{$escaped_table}`
			SET publish_status = 'published',
				published_at = COALESCE(published_at, updated_at, UTC_TIMESTAMP())
			WHERE publish_status = 'unpublished'
			  AND translated_text IS NOT NULL
			  AND TRIM(translated_text) <> ''
			  AND status NOT IN ('ignored', 'missing')"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
