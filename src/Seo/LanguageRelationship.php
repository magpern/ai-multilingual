<?php
/**
 * One language relationship for a document/request.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Seo;

/**
 * Immutable SB11 relationship record.
 */
final class LanguageRelationship {

	/**
	 * Language code (e.g. en, sv).
	 *
	 * @var string
	 */
	public string $language_code;

	/**
	 * BCP47 hreflang value.
	 *
	 * @var string
	 */
	public string $hreflang;

	/**
	 * Absolute SA7 URL.
	 *
	 * @var string
	 */
	public string $url;

	/**
	 * Whether this is the site default language.
	 *
	 * @var bool
	 */
	public bool $is_default;

	/**
	 * Whether this matches the current LanguageContext language.
	 *
	 * @var bool
	 */
	public bool $is_current;

	/**
	 * Creates one relationship record.
	 *
	 * @param string $language_code Language code.
	 * @param string $hreflang      BCP47 tag.
	 * @param string $url           Absolute URL.
	 * @param bool   $is_default    Default language flag.
	 * @param bool   $is_current    Current language flag.
	 */
	public function __construct(
		string $language_code,
		string $hreflang,
		string $url,
		bool $is_default,
		bool $is_current
	) {
		$this->language_code = $language_code;
		$this->hreflang      = $hreflang;
		$this->url           = $url;
		$this->is_default    = $is_default;
		$this->is_current    = $is_current;
	}
}
