<?php
/**
 * Taxonomy term source segment extraction.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

use AIMultilingual\Surface\AdmittedTaxonomies;
use AIMultilingual\Surface\Meta\RegisteredMetaExtractor;
use WP_Term;

/**
 * Turns an admitted taxonomy term into its translatable source segments.
 *
 * TSC.1 admits exactly two native fields, `name` and `description`. The slug is
 * deliberately absent: translating it would change permalinks, which is a
 * routing decision rather than a content one and belongs to its own milestone.
 *
 * Empty fields are skipped so an untouched description never shows up as
 * outstanding translation work. TSC.2 may merge native registered `m:` meta.
 */
final class TermExtractor {

	/**
	 * Native field keys (field_key and segment_key are identical for terms).
	 */
	public const FIELD_NAME        = 'name';
	public const FIELD_DESCRIPTION = 'description';

	/**
	 * Construct the term extractor.
	 *
	 * @param RegisteredMetaExtractor|null $registered_meta Optional registered-meta extractor.
	 */
	public function __construct(
		private ?RegisteredMetaExtractor $registered_meta = null,
	) {
	}
	/**
	 * Native fields in display order.
	 *
	 * @return array<string, array{format: string, order: int}>
	 */
	public static function fields(): array {
		return array(
			self::FIELD_NAME        => array(
				'format' => Store::FORMAT_PLAIN,
				'order'  => 0,
			),
			self::FIELD_DESCRIPTION => array(
				'format' => Store::FORMAT_HTML,
				'order'  => 1,
			),
		);
	}

	/**
	 * Extracts the translatable source segments of a term.
	 *
	 * @param int $term_id Term id.
	 * @return array<string, array{field_key: string, segment_key: string, source_text: string, text_format: string, segment_order: int, segment_kind: string}>
	 */
	public function extract( int $term_id ): array {
		$term = $this->term( $term_id );

		if ( null === $term ) {
			return array();
		}

		$segments = array();

		foreach ( self::fields() as $field_key => $spec ) {
			$source = (string) ( $term->{$field_key} ?? '' );

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

		if ( null !== $this->registered_meta ) {
			foreach ( $this->registered_meta->extract_for_term( $term_id, (string) $term->taxonomy ) as $key => $unit ) {
				$segments[ $key ] = $unit;
			}
		}

		return $segments;
	}

	/**
	 * Loads an admitted term, or null when missing or forbidden.
	 *
	 * @param int $term_id Term id.
	 */
	private function term( int $term_id ): ?WP_Term {
		if ( $term_id <= 0 || ! function_exists( 'get_term' ) ) {
			return null;
		}

		$term = get_term( $term_id );

		if ( ! $term instanceof WP_Term ) {
			return null;
		}

		return AdmittedTaxonomies::admits( (string) $term->taxonomy ) ? $term : null;
	}
}
