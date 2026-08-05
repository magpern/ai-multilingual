<?php
/**
 * Review diagnostics counters unit tests (ADR-0015 §13).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Workspace\Review;

use AIMultilingual\Workspace\Review\ReviewDiagnosticsCounters;
use PHPUnit\Framework\TestCase;

/**
 * Bounded, fixed-key counter behaviour without WordPress loaded.
 */
final class ReviewDiagnosticsCountersTest extends TestCase {

	/**
	 * The key catalog is small, closed, and low-cardinality.
	 */
	public function test_keys_are_bounded_and_fixed(): void {
		$keys = ReviewDiagnosticsCounters::keys();

		$this->assertSame(
			array(
				'conflicts',
				'approval_failures',
				'qa_blocked_approvals',
				'tm_write_back_success',
				'tm_write_back_failure',
			),
			$keys
		);
	}

	/**
	 * Without WordPress option functions, counters() safely defaults to zero.
	 */
	public function test_counters_default_to_zero_without_wordpress(): void {
		$counters = new ReviewDiagnosticsCounters();

		$this->assertSame(
			array(
				'conflicts'             => 0,
				'approval_failures'     => 0,
				'qa_blocked_approvals'  => 0,
				'tm_write_back_success' => 0,
				'tm_write_back_failure' => 0,
			),
			$counters->counters()
		);
	}

	/**
	 * Incrementing without WordPress option functions is a safe no-op.
	 */
	public function test_increment_without_wordpress_is_a_safe_noop(): void {
		$counters = new ReviewDiagnosticsCounters();

		$counters->increment( ReviewDiagnosticsCounters::CONFLICTS );

		$this->assertSame( 0, $counters->counters()['conflicts'] );
	}

	/**
	 * Unknown keys are ignored rather than growing the option's key set.
	 */
	public function test_increment_ignores_unknown_keys(): void {
		$counters = new ReviewDiagnosticsCounters();

		$counters->increment( 'not_a_real_counter' );

		$this->assertArrayNotHasKey( 'not_a_real_counter', $counters->counters() );
	}
}
