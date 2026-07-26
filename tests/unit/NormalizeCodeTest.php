<?php
/**
 * Code and slug normalization.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit;

use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * In code, indentation is meaning, so only line endings are normalized. Slugs
 * arrive already canonicalized through sanitize_title(), so normalization there
 * is limited to trimming and case — which also keeps this helper free of
 * WordPress.
 */
final class NormalizeCodeTest extends TestCase {

	/**
	 * @param string $text Source snippet.
	 */
	private function code( string $text ): string {
		return Store::source_hash( $text, Store::FORMAT_CODE );
	}

	/**
	 * @param string $text Source slug.
	 */
	private function slug( string $text ): string {
		return Store::source_hash( $text, Store::FORMAT_SLUG );
	}

	public function test_line_endings_are_normalized(): void {
		$this->assertSame(
			$this->code( "if (a) {\n  b();\n}" ),
			$this->code( "if (a) {\r\n  b();\r\n}" )
		);
	}

	public function test_indentation_is_significant(): void {
		$this->assertNotSame(
			$this->code( "if (a) {\n  b();\n}" ),
			$this->code( "if (a) {\n    b();\n}" )
		);
	}

	public function test_trailing_whitespace_is_significant(): void {
		$this->assertNotSame(
			$this->code( 'const A = 1;' ),
			$this->code( 'const A = 1;   ' )
		);
	}

	public function test_blank_lines_are_significant(): void {
		$this->assertNotSame(
			$this->code( "a();\nb();" ),
			$this->code( "a();\n\nb();" )
		);
	}

	public function test_internal_spacing_is_significant(): void {
		$this->assertNotSame(
			$this->code( 'a = 1' ),
			$this->code( 'a  =  1' )
		);
	}

	public function test_slug_is_trimmed_and_lowercased(): void {
		$this->assertSame( $this->slug( 'about-us' ), $this->slug( '  About-Us  ' ) );
	}

	public function test_different_slugs_differ(): void {
		$this->assertNotSame( $this->slug( 'about-us' ), $this->slug( 'about-them' ) );
	}

	public function test_slug_normalization_is_idempotent(): void {
		$once  = Store::normalize( 'About-Us', Store::FORMAT_SLUG );
		$twice = Store::normalize( $once, Store::FORMAT_SLUG );

		$this->assertSame( $once, $twice );
	}

	public function test_unknown_format_falls_back_to_plain(): void {
		$this->assertSame(
			Store::normalize( '  a   b ', 'not-a-format' ),
			Store::normalize( '  a   b ', Store::FORMAT_PLAIN )
		);
	}

	public function test_every_declared_format_is_handled(): void {
		foreach ( Store::formats() as $format ) {
			$this->assertIsString( Store::normalize( "  sample\r\n text  ", $format ) );
		}
	}
}
