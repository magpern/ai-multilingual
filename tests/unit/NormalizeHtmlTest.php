<?php
/**
 * HTML normalization.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit;

use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * HTML is where a single whitespace-collapsing rule would do real damage.
 * Whitespace between inline elements is rendered, and whitespace inside `<pre>`
 * is the content. So only line endings and the several spellings of a
 * non-breaking space are normalized — and the non-breaking space is
 * canonicalized to its codepoint rather than turned into an ordinary space,
 * because the difference is visible on screen.
 */
final class NormalizeHtmlTest extends TestCase {

	/**
	 * @param string $text Source fragment.
	 */
	private function hash( string $text ): string {
		return Store::source_hash( $text, Store::FORMAT_HTML );
	}

	public function test_line_endings_are_equivalent(): void {
		$this->assertSame(
			$this->hash( "<p>One</p>\n<p>Two</p>" ),
			$this->hash( "<p>One</p>\r\n<p>Two</p>" )
		);
	}

	public function test_outer_whitespace_is_trimmed(): void {
		$this->assertSame( $this->hash( '<p>Tea</p>' ), $this->hash( "\n  <p>Tea</p>  \n" ) );
	}

	/**
	 * Two inline elements separated by a space render differently from two with
	 * none, so this difference has to survive normalization.
	 */
	public function test_whitespace_between_inline_tags_is_significant(): void {
		$this->assertNotSame(
			$this->hash( '<em>Red</em> <strong>Tea</strong>' ),
			$this->hash( '<em>Red</em><strong>Tea</strong>' )
		);
	}

	public function test_internal_whitespace_runs_are_preserved(): void {
		$this->assertNotSame(
			$this->hash( '<p>Red Tea</p>' ),
			$this->hash( '<p>Red   Tea</p>' )
		);
	}

	public function test_preformatted_content_is_untouched(): void {
		$one = "<pre>line one\n    indented</pre>";
		$two = '<pre>line one indented</pre>';

		$this->assertNotSame(
			$this->hash( $one ),
			$this->hash( $two ),
			'Indentation inside <pre> is content and must affect the hash.'
		);
	}

	public function test_code_block_spacing_is_significant(): void {
		$this->assertNotSame(
			$this->hash( '<code>a  b</code>' ),
			$this->hash( '<code>a b</code>' )
		);
	}

	/**
	 * @dataProvider provide_nbsp_spellings
	 *
	 * @param string $variant A non-breaking space written some other way.
	 */
	public function test_nbsp_spellings_are_equivalent( string $variant ): void {
		$this->assertSame(
			$this->hash( '<p>Red' . "\xC2\xA0" . 'Tea</p>' ),
			$this->hash( '<p>Red' . $variant . 'Tea</p>' )
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function provide_nbsp_spellings(): array {
		return array(
			'entity'    => array( '&nbsp;' ),
			'decimal'   => array( '&#160;' ),
			'hex upper' => array( '&#xA0;' ),
			'hex lower' => array( '&#xa0;' ),
		);
	}

	/**
	 * A non-breaking space is not the same as a normal one; collapsing the two
	 * would silently change how the text wraps.
	 */
	public function test_nbsp_is_not_equivalent_to_a_plain_space(): void {
		$this->assertNotSame(
			$this->hash( '<p>Red&nbsp;Tea</p>' ),
			$this->hash( '<p>Red Tea</p>' )
		);
	}

	public function test_markup_changes_are_detected(): void {
		$this->assertNotSame(
			$this->hash( '<p>Tea</p>' ),
			$this->hash( '<p>Tea</p><p>More</p>' )
		);

		$this->assertNotSame(
			$this->hash( '<em>Tea</em>' ),
			$this->hash( '<strong>Tea</strong>' )
		);
	}

	public function test_attribute_changes_are_detected(): void {
		$this->assertNotSame(
			$this->hash( '<a href="/a">Tea</a>' ),
			$this->hash( '<a href="/b">Tea</a>' )
		);
	}

	/**
	 * The same fragment hashes differently as plain and as HTML, because the
	 * rules differ. Format is part of the identity of a hash.
	 */
	public function test_format_changes_the_hash(): void {
		$fragment = '<p>Red   Tea</p>';

		$this->assertNotSame(
			Store::source_hash( $fragment, Store::FORMAT_HTML ),
			Store::source_hash( $fragment, Store::FORMAT_PLAIN )
		);
	}
}
