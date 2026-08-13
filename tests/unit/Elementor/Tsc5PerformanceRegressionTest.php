<?php
/**
 * TSC.5 Elementor performance regression for large documents.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Elementor;

use AIMultilingual\Elementor\ElementorControlRegistry;
use AIMultilingual\Elementor\ElementorDocumentDetector;
use AIMultilingual\Elementor\ElementorExtractor;
use AIMultilingual\Elementor\ElementorIdentity;
use AIMultilingual\Elementor\ElementorOverlayApplier;
use AIMultilingual\Elementor\ElementorTranslationUnit;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Elementor\ElementorExtractor
 */
final class Tsc5PerformanceRegressionTest extends TestCase {

	public function test_hundred_element_document_extract_and_overlay_bounded(): void {
		$elements  = array();
		$overlays  = array();
		$units     = array();
		$container = array(
			'id'       => 'root',
			'elType'   => 'container',
			'settings' => array(),
			'elements' => array(),
		);

		for ( $i = 0; $i < 100; ++$i ) {
			$element_id              = 'hd' . $i;
			$container['elements'][] = array(
				'id'         => $element_id,
				'elType'     => 'widget',
				'widgetType' => 'heading',
				'settings'   => array( 'title' => 'Title ' . $i ),
				'elements'   => array(),
			);
			$key                     = 'e:d:1:' . $element_id . ':title';
			$overlays[ $key ]        = 'Mal ' . $i;
			$units[]                 = new ElementorTranslationUnit(
				$key,
				1,
				$element_id,
				'heading',
				'title',
				'Title ' . $i,
				Store::source_hash( 'Title ' . $i, Store::FORMAT_PLAIN ),
				Store::FORMAT_PLAIN
			);
		}

		$extractor = new ElementorExtractor(
			new ElementorDocumentDetector(),
			new ElementorControlRegistry(),
			new ElementorIdentity()
		);

		$start           = microtime( true );
		$extracted       = $extractor->extract_from_elements( 1, array( $container ) );
		$extract_elapsed = microtime( true ) - $start;

		$this->assertCount( 100, $extracted );
		$this->assertLessThan( 2.0, $extract_elapsed );

		$applier         = new ElementorOverlayApplier( new ElementorControlRegistry() );
		$start           = microtime( true );
		$out             = $applier->apply( array( $container ), $overlays, $extracted );
		$overlay_elapsed = microtime( true ) - $start;

		$this->assertSame( 'Mal 99', $out[0]['elements'][99]['settings']['title'] );
		$this->assertLessThan( 2.0, $overlay_elapsed );
	}
}
