<?php
/**
 * Internal taxonomy admission foundation (code-owned; no public WP filter).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Surface;

/**
 * Single authority deciding which taxonomies carry first-class term identities.
 *
 * Deliberately narrow (ADR-0021): core content taxonomies, the WooCommerce
 * catalog taxonomies when WooCommerce is present, and global product attribute
 * taxonomies — the term *values* under `pa_*`, never the attribute label
 * itself. There is no public admission filter and no auto-admit of every
 * registered taxonomy: an unreviewed taxonomy would start extracting and
 * adopting rows without anyone deciding that it should.
 */
final class AdmittedTaxonomies {

	/**
	 * Core taxonomies, admitted on every site.
	 *
	 * @var list<string>
	 */
	public const CORE_TAXONOMIES = array( 'category', 'post_tag' );

	/**
	 * WooCommerce catalog taxonomies, admitted only when WooCommerce is present.
	 *
	 * @var list<string>
	 */
	public const CATALOG_TAXONOMIES = array( 'product_cat', 'product_tag' );

	/**
	 * Slug shape of a global product attribute taxonomy.
	 */
	private const ATTRIBUTE_TAXONOMY_PATTERN = '/^pa_[a-z0-9_\-]+$/';

	/**
	 * Request-local memo of the admitted set.
	 *
	 * @var list<string>|null
	 */
	private static ?array $memo = null;

	/**
	 * Every admitted taxonomy slug for this site.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		if ( null !== self::$memo ) {
			return self::$memo;
		}

		$admitted = self::CORE_TAXONOMIES;

		if ( self::woocommerce_present() ) {
			$admitted = array_merge( $admitted, self::CATALOG_TAXONOMIES, self::attribute_taxonomies() );
		}

		self::$memo = array_values( array_unique( $admitted ) );

		return self::$memo;
	}

	/**
	 * Whether a taxonomy carries first-class term identities.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 */
	public static function admits( string $taxonomy ): bool {
		return '' !== $taxonomy && in_array( $taxonomy, self::all(), true );
	}

	/**
	 * Clears the request-local memo.
	 */
	public static function reset_for_tests(): void {
		self::$memo = null;
	}

	/**
	 * Whether WooCommerce is loaded in this request.
	 */
	private static function woocommerce_present(): bool {
		return class_exists( 'WooCommerce', false ) || function_exists( 'wc_get_attribute_taxonomies' );
	}

	/**
	 * Public global attribute taxonomies WooCommerce itself reports.
	 *
	 * Registration alone is not enough: a `pa_`-prefixed taxonomy that
	 * WooCommerce does not own is somebody else's data.
	 *
	 * @return list<string>
	 */
	private static function attribute_taxonomies(): array {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return array();
		}

		$slugs = array();

		foreach ( (array) wc_get_attribute_taxonomies() as $attribute ) {
			if ( ! is_object( $attribute ) ) {
				continue;
			}

			$name = (string) ( $attribute->attribute_name ?? '' );
			if ( '' === $name ) {
				continue;
			}

			$slug = function_exists( 'wc_attribute_taxonomy_name' )
				? (string) wc_attribute_taxonomy_name( $name )
				: 'pa_' . $name;

			if ( 1 !== preg_match( self::ATTRIBUTE_TAXONOMY_PATTERN, $slug ) ) {
				continue;
			}

			if ( ! self::is_public_taxonomy( $slug ) ) {
				continue;
			}

			$slugs[] = $slug;
		}

		return $slugs;
	}

	/**
	 * Whether a registered taxonomy is publicly visible.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 */
	private static function is_public_taxonomy( string $taxonomy ): bool {
		if ( ! function_exists( 'get_taxonomy' ) ) {
			return false;
		}

		$object = get_taxonomy( $taxonomy );
		if ( ! is_object( $object ) ) {
			return false;
		}

		if ( isset( $object->publicly_queryable ) && ! (bool) $object->publicly_queryable ) {
			return false;
		}

		return (bool) ( $object->public ?? false );
	}
}
