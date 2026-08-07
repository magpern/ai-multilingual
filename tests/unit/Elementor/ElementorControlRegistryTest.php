<?php
/**
 * ElementorControlRegistry unit tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Elementor;

use AIMultilingual\Elementor\ElementorControlRegistry;
use PHPUnit\Framework\TestCase;

/**
 * A.2 allowlist registry.
 */
final class ElementorControlRegistryTest extends TestCase {

	private ElementorControlRegistry $registry;

	protected function setUp(): void {
		parent::setUp();
		$this->registry = new ElementorControlRegistry();
	}

	public function test_exactly_three_supported_pairs(): void {
		$this->assertCount( 3, $this->registry->all() );
		$this->assertTrue( $this->registry->is_supported( 'heading', 'title' ) );
		$this->assertTrue( $this->registry->is_supported( 'text-editor', 'editor' ) );
		$this->assertTrue( $this->registry->is_supported( 'button', 'text' ) );
	}

	public function test_unsupported_widgets_and_controls_denied(): void {
		$this->assertFalse( $this->registry->is_supported( 'accordion', 'tab_title' ) );
		$this->assertFalse( $this->registry->is_supported( 'heading', 'title_mobile' ) );
		$this->assertFalse( $this->registry->is_supported( 'heading', 'header_size' ) );
		$this->assertFalse( $this->registry->is_supported_widget( 'image' ) );
	}

	public function test_entries_declare_strategies(): void {
		$entry = $this->registry->get( 'text-editor', 'editor' );
		$this->assertNotNull( $entry );
		$this->assertSame( 'settings_string', $entry['extractor'] );
		$this->assertSame( 'settings_string', $entry['renderer'] );
		$this->assertSame( ElementorControlRegistry::SANITIZE_HTML, $entry['sanitization'] );
		$this->assertSame( ElementorControlRegistry::SUPPORT_DIRECT, $entry['support_state'] );
		$this->assertSame( ElementorControlRegistry::NESTING_NONE, $entry['nesting'] );
		$this->assertSame( ElementorControlRegistry::IDENTITY_DOCUMENT_CONTROL, $entry['identity'] );
		$this->assertSame( ElementorControlRegistry::OWNERSHIP_DOCUMENT, $entry['ownership'] );
	}
}
