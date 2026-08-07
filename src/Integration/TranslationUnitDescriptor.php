<?php
/**
 * Translation unit descriptor for Integration API v1.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration;

/**
 * One visitor-facing translation unit emitted by a plugin integration.
 */
final class TranslationUnitDescriptor {

	/**
	 * Builds a translation unit descriptor.
	 *
	 * @param string $segment_key     Framework-built `p:` key.
	 * @param string $source_text     Source value.
	 * @param string $source_hash     Freshness hash.
	 * @param string $text_format     Store format value.
	 * @param string $ownership_class Contract ownership vocabulary.
	 * @param string $owner_type      Owner type token.
	 * @param string $owner_id        Owner identifier.
	 * @param string $field           Field identifier.
	 * @param string $field_label     Human-readable label.
	 * @param string $integration_id  Integration ID.
	 * @param string $parent_context  Optional parent context label.
	 */
	public function __construct(
		public readonly string $segment_key,
		public readonly string $source_text,
		public readonly string $source_hash,
		public readonly string $text_format,
		public readonly string $ownership_class,
		public readonly string $owner_type,
		public readonly string $owner_id,
		public readonly string $field,
		public readonly string $field_label,
		public readonly string $integration_id,
		public readonly string $parent_context = '',
	) {
	}

	/**
	 * Shape compatible with Extractor / SegmentAssembler segment arrays.
	 *
	 * @param int $segment_order Stable ordering hint for Workspace.
	 * @return array<string, mixed>
	 */
	public function to_segment_array( int $segment_order ): array {
		return array(
			'field_key'       => Contract::FIELD_KEY,
			'segment_key'     => $this->segment_key,
			'source_text'     => $this->source_text,
			'source_hash'     => $this->source_hash,
			'text_format'     => $this->text_format,
			'segment_order'   => $segment_order,
			'segment_kind'    => 'field',
			'surface'         => 'plugin_integration',
			'integration_id'  => $this->integration_id,
			'owner_type'      => $this->owner_type,
			'owner_id'        => $this->owner_id,
			'field_label'     => $this->field_label,
			'ownership_class' => $this->ownership_class,
			'parent_context'  => $this->parent_context,
			'block_name'      => '',
		);
	}
}
