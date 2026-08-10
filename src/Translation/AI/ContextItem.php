<?php
/**
 * Allowlisted context item types for TI.2.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

/**
 * Typed context item carried on TranslationContext.
 */
final class ContextItem {

	public const TYPE_OBJECT_TITLE    = 'object_title';
	public const TYPE_SIBLING_TITLE   = 'sibling_title';
	public const TYPE_CATEGORY        = 'category';
	public const TYPE_ATTRIBUTE_NAME  = 'attribute_name';
	public const TYPE_CONTENT_PURPOSE = 'content_purpose';
	public const TYPE_LANGUAGE_NAME   = 'language_name';

	/**
	 * Allowed context item type ids.
	 *
	 * @return list<string>
	 */
	public static function allowed_types(): array {
		return array(
			self::TYPE_OBJECT_TITLE,
			self::TYPE_SIBLING_TITLE,
			self::TYPE_CATEGORY,
			self::TYPE_ATTRIBUTE_NAME,
			self::TYPE_CONTENT_PURPOSE,
			self::TYPE_LANGUAGE_NAME,
		);
	}

	/**
	 * Rejects non-allowlisted item types.
	 *
	 * @param string      $type  Allowlisted type.
	 * @param string      $value Bounded value.
	 * @param string|null $label Optional short label.
	 * @throws \InvalidArgumentException When type is not allowlisted.
	 */
	public function __construct(
		public readonly string $type,
		public readonly string $value,
		public readonly ?string $label = null,
	) {
		if ( ! in_array( $type, self::allowed_types(), true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
			throw new \InvalidArgumentException( 'Unsupported context item type: ' . $type );
		}
	}
}
