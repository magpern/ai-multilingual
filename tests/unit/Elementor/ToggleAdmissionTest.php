<?php
/**
 * Toggle admission unit evidence.
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
use PHPUnit\Framework\TestCase;

/**
 * A33 Toggle admission.
 */
final class ToggleAdmissionTest extends TestCase {

	private ElementorExtractor $extractor;

	protected function setUp(): void {
		parent::setUp();
		$this->extractor = new ElementorExtractor(
			new ElementorDocumentDetector(),
			new ElementorControlRegistry(),
			new ElementorIdentity(),
			new ElementorDiagnostics()
		);
	}

	public function test_toggle_nested_with_id(): void {
		$tree  = array(
			array(
				'id'         => 'tg1',
				'widgetType' => 'toggle',
				'settings'   => array(
					'tabs' => array(
						array(
							'_id'         => 't1',
							'tab_title'   => 'Tog Title',
							'tab_content' => '<p>Tog Body</p>',
						),
					),
				),
			),
		);
		$units = $this->extractor->extract_from_elements( 2, $tree );
		$keys  = array_map( static fn( $u ) => $u->segment_key, $units );
		$this->assertContains( 'e:d:2:tg1:tab_title:t1', $keys );
		$this->assertContains( 'e:d:2:tg1:tab_content:t1', $keys );
	}

	public function test_toggle_missing_id_source_only(): void {
		$tree  = array(
			array(
				'id'         => 'tg1',
				'widgetType' => 'toggle',
				'settings'   => array(
					'tabs' => array(
						array(
							'tab_title'   => 'Legacy',
							'tab_content' => 'No id',
						),
					),
				),
			),
		);
		$units = $this->extractor->extract_from_elements( 2, $tree );
		$this->assertSame( array(), $units );
	}
}
