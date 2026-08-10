<?php
/**
 * Closed field-semantic vocabulary for TI.2 bounded context.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

/**
 * Deterministic field roles admitted into TranslationContext.
 */
final class FieldSemantic {

	public const PRODUCT_TITLE             = 'product_title';
	public const PRODUCT_SHORT_DESCRIPTION = 'product_short_description';
	public const PRODUCT_LONG_DESCRIPTION  = 'product_long_description';
	public const SEO_TITLE                 = 'seo_title';
	public const SEO_DESCRIPTION           = 'seo_description';
	public const SEO_SOCIAL_TITLE          = 'seo_social_title';
	public const SEO_SOCIAL_DESCRIPTION    = 'seo_social_description';
	public const TERM_NAME                 = 'term_name';
	public const TERM_DESCRIPTION          = 'term_description';
	public const UI_LABEL                  = 'ui_label';
	public const HEADING                   = 'heading';
	public const BODY                      = 'body';
	public const ATTRIBUTE_LABEL           = 'attribute_label';
	public const MARKETING                 = 'marketing';
	public const GENERIC                   = 'generic';

	/**
	 * Returns the closed vocabulary values.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::PRODUCT_TITLE,
			self::PRODUCT_SHORT_DESCRIPTION,
			self::PRODUCT_LONG_DESCRIPTION,
			self::SEO_TITLE,
			self::SEO_DESCRIPTION,
			self::SEO_SOCIAL_TITLE,
			self::SEO_SOCIAL_DESCRIPTION,
			self::TERM_NAME,
			self::TERM_DESCRIPTION,
			self::UI_LABEL,
			self::HEADING,
			self::BODY,
			self::ATTRIBUTE_LABEL,
			self::MARKETING,
			self::GENERIC,
		);
	}

	/**
	 * Normalizes a string to a closed semantic or generic.
	 *
	 * @param string $value Candidate semantic.
	 */
	public static function normalize( string $value ): string {
		$value = strtolower( trim( $value ) );
		return in_array( $value, self::all(), true ) ? $value : self::GENERIC;
	}
}
