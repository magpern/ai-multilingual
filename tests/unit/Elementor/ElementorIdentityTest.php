<?php
/**
 * ElementorIdentity unit tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Elementor;

use AIMultilingual\Elementor\ElementorIdentity;
use PHPUnit\Framework\TestCase;

/**
 * Hybrid-D key grammar (A.2 + nested A.3).
 */
final class ElementorIdentityTest extends TestCase {

	private ElementorIdentity $identity;

	protected function setUp(): void {
		parent::setUp();
		$this->identity = new ElementorIdentity();
	}

	public function test_grammar(): void {
		$key = $this->identity->build( 42, 'a1b2c3d', 'title' );
		$this->assertSame( 'e:d:42:a1b2c3d:title', $key );
		$parsed = $this->identity->parse( $key );
		$this->assertSame( 42, $parsed['owner_post_id'] );
		$this->assertSame( 'a1b2c3d', $parsed['element_id'] );
		$this->assertSame( 'title', $parsed['control_key'] );
		$this->assertNull( $parsed['nested_item_id'] );
	}

	public function test_nested_grammar(): void {
		$key = $this->identity->build_nested( 42, 'a1b2c3d', 'tab_title', 'd750f876' );
		$this->assertSame( 'e:d:42:a1b2c3d:tab_title:d750f876', $key );
		$parsed = $this->identity->parse( $key );
		$this->assertSame( 'tab_title', $parsed['control_key'] );
		$this->assertSame( 'd750f876', $parsed['nested_item_id'] );
	}

	public function test_malformed_nested_rejected(): void {
		$this->assertSame( '', $this->identity->build_nested( 1, 'el', 'tab_title', '' ) );
		$this->assertSame( '', $this->identity->build_nested( 1, 'el', 'tab_title', 'bad id' ) );
		$this->assertNull( $this->identity->parse( 'e:d:1:el:tab_title:bad id' ) );
		$this->assertNull( $this->identity->parse( 'e:d:1:el:tab_title:x:extra' ) );
	}

	public function test_old_a2_keys_unchanged(): void {
		$key = $this->identity->build( 10, 'sameid', 'title' );
		$this->assertSame( 'e:d:10:sameid:title', $key );
		$this->assertSame( 5, count( explode( ':', $key ) ) );
	}

	public function test_same_element_id_different_posts(): void {
		$a = $this->identity->build( 10, 'sameid', 'title' );
		$b = $this->identity->build( 11, 'sameid', 'title' );
		$this->assertNotSame( $a, $b );
	}

	public function test_rejects_unsafe_and_gutenberg_keys(): void {
		$this->assertSame( '', $this->identity->build( 0, 'x', 'title' ) );
		$this->assertSame( '', $this->identity->build( 1, 'bad id', 'title' ) );
		$this->assertNull( $this->identity->parse( 'b:uuid:content' ) );
		$this->assertTrue( $this->identity->is_elementor_key( 'e:d:1:x:title' ) );
		$this->assertFalse( $this->identity->is_elementor_key( 'b:uuid:content' ) );
	}
}
