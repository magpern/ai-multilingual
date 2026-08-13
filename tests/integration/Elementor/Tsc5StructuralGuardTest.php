<?php
/**
 * TSC.5 Elementor HTML structural guard integration.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration\Elementor;

use AIMultilingual\Elementor\ElementorControlRegistry;
use AIMultilingual\Elementor\ElementorDiagnostics;
use AIMultilingual\Elementor\ElementorOverlayApplier;
use AIMultilingual\Elementor\ElementorTranslationUnit;
use AIMultilingual\Elementor\Strategy\ElementorStrategyFactory;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Elementor\Strategy\ElementorStructuralApply
 */
final class Tsc5StructuralGuardTest extends TestCase {

	public function test_text_editor_forged_href_rejects_overlay(): void {
		$registry    = new ElementorControlRegistry();
		$diagnostics = new ElementorDiagnostics();
		$factory     = new ElementorStrategyFactory();
		$entry       = $registry->get( 'text-editor', 'editor' );
		$this->assertNotNull( $entry );

		$strategy = $factory->for_entry( $entry );
		$this->assertNotNull( $strategy );

		$source = '<p>Hello <a href="https://example.com" class="link">Go</a></p>';
		$unit   = new ElementorTranslationUnit(
			'e:d:1:te1:editor',
			1,
			'te1',
			'text-editor',
			'editor',
			$source,
			Store::source_hash( $source, Store::FORMAT_HTML ),
			Store::FORMAT_HTML
		);

		$settings = array( 'editor' => $source );
		$strategy->apply(
			$settings,
			$entry,
			array( 'e:d:1:te1:editor' => '<p>Hello <a href="https://evil.test" class="link">Go</a></p>' ),
			array( $unit ),
			$diagnostics
		);

		$this->assertSame( $source, $settings['editor'] );
		$this->assertSame( 1, $diagnostics->snapshot()['structural_rejected'] );
	}

	public function test_text_editor_safe_translation_applies(): void {
		$registry    = new ElementorControlRegistry();
		$diagnostics = new ElementorDiagnostics();
		$factory     = new ElementorStrategyFactory();
		$entry       = $registry->get( 'text-editor', 'editor' );
		$this->assertNotNull( $entry );

		$strategy = $factory->for_entry( $entry );
		$this->assertNotNull( $strategy );

		$source = '<p>Hello <a href="https://example.com" class="link">Go</a></p>';
		$unit   = new ElementorTranslationUnit(
			'e:d:1:te1:editor',
			1,
			'te1',
			'text-editor',
			'editor',
			$source,
			Store::source_hash( $source, Store::FORMAT_HTML ),
			Store::FORMAT_HTML
		);

		$settings = array( 'editor' => $source );
		$strategy->apply(
			$settings,
			$entry,
			array( 'e:d:1:te1:editor' => '<p>Hej <a href="https://example.com" class="link">Gå</a></p>' ),
			array( $unit ),
			$diagnostics
		);

		$this->assertStringContainsString( 'Hej', $settings['editor'] );
		$this->assertSame( 0, $diagnostics->snapshot()['structural_rejected'] );
	}

	public function test_overlay_applier_never_writes_canonical_meta(): void {
		$applier = new ElementorOverlayApplier( new ElementorControlRegistry() );
		$data    = array(
			array(
				'id'         => 'hd1',
				'widgetType' => 'heading',
				'settings'   => array( 'title' => 'Canonical' ),
			),
		);
		$units = array(
			new ElementorTranslationUnit(
				'e:d:1:hd1:title',
				1,
				'hd1',
				'heading',
				'title',
				'Canonical',
				Store::source_hash( 'Canonical', Store::FORMAT_PLAIN ),
				Store::FORMAT_PLAIN
			),
		);

		$out = $applier->apply( $data, array( 'e:d:1:hd1:title' => 'Overlay' ), $units );
		$this->assertSame( 'Overlay', $out[0]['settings']['title'] );
		$this->assertSame( 'Canonical', $data[0]['settings']['title'], 'Input tree must remain unchanged.' );
	}
}
