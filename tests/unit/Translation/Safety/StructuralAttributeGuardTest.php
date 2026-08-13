<?php
/**
 * Surface-neutral structural attribute guard unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\Safety;

use AIMultilingual\Translation\Safety\StructuralAttributeGuard;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Translation\Safety\StructuralAttributeGuard
 */
final class StructuralAttributeGuardTest extends TestCase {

	public function test_plain_text_replacement_preserves_structure(): void {
		$before = '<div class="wp-block-button"><a class="wp-block-button__link" href="https://example.com">Go</a></div>';
		$after  = '<div class="wp-block-button"><a class="wp-block-button__link" href="https://example.com">Ga</a></div>';

		$this->assertTrue( StructuralAttributeGuard::preserves_structure( $before, $after ) );
	}

	public function test_forged_href_is_rejected(): void {
		$before = '<div class="wp-block-button"><a class="wp-block-button__link" href="https://example.com">Go</a></div>';
		$after  = '<div class="wp-block-button"><a class="wp-block-button__link" href="https://evil.test">Go</a></div>';

		$this->assertFalse( StructuralAttributeGuard::preserves_structure( $before, $after ) );
	}

	public function test_forged_data_attribute_is_rejected(): void {
		$before = '<a href="https://example.com" data-track="safe">Link</a>';
		$after  = '<a href="https://example.com" data-track="forged">Link</a>';

		$this->assertFalse( StructuralAttributeGuard::preserves_structure( $before, $after ) );
	}
}
