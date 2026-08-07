<?php
/**
 * Settings Elementor flag unit tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Elementor;

use AIMultilingual\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Elementor settings flags.
 */
final class ElementorSettingsFlagsTest extends TestCase {

	public function test_defaults_off(): void {
		$s = new Settings( array() );
		$this->assertFalse( $s->elementor_extraction_enabled() );
		$this->assertFalse( $s->elementor_frontend_rendering_enabled() );
	}

	public function test_frontend_requires_extraction(): void {
		$s = new Settings(
			array(
				'elementor_extraction_enabled'         => false,
				'elementor_frontend_rendering_enabled' => true,
			)
		);
		$this->assertFalse( $s->elementor_frontend_rendering_enabled() );

		$s2 = new Settings(
			array(
				'elementor_extraction_enabled'         => true,
				'elementor_frontend_rendering_enabled' => true,
			)
		);
		$this->assertTrue( $s2->elementor_extraction_enabled() );
		$this->assertTrue( $s2->elementor_frontend_rendering_enabled() );
	}
}
