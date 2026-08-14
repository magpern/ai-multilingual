<?php
/**
 * Editorial localized slug candidate lifecycle (MSEO.1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use WP_Error;
use WP_Post;

/**
 * Sole authority for slug candidate generate/edit/clear and slug_origin transitions.
 */
final class SlugCandidateService {

	/**
	 * Constructs the service.
	 *
	 * @param Store $store Translation store.
	 */
	public function __construct(
		private Store $store
	) {
	}

	/**
	 * Generates a candidate from the translated post title.
	 *
	 * @param WP_Post $post        Source post.
	 * @param int     $language_id Target language id.
	 * @return object|WP_Error Hydrated slug row.
	 */
	public function generate( WP_Post $post, int $language_id ) {
		$title_row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, $language_id, Extractor::FIELD_TITLE );
		$title     = is_object( $title_row ) ? trim( (string) ( $title_row->translated_text ?? '' ) ) : '';
		if ( '' === $title || ( is_object( $title_row ) && Store::STATUS_MISSING === (string) ( $title_row->status ?? '' ) ) ) {
			return new WP_Error( 'aiml_slug_missing_title', __( 'Translated title is required to generate a slug.', 'ai-multilingual' ) );
		}

		$existing = $this->store->get( Store::SOURCE_POST, (int) $post->ID, $language_id, Extractor::FIELD_SLUG );
		$origin   = is_object( $existing ) ? (string) ( $existing->slug_origin ?? '' ) : '';
		if ( 'manual' === $origin ) {
			return new WP_Error( 'aiml_slug_manual_locked', __( 'Cannot generate over a manual slug candidate.', 'ai-multilingual' ) );
		}

		$candidate = $this->normalize_generated( $title );
		if ( '' === $candidate ) {
			return new WP_Error( 'aiml_slug_empty_after_sanitize', __( 'Generated slug is empty.', 'ai-multilingual' ) );
		}

		$saved = $this->store->save_slug_candidate(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => (string) $post->post_type,
				'language_id'     => $language_id,
				'field_key'       => Extractor::FIELD_SLUG,
				'segment_key'     => Extractor::FIELD_SLUG,
				'source_text'     => (string) $post->post_name,
				'translated_text' => $candidate,
				'text_format'     => Store::FORMAT_SLUG,
				'status'          => Store::STATUS_MANUALLY_EDITED,
				'slug_origin'     => 'generated',
				'segment_order'   => 1,
			)
		);

		if ( $saved instanceof WP_Error ) {
			return $saved;
		}

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, $language_id, Extractor::FIELD_SLUG );

		return $row ?? new WP_Error( 'aiml_slug_save_failed', __( 'Failed to load saved slug candidate.', 'ai-multilingual' ) );
	}

	/**
	 * Saves a manual editorial candidate.
	 *
	 * @param WP_Post $post        Source post.
	 * @param int     $language_id Language id.
	 * @param string  $candidate   Operator-entered leaf.
	 * @return object|WP_Error
	 */
	public function save_manual( WP_Post $post, int $language_id, string $candidate ) {
		$normalized = $this->normalize_generated( $candidate );
		if ( '' === $normalized ) {
			return new WP_Error( 'aiml_slug_invalid', __( 'Slug candidate is empty after normalization.', 'ai-multilingual' ) );
		}

		$input = strtolower( trim( $candidate ) );
		if ( $input !== $normalized ) {
			return new WP_Error(
				'aiml_slug_sanitize_drift',
				__( 'Slug must match WordPress sanitize_title output.', 'ai-multilingual' ),
				array( 'normalized' => $normalized )
			);
		}

		if ( $this->contains_forbidden_chars( $normalized ) ) {
			return new WP_Error( 'aiml_slug_invalid_chars', __( 'Slug contains forbidden characters.', 'ai-multilingual' ) );
		}

		$saved = $this->store->save_slug_candidate(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => (string) $post->post_type,
				'language_id'     => $language_id,
				'field_key'       => Extractor::FIELD_SLUG,
				'segment_key'     => Extractor::FIELD_SLUG,
				'source_text'     => (string) $post->post_name,
				'translated_text' => $normalized,
				'text_format'     => Store::FORMAT_SLUG,
				'status'          => Store::STATUS_MANUALLY_EDITED,
				'slug_origin'     => 'manual',
				'segment_order'   => 1,
			)
		);

		if ( $saved instanceof WP_Error ) {
			return $saved;
		}

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, $language_id, Extractor::FIELD_SLUG );

		return $row ?? new WP_Error( 'aiml_slug_save_failed', __( 'Failed to load saved slug candidate.', 'ai-multilingual' ) );
	}

	/**
	 * Clears the editorial candidate (MISSING + slug_origin '').
	 *
	 * @param WP_Post $post        Source post.
	 * @param int     $language_id Language id.
	 * @return object|WP_Error
	 */
	public function clear( WP_Post $post, int $language_id ) {
		$saved = $this->store->save_slug_candidate(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => (string) $post->post_type,
				'language_id'     => $language_id,
				'field_key'       => Extractor::FIELD_SLUG,
				'segment_key'     => Extractor::FIELD_SLUG,
				'source_text'     => (string) $post->post_name,
				'translated_text' => '',
				'text_format'     => Store::FORMAT_SLUG,
				'status'          => Store::STATUS_MISSING,
				'slug_origin'     => '',
				'segment_order'   => 1,
			)
		);

		if ( $saved instanceof WP_Error ) {
			return $saved;
		}

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, $language_id, Extractor::FIELD_SLUG );

		return $row ?? new WP_Error( 'aiml_slug_save_failed', __( 'Failed to load cleared slug candidate.', 'ai-multilingual' ) );
	}

	/**
	 * WordPress-compatible slug normalization for generation.
	 *
	 * @param string $text Raw title or candidate.
	 */
	public function normalize_generated( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( $text );

		return (string) sanitize_title( $text, '', 'save' );
	}

	/**
	 * Helper.
	 *
	 * @param string $slug Candidate leaf.
	 */
	private function contains_forbidden_chars( string $slug ): bool {
		return str_contains( $slug, '/' )
			|| str_contains( $slug, '\\' )
			|| str_contains( $slug, '?' )
			|| str_contains( $slug, '#' )
			|| str_contains( $slug, "\0" )
			|| (bool) preg_match( '/%2F/i', $slug );
	}
}
