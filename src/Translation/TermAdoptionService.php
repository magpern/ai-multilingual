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
