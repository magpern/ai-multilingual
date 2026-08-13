<?php
/**
 * TSC.4 structural attribute guard unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\BlockStructuralAttributeGuard;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Block\BlockStructuralAttributeGuard
 */
final class BlockStructuralAttributeGuardTest extends TestCase {

	public function test_plain_text_replacement_preserves_structure(): void {
		$before = '<div class="wp-block-button"><a class="wp-block-button__link" href="https://example.com">Go</a></div>';
		$after  = '<div class="wp-block-button"><a class="wp-block-button__link" href="https://example.com">Ga</a></div>';

		$this->assertTrue( BlockStructuralAttributeGuard::preserves_structure( $before, $after ) );
	}

	public function test_forged_href_is_rejected(): void {
		$before = '<div class="wp-block-button"><a class="wp-block-button__link" href="https://example.com">Go</a></div>';
		$after  = '<div class="wp-block-button"><a class="wp-block-button__link" href="https://evil.test">Go</a></div>';

		$this->assertFalse( BlockStructuralAttributeGuard::preserves_structure( $before, $after ) );
	}

	public function test_forged_class_is_rejected(): void {
		$before = '<p class="intro">Hello</p>';
		$after  = '<p class="intro evil">Hello</p>';

		$this->assertFalse( BlockStructuralAttributeGuard::preserves_structure( $before, $after ) );
	}

	public function test_forged_data_attribute_is_rejected(): void {
		$before = '<a href="https://example.com" data-track="safe">Link</a>';
		$after  = '<a href="https://example.com" data-track="forged">Link</a>';

		$this->assertFalse( BlockStructuralAttributeGuard::preserves_structure( $before, $after ) );
	}

	public function test_identical_html_is_accepted(): void {
		$html = '<figure><figcaption>Caption</figcaption></figure>';

		$this->assertTrue( BlockStructuralAttributeGuard::preserves_structure( $html, $html ) );
	}

	public function test_added_anchor_element_is_rejected(): void {
		$before = '<p>Text</p>';
		$after  = '<p>Text</p><a href="https://evil.test">X</a>';

		$this->assertFalse( BlockStructuralAttributeGuard::preserves_structure( $before, $after ) );
	}
}
