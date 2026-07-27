<?php
/**
 * Strategy F feature-flag dependency rules.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\FeatureFlags;
use PHPUnit\Framework\TestCase;

/**
 * Strategy F feature-flag dependency rules.
 */
final class FeatureFlagsTest extends TestCase {

	public function test_registration_only_flags_have_no_prohibited_combo(): void {
		$flags = array(
			FeatureFlags::REGISTRATION => true,
		);

		$this->assertFalse( FeatureFlags::has_prohibited_combination( $flags ) );
		$this->assertTrue( FeatureFlags::validate_dependencies( $flags )[ FeatureFlags::REGISTRATION ] );
	}

	public function test_render_requires_registration_and_extraction(): void {
		$flags = array(
			FeatureFlags::REGISTRATION => false,
			FeatureFlags::INJECTION    => true,
			FeatureFlags::EXTRACTION   => true,
			FeatureFlags::RENDER       => true,
		);

		$sanitized = FeatureFlags::validate_dependencies( $flags );

		$this->assertFalse( $sanitized[ FeatureFlags::RENDER ] );
		$this->assertTrue( FeatureFlags::has_prohibited_combination( $flags ) );
	}

	public function test_extraction_requires_injection_and_registration(): void {
		$flags = array(
			FeatureFlags::REGISTRATION => true,
			FeatureFlags::INJECTION    => false,
			FeatureFlags::EXTRACTION   => true,
		);

		$sanitized = FeatureFlags::validate_dependencies( $flags );

		$this->assertFalse( $sanitized[ FeatureFlags::EXTRACTION ] );
	}

	public function test_injection_requires_registration(): void {
		$flags = array(
			FeatureFlags::REGISTRATION => false,
			FeatureFlags::INJECTION    => true,
			FeatureFlags::REPAIR       => true,
		);

		$sanitized = FeatureFlags::validate_dependencies( $flags );

		$this->assertFalse( $sanitized[ FeatureFlags::INJECTION ] );
		$this->assertFalse( $sanitized[ FeatureFlags::REPAIR ] );
	}

	public function test_repair_requires_injection(): void {
		$flags = array(
			FeatureFlags::REGISTRATION => true,
			FeatureFlags::INJECTION    => false,
			FeatureFlags::REPAIR       => true,
		);

		$sanitized = FeatureFlags::validate_dependencies( $flags );

		$this->assertFalse( $sanitized[ FeatureFlags::REPAIR ] );
	}

	public function test_frontend_render_requires_extraction_injection_and_registration(): void {
		$flags = array(
			FeatureFlags::REGISTRATION    => true,
			FeatureFlags::INJECTION       => false,
			FeatureFlags::EXTRACTION      => true,
			FeatureFlags::FRONTEND_RENDER => true,
		);

		$sanitized = FeatureFlags::validate_dependencies( $flags );

		$this->assertFalse( $sanitized[ FeatureFlags::FRONTEND_RENDER ] );
		$this->assertTrue( FeatureFlags::has_prohibited_combination( $flags ) );
	}
}
