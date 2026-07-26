<?php
/**
 * Body translatability classification.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\Extractor;

/**
 * Milestone 1 replaces a post body as one whole segment. That is safe for
 * classic content and destructive for anything else, so the extractor has to
 * refuse the rest — and refuse it below the UI, where the CLI cannot get past
 * it either.
 */
final class ExtractorBodyGuardTest extends AimlTestCase {

	public function test_classic_content_is_translatable(): void {
		$post = $this->create_page( 'About Us', '<p>Plain classic body.</p>' );

		$this->assertSame( Extractor::BODY_OK, $this->extractor->body_status( $post ) );
		$this->assertTrue( $this->extractor->can_translate_body( $post ) );
		$this->assertArrayHasKey( Extractor::FIELD_CONTENT, $this->extractor->extract( $post ) );
	}

	public function test_block_content_is_refused(): void {
		$post = $this->create_page(
			'Blocks',
			"<!-- wp:paragraph -->\n<p>Block body.</p>\n<!-- /wp:paragraph -->"
		);

		$this->assertSame( Extractor::BODY_BLOCKS, $this->extractor->body_status( $post ) );
		$this->assertFalse( $this->extractor->can_translate_body( $post ) );
		$this->assertArrayNotHasKey(
			Extractor::FIELD_CONTENT,
			$this->extractor->extract( $post ),
			'Serialized block markup must never be offered as a single editable segment.'
		);
	}

	public function test_elementor_content_is_refused(): void {
		$post = $this->create_page( 'Landing', '<div>Rendered Elementor output.</div>' );

		update_post_meta( $post->ID, '_elementor_data', '[{"id":"a1b2c3","elType":"widget"}]' );

		$this->assertSame( Extractor::BODY_ELEMENTOR, $this->extractor->body_status( $post ) );
		$this->assertFalse( $this->extractor->can_translate_body( $post ) );
		$this->assertArrayNotHasKey( Extractor::FIELD_CONTENT, $this->extractor->extract( $post ) );
	}

	public function test_elementor_edit_mode_alone_is_enough_to_refuse(): void {
		$post = $this->create_page( 'Landing', '<div>Output.</div>' );

		update_post_meta( $post->ID, '_elementor_edit_mode', 'builder' );

		$this->assertSame( Extractor::BODY_ELEMENTOR, $this->extractor->body_status( $post ) );
	}

	/**
	 * A refused body must not cost the page its title.
	 */
	public function test_titles_remain_translatable_on_refused_bodies(): void {
		$post = $this->create_page( 'Landing', '<div>Output.</div>' );
		update_post_meta( $post->ID, '_elementor_data', '[{"id":"a1b2c3"}]' );

		$segments = $this->extractor->extract( $post );

		$this->assertArrayHasKey( Extractor::FIELD_TITLE, $segments );
		$this->assertSame( 'Landing', $segments[ Extractor::FIELD_TITLE ]['source_text'] );
	}

	public function test_refusals_explain_themselves_distinctly(): void {
		$blocks    = Extractor::body_notice( Extractor::BODY_BLOCKS );
		$elementor = Extractor::body_notice( Extractor::BODY_ELEMENTOR );

		$this->assertNotSame( '', $blocks );
		$this->assertNotSame( '', $elementor );
		$this->assertNotSame( $blocks, $elementor );
		$this->assertSame( '', Extractor::body_notice( Extractor::BODY_OK ) );
	}

	public function test_empty_fields_are_not_offered(): void {
		$post = $this->create_page( 'Only a title', '', '' );

		$segments = $this->extractor->extract( $post );

		$this->assertArrayHasKey( Extractor::FIELD_TITLE, $segments );
		$this->assertArrayNotHasKey( Extractor::FIELD_EXCERPT, $segments );
		$this->assertArrayNotHasKey( Extractor::FIELD_CONTENT, $segments );
	}

	public function test_extracted_segments_carry_their_format(): void {
		$post = $this->create_page( 'About Us', '<p>Body.</p>', 'Summary.' );

		$segments = $this->extractor->extract( $post );

		$this->assertSame( 'plain', $segments[ Extractor::FIELD_TITLE ]['text_format'] );
		$this->assertSame( 'plain', $segments[ Extractor::FIELD_EXCERPT ]['text_format'] );
		$this->assertSame( 'html', $segments[ Extractor::FIELD_CONTENT ]['text_format'] );
	}
}
