<?php
/**
 * Background translation job diagnostics unit tests (plan §22).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\BackgroundTranslationDiagnostics;
use PHPUnit\Framework\TestCase;

/**
 * Bounded, fixed-key counter behaviour without WordPress loaded.
 */
final class BackgroundTranslationDiagnosticsTest extends TestCase {

	/**
	 * The counter key catalog is small, closed, and low-cardinality.
	 */
	public function test_counter_keys_are_bounded_and_fixed(): void {
		$keys = BackgroundTranslationDiagnostics::counter_keys();

		$this->assertSame(
			array(
				'provider_errors',
				'stale_source_conflicts',
				'budget_stops',
				'item_retries',
				'cleanup_jobs_deleted',
				'cleanup_items_deleted',
				'cleanup_orphans_deleted',
				'stuck_leases_recovered',
			),
			$keys
		);
	}

	/**
	 * Without WordPress option functions, counters() safely defaults to zero.
	 */
	public function test_counters_default_to_zero_without_wordpress(): void {
		$diagnostics = new BackgroundTranslationDiagnostics();

		$this->assertSame(
			array(
				'provider_errors'         => 0,
				'stale_source_conflicts'  => 0,
				'budget_stops'            => 0,
				'item_retries'            => 0,
				'cleanup_jobs_deleted'    => 0,
				'cleanup_items_deleted'   => 0,
				'cleanup_orphans_deleted' => 0,
				'stuck_leases_recovered'  => 0,
			),
			$diagnostics->counters()
		);
	}

	/**
	 * Incrementing without WordPress option functions is a safe no-op.
	 */
	public function test_increment_without_wordpress_is_a_safe_noop(): void {
		$diagnostics = new BackgroundTranslationDiagnostics();

		$diagnostics->increment( BackgroundTranslationDiagnostics::PROVIDER_ERRORS );

		$this->assertSame( 0, $diagnostics->counters()['provider_errors'] );
	}

	/**
	 * Unknown keys are ignored rather than growing the option's key set.
	 */
	public function test_increment_ignores_unknown_keys(): void {
		$diagnostics = new BackgroundTranslationDiagnostics();

		$diagnostics->increment( 'not_a_real_counter' );

		$this->assertArrayNotHasKey( 'not_a_real_counter', $diagnostics->counters() );
	}
}
