<?php
/**
 * JSON normalization.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit;

use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * Key order and insignificant whitespace do not change what a JSON document
 * means, so they are canonicalized away. Invalid JSON is a different matter: it
 * is hashed byte-for-byte, because "repairing" it would let two genuinely
 * different broken documents collide.
 */
final class NormalizeJsonTest extends TestCase {

	/**
	 * @param string $text Source document.
	 */
	private function hash( string $text ): string {
		return Store::source_hash( $text, Store::FORMAT_JSON );
	}

	public function test_key_order_is_irrelevant(): void {
		$this->assertSame(
			$this->hash( '{"title":"Tea","body":"Green"}' ),
			$this->hash( '{"body":"Green","title":"Tea"}' )
		);
	}

	public function test_insignificant_whitespace_is_irrelevant(): void {
		$this->assertSame(
			$this->hash( '{"title":"Tea"}' ),
			$this->hash( "{\n    \"title\" : \"Tea\"\n}" )
		);
	}

	public function test_nested_key_order_is_irrelevant(): void {
		$this->assertSame(
			$this->hash( '{"a":{"x":1,"y":2},"b":3}' ),
			$this->hash( '{"b":3,"a":{"y":2,"x":1}}' )
		);
	}

	/**
	 * Arrays are ordered data; reordering them changes the document.
	 */
	public function test_array_order_is_significant(): void {
		$this->assertNotSame(
			$this->hash( '{"items":["a","b"]}' ),
			$this->hash( '{"items":["b","a"]}' )
		);
	}

	public function test_value_changes_are_detected(): void {
		$this->assertNotSame(
			$this->hash( '{"title":"Tea"}' ),
			$this->hash( '{"title":"Coffee"}' )
		);
	}

	public function test_whitespace_inside_a_string_is_significant(): void {
		$this->assertNotSame(
			$this->hash( '{"title":"Red Tea"}' ),
			$this->hash( '{"title":"Red  Tea"}' )
		);
	}

	public function test_unicode_escaping_style_is_irrelevant(): void {
		$this->assertSame(
			$this->hash( '{"title":"Rött"}' ),
			$this->hash( '{"title":"Rött"}' )
		);
	}

	public function test_slash_escaping_style_is_irrelevant(): void {
		$this->assertSame(
			$this->hash( '{"url":"https:\/\/example.test"}' ),
			$this->hash( '{"url":"https://example.test"}' )
		);
	}

	public function test_invalid_json_is_hashed_byte_sensitively(): void {
		$broken = '{"title":"Tea"';

		$this->assertSame( sha1( $broken ), $this->hash( $broken ) );
	}

	public function test_two_different_broken_documents_do_not_collide(): void {
		$this->assertNotSame(
			$this->hash( '{"title":"Tea"' ),
			$this->hash( '{"title":"Coffee"' )
		);
	}

	public function test_invalid_json_whitespace_is_significant(): void {
		$this->assertNotSame(
			$this->hash( '{"title":"Tea"' ),
			$this->hash( '{"title": "Tea"' )
		);
	}

	public function test_numeric_and_boolean_scalars_round_trip(): void {
		$this->assertSame(
			$this->hash( '{"n":1,"b":true,"z":null}' ),
			$this->hash( '{"z":null,"b":true,"n":1}' )
		);
	}
}
