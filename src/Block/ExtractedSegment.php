<?php
/**
 * Strategy F extracted block segment.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Immutable segment produced by block extraction.
 */
final class ExtractedSegment {

	/**
	 * Builds an extracted block segment.
	 *
	 * @param string               $segment_key       Segment identity (`b:<uuid>:<field>`).
	 * @param string               $field             Adapter field identifier.
	 * @param string               $block_name        Block type name.
	 * @param string               $uuid              Block UUID.
	 * @param string               $source_text       Canonical adapter source text.
	 * @param string               $normalized_source Normalized source for hashing.
	 * @param string               $source_hash       Hash of normalized source.
	 * @param string               $text_format       Source text format.
	 * @param int                  $segment_order     Document order index.
	 * @param array<string, mixed> $metadata          Adapter metadata for later milestones.
	 */
	public function __construct(
		public readonly string $segment_key,
		public readonly string $field,
		public readonly string $block_name,
		public readonly string $uuid,
		public readonly string $source_text,
		public readonly string $normalized_source,
		public readonly string $source_hash,
		public readonly string $text_format,
		public readonly int $segment_order,
		public readonly array $metadata = array(),
	) {
	}

	/**
	 * Converts the segment into the store reconciliation shape.
	 *
	 * @return array<string, mixed>
	 */
	public function to_sync_segment(): array {
		return array(
			'field_key'         => \AIMultilingual\Translation\Extractor::FIELD_CONTENT,
			'segment_key'       => $this->segment_key,
			'field'             => $this->field,
			'block_name'        => $this->block_name,
			'uuid'              => $this->uuid,
			'source_text'       => $this->source_text,
			'normalized_source' => $this->normalized_source,
			'source_hash'       => $this->source_hash,
			'text_format'       => $this->text_format,
			'segment_order'     => $this->segment_order,
			'segment_kind'      => \AIMultilingual\Translation\Store::KIND_BLOCK,
			'metadata'          => $this->metadata,
		);
	}
}
