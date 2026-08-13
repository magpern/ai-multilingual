<?php
/**
 * TSC.4 block/field pair authority and structural rejection tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockRenderLogger;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Translation\BlockRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Malformed block/field pairs and structural bypass rejection (AC21/AC22).
 *
 * @covers \AIMultilingual\Translation\BlockRenderer
 */
final class Tsc4BlockFieldPairAuthorityTest extends TestCase {

	private const UUID_P = '11111111-1111-4111-8111-111111111111';
	private const UUID_I = '22222222-2222-4222-8222-222222222222';
	private const UUID_D = '33333333-3333-4333-8333-333333333333';
	private const UUID_Q = '44444444-4444-4444-8444-444444444444';
	private const UUID_F = '55555555-5555-4555-8555-555555555555';

	private BlockRenderer $renderer;

	protected function setUp(): void {
		parent::setUp();
		$this->renderer = new BlockRenderer( new AdapterRegistry(), new BlockRenderLogger() );
	}

	public function test_paragraph_caption_pair_is_not_applied(): void {
		$blocks = array( $this->paragraph( 'Body EN' ) );
		$before = $blocks[0]['innerHTML'];

		$this->renderer->render(
			$blocks,
			array( SegmentKey::build( self::UUID_P, Contract::FIELD_CAPTION ) => 'Should not apply' )
		);

		$this->assertSame( $before, $blocks[0]['innerHTML'] );
	}

	public function test_image_summary_pair_is_not_applied(): void {
		$blocks = array( $this->image( 'Caption EN' ) );
		$before = $blocks[0]['innerHTML'];

		$this->renderer->render(
			$blocks,
			array( SegmentKey::build( self::UUID_I, Contract::FIELD_SUMMARY ) => 'Should not apply' )
		);

		$this->assertSame( $before, $blocks[0]['innerHTML'] );
	}

	public function test_details_file_name_pair_is_not_applied(): void {
		$blocks = array( $this->details( 'Summary EN' ) );
		$before = $blocks[0]['innerHTML'];

		$this->renderer->render(
			$blocks,
			array( SegmentKey::build( self::UUID_D, Contract::FIELD_FILE_NAME ) => 'Should not apply' )
		);

		$this->assertSame( $before, $blocks[0]['innerHTML'] );
	}

	public function test_image_caption_pair_is_accepted(): void {
		$blocks = array( $this->image( 'Caption EN' ) );

		$this->renderer->render(
			$blocks,
			array( SegmentKey::build( self::UUID_I, Contract::FIELD_CAPTION ) => 'Bildtext SV' )
		);

		$this->assertStringContainsString( 'Bildtext SV', (string) $blocks[0]['innerHTML'] );
	}

	public function test_quote_citation_pair_is_accepted(): void {
		$blocks = array( $this->quote( 'Author EN' ) );

		$this->renderer->render(
			$blocks,
			array( SegmentKey::build( self::UUID_Q, Contract::FIELD_CITATION ) => 'Författare SV' )
		);

		$this->assertStringContainsString( 'Författare SV', (string) $blocks[0]['innerHTML'] );
	}

	public function test_file_name_and_download_button_pairs_are_accepted(): void {
		$blocks = array( $this->file( 'Report.pdf', 'Download' ) );

		$this->renderer->render(
			$blocks,
			array(
				SegmentKey::build( self::UUID_F, Contract::FIELD_FILE_NAME )             => 'Rapport.pdf',
				SegmentKey::build( self::UUID_F, Contract::FIELD_DOWNLOAD_BUTTON_TEXT ) => 'Ladda ner',
			)
		);

		$this->assertStringContainsString( 'Rapport.pdf', (string) $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'Ladda ner', (string) $blocks[0]['innerHTML'] );
	}

	public function test_structural_href_bypass_is_rejected_to_source(): void {
		$href   = 'https://example.com/safe';
		$blocks = array(
			array(
				'blockName'    => 'core/button',
				'attrs'        => array(
					Contract::ATTR_NAME => self::UUID_P,
					'url'               => $href,
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '<div class="wp-block-button"><a class="wp-block-button__link" href="' . $href . '">Go</a></div>',
				'innerContent' => array(
					'<div class="wp-block-button"><a class="wp-block-button__link" href="' . $href . '">Go</a></div>',
				),
			),
		);
		$before = $blocks[0]['innerHTML'];

		$result = $this->renderer->render(
			$blocks,
			array(
				SegmentKey::build(
					self::UUID_P,
					Contract::FIELD_CONTENT
				) => '<a href="https://evil.test">Forged</a>',
			)
		);

		$this->assertSame( $before, $blocks[0]['innerHTML'] );
		$this->assertFalse( $result->changed );
		$this->assertTrue( $this->has_event( $result, BlockRenderLogger::EVENT_STRUCTURAL_REJECTED ) );
	}

	/**
	 * @param string $text Body text.
	 * @return array<string, mixed>
	 */
	private function paragraph( string $text ): array {
		return array(
			'blockName'    => 'core/paragraph',
			'attrs'        => array( Contract::ATTR_NAME => self::UUID_P ),
			'innerBlocks'  => array(),
			'innerHTML'    => '<p>' . $text . '</p>',
			'innerContent' => array( '<p>' . $text . '</p>' ),
		);
	}

	/**
	 * @param string $caption Caption text.
	 * @return array<string, mixed>
	 */
	private function image( string $caption ): array {
		return array(
			'blockName'    => 'core/image',
			'attrs'        => array( Contract::ATTR_NAME => self::UUID_I ),
			'innerBlocks'  => array(),
			'innerHTML'    => '<figure><figcaption>' . $caption . '</figcaption></figure>',
			'innerContent' => array( '<figure><figcaption>' . $caption . '</figcaption></figure>' ),
		);
	}

	/**
	 * @param string $summary Summary text.
	 * @return array<string, mixed>
	 */
	private function details( string $summary ): array {
		return array(
			'blockName'    => 'core/details',
			'attrs'        => array( Contract::ATTR_NAME => self::UUID_D ),
			'innerBlocks'  => array(),
			'innerHTML'    => '<details><summary>' . $summary . '</summary></details>',
			'innerContent' => array( '<details><summary>' . $summary . '</summary></details>' ),
		);
	}

	/**
	 * @param string $citation Citation text.
	 * @return array<string, mixed>
	 */
	private function quote( string $citation ): array {
		return array(
			'blockName'    => 'core/quote',
			'attrs'        => array( Contract::ATTR_NAME => self::UUID_Q ),
			'innerBlocks'  => array(),
			'innerHTML'    => '<blockquote class="wp-block-quote"><cite>' . $citation . '</cite></blockquote>',
			'innerContent' => array( '<blockquote class="wp-block-quote"><cite>' . $citation . '</cite></blockquote>' ),
		);
	}

	/**
	 * @param string $file_name File label.
	 * @param string $button    Download label.
	 * @return array<string, mixed>
	 */
	private function file( string $file_name, string $button ): array {
		return array(
			'blockName'    => 'core/file',
			'attrs'        => array( Contract::ATTR_NAME => self::UUID_F ),
			'innerBlocks'  => array(),
			'innerHTML'    => '<div class="wp-block-file"><a href="https://example.com/file.pdf">' . $file_name . '</a><a class="wp-block-file__button" href="https://example.com/file.pdf" download>' . $button . '</a></div>',
			'innerContent' => array(
				'<div class="wp-block-file"><a href="https://example.com/file.pdf">' . $file_name . '</a><a class="wp-block-file__button" href="https://example.com/file.pdf" download>' . $button . '</a></div>',
			),
		);
	}

	/**
	 * @param \AIMultilingual\Translation\RenderResult $result Render result.
	 * @param string                                   $event  Event name.
	 */
	private function has_event( \AIMultilingual\Translation\RenderResult $result, string $event ): bool {
		foreach ( $result->events as $entry ) {
			if ( is_array( $entry ) && ( $entry['event'] ?? '' ) === $event ) {
				return true;
			}
		}

		return false;
	}
}
