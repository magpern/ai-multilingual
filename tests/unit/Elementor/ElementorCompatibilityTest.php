<?php
/**
 * ElementorCompatibility unit tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Elementor;

use AIMultilingual\Elementor\Contract;
use AIMultilingual\Elementor\ElementorCompatibility;
use PHPUnit\Framework\TestCase;

/**
 * Compatibility boundary.
 */
final class ElementorCompatibilityTest extends TestCase {

	public function test_unavailable_when_elementor_missing(): void {
		$compat = new ElementorCompatibility();
		$this->assertFalse( $compat->is_elementor_available() );
		$this->assertSame( ElementorCompatibility::STATUS_UNAVAILABLE, $compat->status() );
		$this->assertFalse( $compat->overlays_allowed() );
	}

	public function test_supported_family_constant(): void {
		$this->assertSame( '4.2', Contract::SUPPORTED_MAJOR_MINOR );
	}

	public function test_version_string_support(): void {
		$compat = new ElementorCompatibility();
		$this->assertTrue( $compat->is_version_string_supported( '4.2.1' ) );
		$this->assertTrue( $compat->is_version_string_supported( '4.2.0' ) );
		$this->assertFalse( $compat->is_version_string_supported( '3.9.0' ) );
		$this->assertFalse( $compat->is_version_string_supported( '5.0.0' ) );
		$this->assertFalse( $compat->is_version_string_supported( '' ) );
	}
}
