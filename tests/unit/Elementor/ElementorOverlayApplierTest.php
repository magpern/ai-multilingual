<?php
/**
 * ElementorOverlayApplier unit tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Elementor;

use AIMultilingual\Elementor\ElementorControlRegistry;
use AIMultilingual\Elementor\ElementorDiagnostics;
use AIMultilingual\Elementor\ElementorFrontendBridge;
use AIMultilingual\Elementor\ElementorOverlayApplier;
use AIMultilingual\Elementor\ElementorTranslationUnit;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * Overlay application and local-failure policy.
 */
final class ElementorOverlayApplierTest extends TestCase {

	public function test_hook_constant(): void {
		$this->assertSame( 'elementor/frontend/builder_content_data', ElementorFrontendBridge::HOOK );
	}

	public function test_applies_supported_controls_leaves_unsupported(): void {
		$diag    = new ElementorDiagnostics();
		$applier = new ElementorOverlayApplier( new ElementorControlRegistry(), $diag );

		$units = array(
			new ElementorTranslationUnit( 'e:d:1:hd1:title', 1, 'hd1', 'heading', 'title', 'Hello', Store::source_hash( 'Hello' ), 'plain' ),
			new ElementorTranslationUnit( 'e:d:1:btn1:text', 1, 'btn1', 'button', 'text', 'Go', Store::source_hash( 'Go' ), 'plain' ),
		);

		$data = array(
			array(
				'id'         => 'hd1',
				'widgetType' => 'heading',
				'settings'   => array( 'title' => 'Hello' ),
				'elements'   => array(),
			),
			array(
				'id'         => 'acc1',
				'widgetType' => 'accordion',
				'settings'   => array( 'tabs' => 'source-only' ),
				'elements'   => array(),
			),
			array(
				'id'         => 'btn1',
				'widgetType' => 'button',
				'settings'   => array( 'text' => 'Go' ),
				'elements'   => array(),
			),
		);

		$out = $applier->apply(
			$data,
			array(
				'e:d:1:hd1:title' => 'Hej',
				'e:d:1:btn1:text' => 'Kör',
			),
			$units
		);

		$this->assertSame( 'Hej', $out[0]['settings']['title'] );
		$this->assertSame( 'source-only', $out[1]['settings']['tabs'] );
		$this->assertSame( 'Kör', $out[2]['settings']['text'] );
		$this->assertSame( 2, $diag->snapshot()['overlay_applied'] );
	}

	public function test_missing_overlay_keeps_source_no_document_failure(): void {
		$applier = new ElementorOverlayApplier( new ElementorControlRegistry() );
		$units   = array(
			new ElementorTranslationUnit( 'e:d:1:hd1:title', 1, 'hd1', 'heading', 'title', 'Hello', Store::source_hash( 'Hello' ), 'plain' ),
		);
		$data    = array(
			array(
				'id'         => 'hd1',
				'widgetType' => 'heading',
				'settings'   => array( 'title' => 'Hello' ),
			),
		);

		$out = $applier->apply( $data, array(), $units );
		$this->assertSame( 'Hello', $out[0]['settings']['title'] );
	}
}
