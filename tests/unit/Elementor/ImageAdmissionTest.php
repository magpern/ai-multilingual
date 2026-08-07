<?php
/**
 * Image ownership admission unit evidence.
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
 * A34 Image subset — custom caption only.
 */
final class ImageAdmissionTest extends TestCase {

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

	public function test_custom_caption_admitted(): void {
		$tree  = array(
			array(
				'id'         => 'im1',
				'widgetType' => 'image',
				'settings'   => array(
					'caption_source' => 'custom',
					'caption'        => 'Custom cap',
				),
			),
		);
		$units = $this->extractor->extract_from_elements( 3, $tree );
		$this->assertCount( 1, $units );
		$this->assertSame( 'e:d:3:im1:caption', $units[0]->segment_key );
	}

	public function test_attachment_and_none_denied(): void {
		foreach ( array( 'attachment', 'none', '' ) as $source ) {
			$tree  = array(
				array(
					'id'         => 'im2',
					'widgetType' => 'image',
					'settings'   => array(
						'caption_source' => $source,
						'caption'        => 'Ignore',
					),
				),
			);
			$units = $this->extractor->extract_from_elements( 3, $tree );
			$this->assertSame( array(), $units, 'source=' . $source );
		}
	}
}
