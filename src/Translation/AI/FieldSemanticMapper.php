<?php
/**
 * Deterministic FieldSemantic mapping for TI.2.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Translation\Extractor;

/**
 * Maps segment identity to a closed FieldSemantic value.
 */
final class FieldSemanticMapper {

	/**
	 * Plugin identity parser (stateless).
	 *
	 * @var PluginIdentity
	 */
	private PluginIdentity $identity;

	/**
	 * Constructs the mapper.
	 *
	 * @param PluginIdentity|null $identity Optional identity helper.
	 */
	public function __construct( ?PluginIdentity $identity = null ) {
		$this->identity = $identity ?? new PluginIdentity();
	}

	/**
	 * Maps assembled segment + post type to FieldSemantic.
	 *
	 * @param array<string, mixed> $segment   Assembled segment DTO.
	 * @param string               $post_type WP post type (empty when unknown).
	 */
	public function map( array $segment, string $post_type = '' ): string {
		$field_key   = (string) ( $segment['field_key'] ?? '' );
		$segment_key = (string) ( $segment['segment_key'] ?? '' );
		$block_name  = (string) ( $segment['block_name'] ?? '' );

		if ( Extractor::FIELD_TITLE === $field_key || Extractor::FIELD_TITLE === $segment_key ) {
			return 'product' === $post_type ? FieldSemantic::PRODUCT_TITLE : FieldSemantic::HEADING;
		}
		if ( Extractor::FIELD_EXCERPT === $field_key || Extractor::FIELD_EXCERPT === $segment_key ) {
			return 'product' === $post_type ? FieldSemantic::PRODUCT_SHORT_DESCRIPTION : FieldSemantic::BODY;
		}
		if ( Extractor::FIELD_CONTENT === $field_key || Extractor::FIELD_CONTENT === $segment_key ) {
			return 'product' === $post_type ? FieldSemantic::PRODUCT_LONG_DESCRIPTION : FieldSemantic::BODY;
		}

		if ( str_starts_with( $segment_key, 'p:' ) ) {
			$parsed = $this->identity->parse( $segment_key );
			if ( is_array( $parsed ) ) {
				$integration = (string) ( $parsed['integration_id'] ?? '' );
				$field       = (string) ( $parsed['field'] ?? '' );
				$owner_type  = (string) ( $parsed['owner_type'] ?? '' );

				if ( 'rankmath' === $integration ) {
					return match ( $field ) {
						'title' => FieldSemantic::SEO_TITLE,
						'description' => FieldSemantic::SEO_DESCRIPTION,
						'facebook_title', 'twitter_title' => FieldSemantic::SEO_SOCIAL_TITLE,
						'facebook_description', 'twitter_description' => FieldSemantic::SEO_SOCIAL_DESCRIPTION,
						default => FieldSemantic::GENERIC,
					};
				}

				if ( 'woocommerce' === $integration ) {
					if ( in_array( $field, array( 'attribute_name', 'variation_attribute_name' ), true ) ) {
						return FieldSemantic::ATTRIBUTE_LABEL;
					}
					if ( 'name' === $field && in_array( $owner_type, array( 'product_cat', 'product_tag', 'category', 'post_tag' ), true ) ) {
						return FieldSemantic::TERM_NAME;
					}
					if ( 'description' === $field && in_array( $owner_type, array( 'product_cat', 'product_tag', 'category', 'post_tag' ), true ) ) {
						return FieldSemantic::TERM_DESCRIPTION;
					}
					if ( in_array( $field, array( 'label', 'title', 'subject', 'heading' ), true ) ) {
						return FieldSemantic::UI_LABEL;
					}
				}
			}
		}

		if ( str_starts_with( $segment_key, 'm:' ) ) {
			return FieldSemantic::GENERIC;
		}

		if ( '' !== $block_name ) {
			if ( str_contains( $block_name, 'heading' ) ) {
				return FieldSemantic::HEADING;
			}
			return FieldSemantic::BODY;
		}

		return FieldSemantic::GENERIC;
	}

	/**
	 * Maps a TQ.0 corpus field_semantics string through the closed vocabulary.
	 *
	 * @param string $corpus_semantic Corpus metadata value.
	 */
	public function map_corpus( string $corpus_semantic ): string {
		return FieldSemantic::normalize( $corpus_semantic );
	}
}
