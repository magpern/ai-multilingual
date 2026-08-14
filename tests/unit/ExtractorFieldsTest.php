<?php
/**
 * Extractor field mapping.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit;

use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * The pure half of the extractor. Body classification needs real posts and is
 * covered by the integration suite, where has_blocks() and post meta exist.
 */
final class ExtractorFieldsTest extends TestCase {

	public function test_milestone_fields_include_slug(): void {
		$this->assertSame(
			array( 'post_title', 'post_name', 'post_excerpt', 'post_content' ),
			array_keys( Extractor::fields() )
		);
	}

	public function test_body_is_html_and_slug_is_slug_format(): void {
		$fields = Extractor::fields();

		$this->assertSame( Store::FORMAT_PLAIN, $fields[ Extractor::FIELD_TITLE ]['format'] );
		$this->assertSame( Store::FORMAT_SLUG, $fields[ Extractor::FIELD_SLUG ]['format'] );
		$this->assertSame( Store::FORMAT_PLAIN, $fields[ Extractor::FIELD_EXCERPT ]['format'] );
		$this->assertSame( Store::FORMAT_HTML, $fields[ Extractor::FIELD_CONTENT ]['format'] );
	}

	public function test_field_order_is_stable(): void {
		$fields = Extractor::fields();

		$this->assertSame( 0, $fields[ Extractor::FIELD_TITLE ]['order'] );
		$this->assertSame( 1, $fields[ Extractor::FIELD_SLUG ]['order'] );
		$this->assertSame( 2, $fields[ Extractor::FIELD_EXCERPT ]['order'] );
		$this->assertSame( 3, $fields[ Extractor::FIELD_CONTENT ]['order'] );
	}

	/**
	 * @dataProvider provide_field_names
	 *
	 * @param string      $input    Short name from the CLI or UI.
	 * @param string|null $expected Storage field key, or null when unknown.
	 */
	public function test_short_names_map_to_field_keys( string $input, ?string $expected ): void {
		$this->assertSame( $expected, Extractor::field_key( $input ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string|null}>
	 */
	public function provide_field_names(): array {
		return array(
			'title'       => array( 'title', 'post_title' ),
			'slug'        => array( 'slug', 'post_name' ),
			'excerpt'     => array( 'excerpt', 'post_excerpt' ),
			'content'     => array( 'content', 'post_content' ),
			'uppercase'   => array( 'TITLE', 'post_title' ),
			'padded'      => array( '  content  ', 'post_content' ),
			'storage key' => array( 'post_title', null ),
			'unknown'     => array( 'unknown', null ),
			'empty'       => array( '', null ),
		);
	}

	public function test_body_states_are_distinct(): void {
		$this->assertNotSame( Extractor::BODY_OK, Extractor::BODY_BLOCKS );
		$this->assertNotSame( Extractor::BODY_OK, Extractor::BODY_ELEMENTOR );
		$this->assertNotSame( Extractor::BODY_BLOCKS, Extractor::BODY_ELEMENTOR );
	}
}
