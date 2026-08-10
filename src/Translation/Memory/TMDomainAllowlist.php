<?php
/**
 * Domain allowlist for automatic TM reuse (TI.3).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Memory;

/**
 * Fail-closed public-content allowlist for generation-path TM.
 *
 * Eligibility is evaluated against the *requesting* object subtype
 * (post_type / source_subtype). TM rows themselves do not carry domain.
 */
final class TMDomainAllowlist {

	/**
	 * Public post types and public taxonomy subtypes eligible for automatic TM8/TM9.
	 *
	 * @var list<string>
	 */
	private const ALLOWED_SUBTYPES = array(
		'post',
		'page',
		'product',
		'category',
		'post_tag',
		'product_cat',
		'product_tag',
	);

	/**
	 * Explicitly denied subtypes (fail closed even if later allowlisted by mistake).
	 *
	 * @var list<string>
	 */
	private const DENIED_SUBTYPES = array(
		'shop_order',
		'shop_order_refund',
		'shop_coupon',
		'shop_webhook',
		'customer',
		'user',
		'attachment',
		'revision',
		'nav_menu_item',
		'oembed_cache',
		'custom_css',
		'customize_changeset',
		'wp_global_styles',
		'wp_template',
		'wp_template_part',
		'wp_navigation',
	);

	/**
	 * Whether the requesting object may use automatic TM reuse.
	 *
	 * @param string $source_subtype Post type or source subtype.
	 */
	public static function is_eligible( string $source_subtype ): bool {
		$subtype = strtolower( trim( $source_subtype ) );
		if ( '' === $subtype ) {
			return false;
		}

		if ( in_array( $subtype, self::DENIED_SUBTYPES, true ) ) {
			return false;
		}

		return in_array( $subtype, self::ALLOWED_SUBTYPES, true );
	}
}
