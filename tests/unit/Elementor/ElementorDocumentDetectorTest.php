<?php
/**
 * ElementorDocumentDetector unit tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Elementor;

use AIMultilingual\Elementor\ElementorDocumentDetector;
use PHPUnit\Framework\TestCase;

/**
 * Document detection / decode.
 */
final class ElementorDocumentDetectorTest extends TestCase {

	private ElementorDocumentDetector $detector;

	protected function setUp(): void {
		parent::setUp();
		$this->detector = new ElementorDocumentDetector();
	}

	public function test_payload_detection(): void {
		$this->assertTrue( $this->detector->is_elementor_payload( '[]', '' ) );
		$this->assertTrue( $this->detector->is_elementor_payload( '', 'builder' ) );
		$this->assertFalse( $this->detector->is_elementor_payload( '', '' ) );
	}

	public function test_decode_valid_and_malformed(): void {
		$tree = $this->detector->decode_raw( '[{"id":"abc","elType":"widget","widgetType":"heading","settings":{"title":"Hi"},"elements":[]}]' );
		$this->assertIsArray( $tree );
		$this->assertSame( 'abc', $tree[0]['id'] );

		$this->assertNull( $this->detector->decode_raw( '{not-json' ) );
		$this->assertNull( $this->detector->decode_raw( '' ) );
	}

	public function test_gutenberg_only_not_elementor_payload(): void {
		$this->assertFalse( $this->detector->is_elementor_payload( '', '' ) );
	}
}
