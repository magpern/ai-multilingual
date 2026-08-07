<?php
/**
 * A35 bounded widget admission unit evidence.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Elementor;

use AIMultilingual\Elementor\ElementorControlRegistry;
use AIMultilingual\Elementor\ElementorDiagnostics;
use AIMultilingual\Elementor\ElementorDocumentDetector;
use AIMultilingual\Elementor\ElementorExtractor;
use AIMultilingual\Elementor\ElementorIdentity;
use AIMultilingual\Elementor\ElementorOverlayApplier;
use PHPUnit\Framework\TestCase;

/**
 * Icon List + Call to Action admissions.
 */
final class A35WidgetAdmissionTest extends TestCase {

	private ElementorExtractor $extractor;
	private ElementorOverlayApplier $applier;

	protected function setUp(): void {
		parent::setUp();
		$registry        = new ElementorControlRegistry();
		$diag            = new ElementorDiagnostics();
		$this->extractor = new ElementorExtractor(
			new ElementorDocumentDetector(),
			$registry,
			new ElementorIdentity(),
			$diag
		);
		$this->applier   = new ElementorOverlayApplier( $registry, $diag );
	}

	public function test_icon_list_and_cta(): void {
		$tree  = array(
			array(
				'id'         => 'il1',
				'widgetType' => 'icon-list',
				'settings'   => array(
					'icon_list' => array(
						array(
							'_id'  => 'i1',
							'text' => 'Item',
						),
					),
				),
			),
			array(
				'id'         => 'ct1',
				'widgetType' => 'call-to-action',
				'settings'   => array(
					'title'       => 'CTA T',
					'description' => 'CTA D',
					'button'      => 'CTA B',
				),
			),
		);
		$units = $this->extractor->extract_from_elements( 4, $tree );
		$keys  = array_map( static fn( $u ) => $u->segment_key, $units );
		$this->assertContains( 'e:d:4:il1:text:i1', $keys );
		$this->assertContains( 'e:d:4:ct1:title', $keys );
		$this->assertContains( 'e:d:4:ct1:description', $keys );
		$this->assertContains( 'e:d:4:ct1:button', $keys );

		$out = $this->applier->apply(
			$tree,
			array(
				'e:d:4:il1:text:i1' => 'Punkt',
				'e:d:4:ct1:title'   => 'CTA Rubrik',
			),
			$units
		);
		$this->assertSame( 'Punkt', $out[0]['settings']['icon_list'][0]['text'] );
		$this->assertSame( 'CTA Rubrik', $out[1]['settings']['title'] );
	}
}
