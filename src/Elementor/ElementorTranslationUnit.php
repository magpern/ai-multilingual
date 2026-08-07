<?php
/**
 * Elementor translation unit DTO.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

/**
 * Extracted Elementor control unit (immutable value object).
 */
final class ElementorTranslationUnit {

	/**
	 * Builds one translation unit.
	 *
	 * @param string      $segment_key    Hybrid-D key.
	 * @param int         $owner_post_id  Owner post.
	 * @param string      $element_id     Element ID.
	 * @param string      $widget_type    Widget type.
	 * @param string      $control_key    Control key.
	 * @param string      $source_text    Source text.
	 * @param string      $source_hash    Freshness hash.
	 * @param string      $text_format    plain|html.
	 * @param string|null $nested_item_id Repeater `_id` when nested.
	 */
	public function __construct(
		public readonly string $segment_key,
		public readonly int $owner_post_id,
		public readonly string $element_id,
		public readonly string $widget_type,
		public readonly string $control_key,
		public readonly string $source_text,
		public readonly string $source_hash,
		public readonly string $text_format = 'plain',
		public readonly ?string $nested_item_id = null
	) {}

	/**
	 * Array representation for Store/Workspace assembly.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$out = array(
			'segment_key'   => $this->segment_key,
			'owner_post_id' => $this->owner_post_id,
			'element_id'    => $this->element_id,
			'widget_type'   => $this->widget_type,
			'control_key'   => $this->control_key,
			'source_text'   => $this->source_text,
			'source_hash'   => $this->source_hash,
			'text_format'   => $this->text_format,
			'field_key'     => Contract::FIELD_KEY,
		);

		if ( null !== $this->nested_item_id && '' !== $this->nested_item_id ) {
			$out['nested_item_id'] = $this->nested_item_id;
		}

		return $out;
	}
}
