<?php
/**
 * ElementorDiagnostics unit tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Elementor;

use AIMultilingual\Elementor\ElementorDiagnostics;
use PHPUnit\Framework\TestCase;

/**
 * Bounded counters.
 */
final class ElementorDiagnosticsTest extends TestCase {

	public function test_known_counters_only(): void {
		$d = new ElementorDiagnostics();
		$d->inc( 'overlay_applied', 2 );
		$d->inc( 'unknown_metric', 99 );
		$snap = $d->snapshot();
		$this->assertSame( 2, $snap['overlay_applied'] );
		$this->assertArrayNotHasKey( 'unknown_metric', $snap );
		$this->assertArrayHasKey( 'cache_isolation_failure', $snap );
	}
}
