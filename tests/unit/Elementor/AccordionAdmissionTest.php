<?php
/**
 * Accordion admission + extract/apply tests.
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
 * A32 Accordion admission evidence (unit).
 */
final class AccordionAdmissionTest extends TestCase {

	private ElementorControlRegistry $registry;
	private ElementorExtractor $extractor;
	private ElementorOverlayApplier $applier;
	private ElementorDiagnostics $diag;

	protected function setUp(): void {
		parent::setUp();
		$this->registry = new ElementorControlRegistry();
		$this->diag      = new ElementorDiagnostics();
		$this->extractor = new ElementorExtractor(
			new ElementorDocumentDetector(),
			$this->registry,
			new ElementorIdentity(),
			$this->diag
		);
		$this->applier   = new ElementorOverlayApplier( $this->registry, $this->diag );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function tree(): array {
		return array(
			array(
				'id'         => 'acc1',
				'widgetType' => 'accordion',
				'settings'   => array(
					'tabs' => array(
						array(
							'_id'         => 'row1',
							'tab_title'   => 'Title A',
							'tab_content' => '<p>Body A</p>',
						),
						array(
							'_id'         => 'row2',
							'tab_title'   => 'Title B',
							'tab_content' => '<p>Body B</p>',
						),
					),
				),
				'elements'   => array(),
			),
		);
	}

	public function test_extracts_title_and_content(): void {
		$units = $this->extractor->extract_from_elements( 4124, $this->tree() );
		$keys  = array_map( static fn( $u ) => $u->segment_key, $units );
		$this->assertContains( 'e:d:4124:acc1:tab_title:row1', $keys );
		$this->assertContains( 'e:d:4124:acc1:tab_content:row1', $keys );
		$this->assertContains( 'e:d:4124:acc1:tab_title:row2', $keys );
		$this->assertCount( 4, $units );
	}

	public function test_reorder_and_overlay(): void {
		$tree                        = $this->tree();
		$units                       = $this->extractor->extract_from_elements( 1, $tree );
		$tree[0]['settings']['tabs'] = array_reverse( $tree[0]['settings']['tabs'] );
		$out                         = $this->applier->apply(
			$tree,
			array(
				'e:d:1:acc1:tab_title:row1'   => 'Titel A',
				'e:d:1:acc1:tab_content:row1' => '<p>Kropp A</p>',
			),
			$units
		);
		$rows                        = $out[0]['settings']['tabs'];
		$by                          = array();
		foreach ( $rows as $row ) {
			$by[ $row['_id'] ] = $row;
		}
		$this->assertSame( 'Titel A', $by['row1']['tab_title'] );
		$this->assertSame( '<p>Kropp A</p>', $by['row1']['tab_content'] );
		$this->assertSame( 'Title B', $by['row2']['tab_title'] );
	}

	public function test_duplicate_widget_and_page_isolation(): void {
		$tree = array(
			array(
				'id'         => 'acc1',
				'widgetType' => 'accordion',
				'settings'   => array(
					'tabs' => array(
						array(
							'_id'         => 'same',
							'tab_title'   => 'X',
							'tab_content' => 'Y',
						),
					),
				),
			),
			array(
				'id'         => 'acc2',
				'widgetType' => 'accordion',
				'settings'   => array(
					'tabs' => array(
						array(
							'_id'         => 'same',
							'tab_title'   => 'X2',
							'tab_content' => 'Y2',
						),
					),
				),
			),
		);
		$a    = $this->extractor->extract_from_elements( 10, $tree );
		$b    = $this->extractor->extract_from_elements( 11, $tree );
		$this->assertNotSame( $a[0]->segment_key, $b[0]->segment_key );
		$keys = array_map( static fn( $u ) => $u->segment_key, $a );
		$this->assertContains( 'e:d:10:acc1:tab_title:same', $keys );
		$this->assertContains( 'e:d:10:acc2:tab_title:same', $keys );
	}

	public function test_a2_controls_unaffected(): void {
		$tree  = array(
			array(
				'id'         => 'hd1',
				'widgetType' => 'heading',
				'settings'   => array( 'title' => 'Hello' ),
			),
		);
		$units = $this->extractor->extract_from_elements( 1, $tree );
		$this->assertSame( 'e:d:1:hd1:title', $units[0]->segment_key );
	}
}
