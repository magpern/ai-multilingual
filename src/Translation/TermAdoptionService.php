<?php
/**
 * Lazy hosted-to-native adoption of taxonomy term translations.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

use AIMultilingual\Surface\AdmittedTaxonomies;
use WP_Error;

/**
 * Orchestrates adoption; Store still owns persistence and locking.
 *
 * Adoption is lazy on purpose (ADR-0021 §3). Migrating a whole catalog up front
 * would be a long unbounded write over rows nobody is editing, so a hosted row
 * only moves to its native identity when something is about to write content to
 * it. Reads, frontend rendering and axis-only mutations never adopt — they go
 * through the resolver and the Store authority lock instead.
 */
final class TermAdoptionService {

	/**
	 * Builds the adoption service.
	 *
	 * @param Store                        $store     Segment store (owns locking and persistence).
	 * @param TermExtractor                $extractor Native term field extractor.
	 * @param TermTranslationResolver|null $resolver Address resolver (sole alias implementation).
	 */
	public function __construct(
		private Store $store,
		private TermExtractor $extractor,
		private ?TermTranslationResolver $resolver = null
	) {
	}

	/**
	 * Moves one logical term field onto its native identity.
	 *
	 * @param int    $term_id       Term id.
	 * @param string $taxonomy      Taxonomy slug.
	 * @param int    $language_id   Language id.
	 * @param string $logical_field name, description, or a Rank Math segment key.
	 * @return object|WP_Error Native row, or WP_Error. `aiml_term_adopt_nothing_to_adopt`
	 *                         means the field has no stored translation at either address.
	 */
	public function adopt_logical_field( int $term_id, string $taxonomy, int $language_id, string $logical_field ) {
		if ( ! AdmittedTaxonomies::admits( $taxonomy ) ) {
			return new WP_Error(
				'aiml_term_not_admitted',
				__( 'This taxonomy does not carry term translations.', 'ai-multilingual' )
			);
		}

		$ref = $this->resolver()->compat_ref( $term_id, $taxonomy, $logical_field, $language_id );

		if ( null === $ref ) {
			return new WP_Error(
				'aiml_term_adopt_unsupported_field',
				__( 'This term field cannot be translated.', 'ai-multilingual' )
			);
		}

		$hosted = $this->hosted_row( $ref );

		if ( null !== $hosted ) {
			return $this->store->adopt_row_to_identity(
				$hosted,
				$ref->to_native_identity(),
				$this->source_text_resolver( $ref )
			);
		}

		$native = $this->store->get(
			Store::SOURCE_TERM,
			$ref->term_id,
			$ref->language_id,
			$ref->native_segment_key
		);

		if ( null !== $native ) {
			return $native;
		}

		return new WP_Error(
			'aiml_term_adopt_nothing_to_adopt',
			__( 'This term field has no stored translation yet.', 'ai-multilingual' )
		);
	}

	/**
	 * Term reference behind a Store address, or null when it is not a term field.
	 *
	 * @param string $source_type Stored source type.
	 * @param int    $source_id   Stored source id.
	 * @param int    $language_id Language id.
	 * @param string $segment_key Stored segment key.
	 */
	public function ref_for_store_address( string $source_type, int $source_id, int $language_id, string $segment_key ): ?TermCompatRef {
		return $this->resolver()->ref_for_store_address( $source_type, $source_id, $segment_key, $language_id );
	}

	/**
	 * Guarantees the native identity is authoritative before a content write.
	 *
	 * @param TermCompatRef $ref Identity reference.
	 * @return true|WP_Error
	 */
	public function ensure_native_for_ref( TermCompatRef $ref ) {
		return $this->ensure_native_before_content_write(
			$ref->term_id,
			$ref->taxonomy,
			$ref->language_id,
			$ref->logical_field
		);
	}

	/**
	 * Adopts a term field and returns the identity a content write must target.
	 *
	 * The single entry point for write paths that address segments by
	 * `(source_type, source_id, segment_key)`: they get either nothing to change
	 * (the common non-term case), or the native identity columns to write
	 * instead of the hosted ones. Adoption has already happened by then, so the
	 * write cannot create a second live copy of the translation.
	 *
	 * @param string $source_type Address source type.
	 * @param int    $source_id   Address source id.
	 * @param int    $language_id Language id.
	 * @param string $segment_key Address segment key.
	 * @return array<string, mixed>|WP_Error Identity overrides, empty when not a term field.
	 */
	public function native_write_identity( string $source_type, int $source_id, int $language_id, string $segment_key ) {
		$ref = $this->ref_for_store_address( $source_type, $source_id, $language_id, $segment_key );

		if ( null === $ref ) {
			return array();
		}

		$adopted = $this->ensure_native_for_ref( $ref );

		if ( $adopted instanceof WP_Error ) {
			return $adopted;
		}

		return $ref->to_native_identity();
	}

	/**
	 * Guarantees the native identity is authoritative before a content write.
	 *
	 * Content writes (manual save, retranslate persist, provider persist) must
	 * never create a second live copy of a translation, so any hosted row is
	 * adopted first. A field with nothing stored anywhere is already safe to
	 * write natively, which is a success, not an error.
	 *
	 * @param int    $term_id       Term id.
	 * @param string $taxonomy      Taxonomy slug.
	 * @param int    $language_id   Language id.
	 * @param string $logical_field name, description, or a Rank Math segment key.
	 * @return true|WP_Error
	 */
	public function ensure_native_before_content_write( int $term_id, string $taxonomy, int $language_id, string $logical_field ) {
		$result = $this->adopt_logical_field( $term_id, $taxonomy, $language_id, $logical_field );

		if ( $result instanceof WP_Error ) {
			return 'aiml_term_adopt_nothing_to_adopt' === $result->get_error_code() ? true : $result;
		}

		return true;
	}

	/**
	 * Hosted compatibility row for a reference, when one still exists.
	 *
	 * @param TermCompatRef $ref Identity reference.
	 */
	private function hosted_row( TermCompatRef $ref ): ?object {
		if ( ! $ref->has_hosted_address() ) {
			return null;
		}

		return $this->store->get(
			$ref->hosted_source_type,
			$ref->hosted_source_id,
			$ref->language_id,
			$ref->hosted_segment_key
		);
	}

	/**
	 * Current-extract lookup so an adopted row cannot claim a stale source.
	 *
	 * @param TermCompatRef $ref Identity reference.
	 * @return callable(string, string): (array<string, string>|null)
	 */
	private function source_text_resolver( TermCompatRef $ref ): callable {
		$segments = $this->extractor->extract( $ref->term_id );

		return static function ( string $field_key, string $segment_key ) use ( $segments ): ?array {
			unset( $field_key );

			$unit = $segments[ $segment_key ] ?? null;

			if ( ! is_array( $unit ) ) {
				return null;
			}

			return array(
				'source_text' => (string) ( $unit['source_text'] ?? '' ),
				'text_format' => (string) ( $unit['text_format'] ?? '' ),
			);
		};
	}

	/**
	 * Lazily built address resolver.
	 */
	private function resolver(): TermTranslationResolver {
		if ( null === $this->resolver ) {
			$this->resolver = new TermTranslationResolver( $this->store );
		}

		return $this->resolver;
	}
}
