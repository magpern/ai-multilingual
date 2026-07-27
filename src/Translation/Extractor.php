<?php
/**
 * Source segment extraction.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

use AIMultilingual\Settings;
use WP_Post;

/**
 * Turns a canonical object into the set of source segments that may be
 * translated, and decides which of them Milestone 1 is allowed to touch.
 *
 * The body guard is the important part. A Gutenberg body is serialized block
 * markup: HTML interleaved with `<!-- wp:… -->` delimiters that the editor
 * parses back into blocks. Replacing that whole string with translated prose
 * would destroy the delimiters and silently break the editor for that post,
 * and the damage would only surface the next time someone opened it. Elementor
 * is worse still — its body lives in `_elementor_data` postmeta and
 * `post_content` is a rendered artifact, so translating the artifact achieves
 * nothing.
 *
 * So Milestone 1 refuses both, here rather than in the UI, which means the
 * WP-CLI path cannot bypass the check either. Titles and excerpts are
 * unaffected: they are plain fields regardless of how the body is stored, so an
 * Elementor landing page can still be given a translated title today.
 *
 * Block-level segmentation arrives in Milestone 2 and Elementor in Milestone 6;
 * both extend this class rather than replacing it.
 */
final class Extractor {

	/**
	 * Builds the extractor.
	 *
	 * @param Settings|null       $settings        Plugin settings.
	 * @param BlockExtractor|null $block_extractor Block segment extractor.
	 */
	public function __construct(
		private ?Settings $settings = null,
		private ?BlockExtractor $block_extractor = null,
	) {
	}

	/**
	 * Field keys.
	 */
	public const FIELD_TITLE   = 'post_title';
	public const FIELD_EXCERPT = 'post_excerpt';
	public const FIELD_CONTENT = 'post_content';

	/**
	 * Reasons a body cannot be translated in this milestone.
	 */
	public const BODY_OK        = 'ok';
	public const BODY_BLOCKS    = 'blocks';
	public const BODY_ELEMENTOR = 'elementor';

	/**
	 * Fields the minimal editor exposes, in display order.
	 *
	 * @return array<string, array{label_key: string, format: string, order: int}>
	 */
	public static function fields(): array {
		return array(
			self::FIELD_TITLE   => array(
				'label_key' => 'title',
				'format'    => Store::FORMAT_PLAIN,
				'order'     => 0,
			),
			self::FIELD_EXCERPT => array(
				'label_key' => 'excerpt',
				'format'    => Store::FORMAT_PLAIN,
				'order'     => 1,
			),
			self::FIELD_CONTENT => array(
				'label_key' => 'content',
				'format'    => Store::FORMAT_HTML,
				'order'     => 2,
			),
		);
	}

	/**
	 * Maps a short CLI/UI field name to its storage field key.
	 *
	 * @param string $name One of title, excerpt, content.
	 */
	public static function field_key( string $name ): ?string {
		$map = array(
			'title'   => self::FIELD_TITLE,
			'excerpt' => self::FIELD_EXCERPT,
			'content' => self::FIELD_CONTENT,
		);

		return $map[ strtolower( trim( $name ) ) ] ?? null;
	}

	/**
	 * Classifies how a post's body is stored.
	 *
	 * Pure apart from the two WordPress lookups it needs; both are cheap and
	 * cached by core.
	 *
	 * @param WP_Post $post Canonical post.
	 * @return string One of the BODY_* constants.
	 */
	public function body_status( WP_Post $post ): string {
		if ( '' !== (string) get_post_meta( $post->ID, '_elementor_data', true ) ) {
			return self::BODY_ELEMENTOR;
		}

		if ( '' !== (string) get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) {
			return self::BODY_ELEMENTOR;
		}

		if ( has_blocks( $post->post_content ) ) {
			return self::BODY_BLOCKS;
		}

		return self::BODY_OK;
	}

	/**
	 * Whether this milestone can translate the post's body as one segment.
	 *
	 * @param WP_Post $post Canonical post.
	 */
	public function can_translate_body( WP_Post $post ): bool {
		return self::BODY_OK === $this->body_status( $post );
	}

	/**
	 * Human-readable explanation for a refused body, or empty when allowed.
	 *
	 * @param string $status One of the BODY_* constants.
	 */
	public static function body_notice( string $status ): string {
		switch ( $status ) {
			case self::BODY_BLOCKS:
				return __( 'This page is built with the block editor. Block-level translation arrives in a later milestone; translating the body as one field would corrupt the block markup, so it is disabled here. The title and excerpt can still be translated.', 'ai-multilingual' );

			case self::BODY_ELEMENTOR:
				return __( 'This page is built with Elementor, which stores its content outside the post body. Elementor translation arrives in a later milestone. The title and excerpt can still be translated.', 'ai-multilingual' );

			default:
				return '';
		}
	}

	/**
	 * Extracts the translatable source segments of a post.
	 *
	 * The body is only included when it can be safely replaced wholesale.
	 * Empty fields are skipped: there is nothing to translate and a row would
	 * only show up as spurious missing work.
	 *
	 * @param WP_Post $post Canonical post.
	 * @return array<string, array{field_key: string, segment_key: string, source_text: string, text_format: string, segment_order: int, segment_kind: string}>
	 */
	public function extract( WP_Post $post ): array {
		$segments = array();

		foreach ( self::fields() as $field_key => $spec ) {
			if ( self::FIELD_CONTENT === $field_key ) {
				if ( $this->should_extract_blocks( $post ) ) {
					$segments = array_merge( $segments, $this->block_extractor->extract_post( $post ) );
					continue;
				}

				if ( ! $this->can_translate_body( $post ) ) {
					continue;
				}
			}

			$source = (string) $post->{$field_key};

			if ( '' === trim( $source ) ) {
				continue;
			}

			$segments[ $field_key ] = array(
				'field_key'     => $field_key,
				'segment_key'   => $field_key,
				'source_text'   => $source,
				'text_format'   => $spec['format'],
				'segment_order' => $spec['order'],
				'segment_kind'  => Store::KIND_FIELD,
			);
		}

		return $segments;
	}

	/**
	 * Whether block-level extraction should run for this post.
	 *
	 * @param WP_Post $post Canonical post.
	 */
	private function should_extract_blocks( WP_Post $post ): bool {
		if ( null === $this->settings || null === $this->block_extractor ) {
			return false;
		}

		if ( ! $this->settings->block_extraction_enabled() ) {
			return false;
		}

		return self::BODY_BLOCKS === $this->body_status( $post );
	}
}
