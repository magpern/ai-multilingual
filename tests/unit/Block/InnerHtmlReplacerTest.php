<?php
/**
 * Strategy F inner HTML replacer.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\InnerHtmlReplacer;
use PHPUnit\Framework\TestCase;

/**
 * DOM-based inner HTML replacement helpers.
 */
final class InnerHtmlReplacerTest extends TestCase {

	public function test_replace_tag_content_preserves_wrapper_attributes(): void {
		$html = '<p class="intro">Hello</p>';

		$result = InnerHtmlReplacer::replace_tag_content( $html, 'p', 'Bonjour' );

		$this->assertSame( '<p class="intro">Bonjour</p>', $result );
	}

	public function test_replace_button_label_preserves_href_and_classes(): void {
		$html = '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.com">Buy</a></div>';

		$result = InnerHtmlReplacer::replace_button_label( $html, 'Acheter' );

		$this->assertStringContainsString( 'href="https://example.com"', $result );
		$this->assertStringContainsString( 'wp-block-button__link', $result );
		$this->assertStringContainsString( 'Acheter', $result );
		$this->assertStringNotContainsString( 'Buy', $result );
	}
}
