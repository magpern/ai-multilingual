<?php
/**
 * Plain-text normalization.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit;

use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * In a title or label, line endings, non-breaking spaces and runs of
 * whitespace are all cosmetic, so they must not make a translation stale.
 * Anything that changes the words must.
 */
final class NormalizePlainTest extends TestCase {

	/**
	 * @param string $text Source text.
	 */
	private function hash( string $text ): string {
		return Store::source_hash( $text, Store::FORMAT_PLAIN );
	}

	public function test_line_endings_are_equivalent(): void {
		$this->assertSame(
			$this->hash( "Red Tea\nGreen Tea" ),
			$this->hash( "Red Tea\r\nGreen Tea" )
		);

		$this->assertSame(
			$this->hash( "Red Tea\nGreen Tea" ),
			$this->hash( "Red Tea\rGreen Tea" )
		);
	}

	public function test_trailing_and_leading_whitespace_is_ignored(): void {
		$this->assertSame( $this->hash( 'Red Tea' ), $this->hash( "  Red Tea \n\t" ) );
	}

	public function test_internal_whitespace_runs_collapse(): void {
		$this->assertSame( $this->hash( 'Red Tea 500 g' ), $this->hash( "Red   Tea\t500  g" ) );
	}

	/**
	 * @dataProvider provide_nbsp
	 *
	 * @param string $variant A non-breaking space written some other way.
	 */
	public function test_nbsp_variants_match_a_plain_space( string $variant ): void {
		$this->assertSame(
			$this->hash( 'Red Tea' ),
			$this->hash( 'Red' . $variant . 'Tea' )
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function provide_nbsp(): array {
		return array(
			'entity'    => array( '&nbsp;' ),
			'decimal'   => array( '&#160;' ),
			'hex upper' => array( '&#xA0;' ),
			'hex lower' => array( '&#xa0;' ),
			'codepoint' => array( "\xC2\xA0" ),
		);
	}

	public function test_real_edits_change_the_hash(): void {
		$this->assertNotSame( $this->hash( 'Red Tea' ), $this->hash( 'Green Tea' ) );
		$this->assertNotSame( $this->hash( 'Red Tea' ), $this->hash( 'Red Teas' ) );
	}

	public function test_case_is_significant(): void {
		$this->assertNotSame( $this->hash( 'Red Tea' ), $this->hash( 'red tea' ) );
	}

	public function test_empty_and_whitespace_only_are_equivalent(): void {
		$this->assertSame( $this->hash( '' ), $this->hash( "  \n  " ) );
	}

	public function test_multibyte_text_survives(): void {
		$this->assertSame( $this->hash( 'Rött te' ), $this->hash( '  Rött   te ' ) );
		$this->assertNotSame( $this->hash( 'Rött te' ), $this->hash( 'Rott te' ) );
	}

	public function test_plain_is_the_default_format(): void {
		$this->assertSame( Store::normalize( '  a   b ' ), Store::normalize( '  a   b ', Store::FORMAT_PLAIN ) );
	}
}
