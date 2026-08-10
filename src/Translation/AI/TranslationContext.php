<?php
/**
 * Optional bounded translation context (TI.2 / ADR-0010 amendment).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

/**
 * Provider-agnostic context DTO — not Store identity; not part of source_hash.
 */
final class TranslationContext {

	public const SCHEMA_VERSION = '2';

	public const MAX_TOTAL_CHARS     = 1200;
	public const MAX_OBJECT_TITLE    = 200;
	public const MAX_ITEM_VALUE      = 200;
	public const MAX_TM_EXAMPLE_VALUE = 400;
	public const MAX_TM_EXAMPLES     = 3;
	public const MAX_ITEMS           = 8;
	public const MAX_CATEGORIES      = 3;
	public const MAX_ATTRIBUTE_NAMES = 5;

	/**
	 * Constructs the context DTO.
	 *
	 * @param string $field_semantic Closed FieldSemantic value.
	 * @param string $object_type    Bounded object type.
	 * @param string $object_title   Capped title.
	 * @param array  $items          Allowlisted ContextItem list.
	 * @param array  $provenance     Lightweight provenance map.
	 * @param string $schema_version Context schema version.
	 */
	public function __construct(
		public readonly string $field_semantic,
		public readonly string $object_type = '',
		public readonly string $object_title = '',
		public readonly array $items = array(),
		public readonly array $provenance = array(
			'item_types' => array(),
			'truncated'  => false,
			'char_count' => 0,
		),
		public readonly string $schema_version = self::SCHEMA_VERSION,
	) {
	}

	/**
	 * Content purpose hint for SEO-like fields.
	 */
	public function content_purpose(): string {
		return match ( $this->field_semantic ) {
			FieldSemantic::SEO_TITLE, FieldSemantic::SEO_DESCRIPTION => 'search_snippet',
			FieldSemantic::SEO_SOCIAL_TITLE, FieldSemantic::SEO_SOCIAL_DESCRIPTION => 'social_snippet',
			default => '',
		};
	}
}
