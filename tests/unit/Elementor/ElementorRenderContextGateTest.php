<?php
/**
 * Elementor render context gate unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Elementor;

use AIMultilingual\Elementor\ElementorRenderContextGate;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Elementor\ElementorRenderContextGate
 */
final class ElementorRenderContextGateTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['aiml_test_elementor_edit_mode'], $GLOBALS['aiml_test_elementor_preview_mode'] );
		remove_all_filters( 'wp_doing_ajax' );
		parent::tearDown();
	}

	public function test_default_visitor_context_allowed(): void {
		$gate = new ElementorRenderContextGate();
		$this->assertTrue( $gate->overlay_allowed() );
	}

	public function test_edit_mode_denied(): void {
		$GLOBALS['aiml_test_elementor_edit_mode'] = true;
		$gate = new ElementorRenderContextGate();
		$this->assertFalse( $gate->overlay_allowed() );
	}

	public function test_preview_mode_denied(): void {
		$GLOBALS['aiml_test_elementor_preview_mode'] = true;
		$gate = new ElementorRenderContextGate();
		$this->assertFalse( $gate->overlay_allowed() );
	}
}
