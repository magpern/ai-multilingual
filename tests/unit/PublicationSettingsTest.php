<?php
/**
 * TI.7 settings defaults and sanitize (unit).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit;

use AIMultilingual\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Safe defaults for publication gate and automation mode.
 */
final class PublicationSettingsTest extends TestCase {

	public function test_defaults_are_safe(): void {
		$defaults = Settings::defaults();
		$this->assertFalse( $defaults['segment_publication_gate_enabled'] );
		$this->assertSame( 'manual', $defaults['auto_publication_mode'] );
	}

	public function test_sanitize_rejects_unknown_mode(): void {
		$clean = Settings::sanitize(
			array(
				'auto_publication_mode' => 'score_threshold',
			)
		);
		$this->assertSame( 'manual', $clean['auto_publication_mode'] );
	}

	public function test_sanitize_accepts_closed_modes(): void {
		foreach ( array( 'manual', 'approved_only', 'controlled_auto' ) as $mode ) {
			$clean = Settings::sanitize( array( 'auto_publication_mode' => $mode ) );
			$this->assertSame( $mode, $clean['auto_publication_mode'] );
		}
	}

	public function test_gate_bool_sanitize(): void {
		$clean = Settings::sanitize( array( 'segment_publication_gate_enabled' => '1' ) );
		$this->assertTrue( $clean['segment_publication_gate_enabled'] );
	}
}
