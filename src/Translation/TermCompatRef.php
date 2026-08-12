<?php
/**
 * Term identity reference spanning native and hosted compatibility rows.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

/**
 * Immutable description of one logical term field in one language.
 *
 * Carries both addresses a term translation can currently live at: the native
 * TERM_ID identity and, while it still exists, the hosted compatibility row on
 * a shop or posts page. Store needs both to take the authority lock in the
 * frozen order, so they travel together rather than being re-derived at each
 * call site.
 */
final class TermCompatRef {

	/**
	 * Builds a term compatibility reference.
	 *
	 * @param int    $term_id            Term id.
	 * @param string $taxonomy           Taxonomy slug (stored as source_subtype).
	 * @param int    $language_id        Language id.
	 * @param string $logical_field      name, description, or a Rank Math segment key.
	 * @param string $native_field_key   field_key under the native identity.
	 * @param string $native_segment_key segment_key under the native identity.
	 * @param string $hosted_source_type Hosting source type ('' when not hosted).
	 * @param int    $hosted_source_id   Hosting object id (0 when not hosted).
	 * @param string $hosted_field_key   field_key on the hosted row.
	 * @param string $hosted_segment_key segment_key on the hosted row ('' when not hosted).
	 */
	public function __construct(
		public readonly int $term_id,
		public readonly string $taxonomy,
		public readonly int $language_id,
		public readonly string $logical_field,
		public readonly string $native_field_key,
		public readonly string $native_segment_key,
		public readonly string $hosted_source_type = '',
		public readonly int $hosted_source_id = 0,
		public readonly string $hosted_field_key = '',
		public readonly string $hosted_segment_key = ''
	) {
	}

	/**
	 * Whether a hosted compatibility address is known for this field.
	 */
	public function has_hosted_address(): bool {
		return $this->hosted_source_id > 0 && '' !== $this->hosted_segment_key;
	}

	/**
	 * Store authority-lock reference.
	 *
	 * @return array<string, mixed>
	 */
	public function to_store_ref(): array {
		return array(
			'term_id'            => $this->term_id,
			'taxonomy'           => $this->taxonomy,
			'language_id'        => $this->language_id,
			'native_field_key'   => $this->native_field_key,
			'native_segment_key' => $this->native_segment_key,
			'hosted_source_type' => $this->hosted_source_type,
			'hosted_source_id'   => $this->hosted_source_id,
			'hosted_field_key'   => $this->hosted_field_key,
			'hosted_segment_key' => $this->hosted_segment_key,
		);
	}

	/**
	 * Native identity map for {@see Store::adopt_row_to_identity()}.
	 *
	 * @return array<string, mixed>
	 */
	public function to_native_identity(): array {
		return array(
			'source_type'    => Store::SOURCE_TERM,
			'source_id'      => $this->term_id,
			'source_subtype' => $this->taxonomy,
			'field_key'      => $this->native_field_key,
			'segment_key'    => $this->native_segment_key,
		);
	}
}
