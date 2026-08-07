<?php
/**
 * ElementorExtractor unit tests.
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
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * Hybrid-D extraction.
 */
final class ElementorExtractorTest extends TestCase {

	private ElementorExtractor $extractor;
	private ElementorDiagnostics $diagnostics;

	protected function setUp(): void {
		parent::setUp();
		$this->diagnostics = new ElementorDiagnostics();
		$this->extractor   = new ElementorExtractor(
			new ElementorDocumentDetector(),
			new ElementorControlRegistry(),
			new ElementorIdentity(),
			$this->diagnostics
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function sample_tree(): array {
		return array(
			array(
				'id'       => 'sec1',
				'elType'   => 'section',
				'elements' => array(
					array(
						'id'         => 'hd1',
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array(
							'title'        => 'Hello',
							'title_mobile' => 'Hi',
						),
						'elements'   => array(),
					),
					array(
						'id'         => 'te1',
						'elType'     => 'widget',
						'widgetType' => 'text-editor',
						'settings'   => array( 'editor' => '<p>Body</p>' ),
						'elements'   => array(),
					),
					array(
						'id'         => 'btn1',
						'elType'     => 'widget',
						'widgetType' => 'button',
						'settings'   => array( 'text' => 'Click' ),
						'elements'   => array(),
					),
					array(
						'id'         => 'acc1',
						'elType'     => 'widget',
						'widgetType' => 'accordion',
						'settings'   => array( 'tabs' => array() ),
						'elements'   => array(),
					),
				),
			),
		);
	}

	public function test_extracts_only_allowlisted_controls(): void {
		$units = $this->extractor->extract_from_elements( 99, $this->sample_tree() );
		$keys  = array_map( static fn( $u ) => $u->segment_key, $units );

		$this->assertContains( 'e:d:99:hd1:title', $keys );
		$this->assertContains( 'e:d:99:te1:editor', $keys );
		$this->assertContains( 'e:d:99:btn1:text', $keys );
		// Accordion present but empty tabs → no nested units.
		$this->assertCount( 3, $units );
		$this->assertNotContains( 'e:d:99:hd1:title_mobile', $keys );
	}

	public function test_source_change_changes_hash_not_identity(): void {
		$tree = $this->sample_tree();
		$a    = $this->extractor->extract_from_elements( 5, $tree );
		$tree[0]['elements'][0]['settings']['title'] = 'Hello world';
		$b = $this->extractor->extract_from_elements( 5, $tree );

		$this->assertSame( $a[0]->segment_key, $b[0]->segment_key );
		$this->assertNotSame( $a[0]->source_hash, $b[0]->source_hash );
		$this->assertSame( Store::source_hash( 'Hello', 'plain' ), $a[0]->source_hash );
	}

	public function test_page_duplicate_isolation(): void {
		$tree = $this->sample_tree();
		$a    = $this->extractor->extract_from_elements( 1, $tree );
		$b    = $this->extractor->extract_from_elements( 2, $tree );
		$this->assertNotSame( $a[0]->segment_key, $b[0]->segment_key );
	}

	public function test_reorder_keeps_identity(): void {
		$tree                = $this->sample_tree();
		$a                   = $this->extractor->extract_from_elements( 7, $tree );
		$el                  = $tree[0]['elements'];
		$tree[0]['elements'] = array( $el[2], $el[0], $el[1], $el[3] );
		$b                   = $this->extractor->extract_from_elements( 7, $tree );

		$keys_a = array_map( static fn( $u ) => $u->segment_key, $a );
		$keys_b = array_map( static fn( $u ) => $u->segment_key, $b );
		sort( $keys_a );
		sort( $keys_b );
		$this->assertSame( $keys_a, $keys_b );
	}

	public function test_malformed_node_continues(): void {
		$tree  = array(
			'not-an-array',
			array(
				'id'         => 'hd1',
				'widgetType' => 'heading',
				'settings'   => array( 'title' => 'Ok' ),
			),
		);
		$units = $this->extractor->extract_from_elements( 3, $tree );
		$this->assertCount( 1, $units );
		$this->assertGreaterThan( 0, $this->diagnostics->snapshot()['identity_error'] );
	}
}
