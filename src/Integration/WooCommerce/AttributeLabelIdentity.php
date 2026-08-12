<?php
/**
 * TSC.3 WooCommerce global attribute-label identity helpers.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Integration\WooCommerce;

use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Translation\Store;

/**
 * Canonical / compatibility key grammar for Woo attribute labels (TSC.3).
 */
final class AttributeLabelIdentity {

	public const OWNER_ATTRIBUTE = 'attribute';

	public const FIELD_LABEL = 'label';

	/**
	 * Canonical segment key for a Woo global attribute definition.
	 *
	 * @param PluginIdentity $identity Serializer.
	 * @param int            $attribute_id Woo attribute_id.
	 */
	public static function canonical_key( PluginIdentity $identity, int $attribute_id ): string {
		return $identity->build(
			WooCommerceIntegration::ID,
			self::OWNER_ATTRIBUTE,
			(string) $attribute_id,
			self::FIELD_LABEL
		);
	}

	/**
	 * Whether a segment key is the canonical global attribute-label family.
	 *
	 * @param string              $segment_key Key.
	 * @param PluginIdentity|null $identity    Optional parser.
	 */
	public static function is_canonical_key( string $segment_key, ?PluginIdentity $identity = null ): bool {
		$identity ??= new PluginIdentity();
		$parsed     = $identity->parse( $segment_key );
		if ( null === $parsed ) {
			return false;
		}
		if ( WooCommerceIntegration::ID !== $parsed['integration_id'] ) {
			return false;
		}
		if ( self::OWNER_ATTRIBUTE !== $parsed['owner_type'] ) {
			return false;
		}
		if ( self::FIELD_LABEL !== $parsed['field'] ) {
			return false;
		}
		if ( array() !== $parsed['nested'] ) {
			return false;
		}
		$owner_id = $parsed['owner_id'];

		return '' !== $owner_id && 1 === preg_match( '/^[1-9][0-9]*$/', $owner_id );
	}

	/**
	 * Attribute id from a canonical key, or 0.
	 *
	 * @param string              $segment_key Key.
	 * @param PluginIdentity|null $identity    Parser.
	 */
	public static function attribute_id_from_canonical( string $segment_key, ?PluginIdentity $identity = null ): int {
		if ( ! self::is_canonical_key( $segment_key, $identity ) ) {
			return 0;
		}
		$identity ??= new PluginIdentity();
		$parsed     = $identity->parse( $segment_key );

		return null === $parsed ? 0 : (int) $parsed['owner_id'];
	}

	/**
	 * Whether a product-hosted P5/P7 key is taxonomy-backed compatibility (non-writable).
	 *
	 * @param string              $segment_key Key.
	 * @param PluginIdentity|null $identity    Parser.
	 */
	public static function is_taxonomy_compat_product_key( string $segment_key, ?PluginIdentity $identity = null ): bool {
		$identity ??= new PluginIdentity();
		$parsed     = $identity->parse( $segment_key );
		if ( null === $parsed || WooCommerceIntegration::ID !== $parsed['integration_id'] ) {
			return false;
		}
		if ( 'product' !== $parsed['owner_type'] ) {
			return false;
		}
		$field = $parsed['field'];
		if (
			WooCommerceIntegration::FIELD_ATTRIBUTE_NAME !== $field
			&& WooCommerceIntegration::FIELD_VARIATION_ATTRIBUTE_NAME !== $field
		) {
			return false;
		}
		$slug = $parsed['nested'][0] ?? '';
		if ( '' === $slug ) {
			return false;
		}

		return self::slug_is_global_product_attribute( $slug );
	}

	/**
	 * Whether a slug/name refers to a registered Woo global product attribute taxonomy.
	 *
	 * @param string $slug Attribute slug or pa_* taxonomy name.
	 */
	public static function slug_is_global_product_attribute( string $slug ): bool {
		$name = $slug;
		if ( function_exists( 'taxonomy_is_product_attribute' ) && taxonomy_is_product_attribute( $name ) ) {
			return true;
		}
		if ( function_exists( 'wc_attribute_taxonomy_name' ) && ! str_starts_with( $name, 'pa_' ) ) {
			$tax = wc_attribute_taxonomy_name( $name );
			if ( function_exists( 'taxonomy_is_product_attribute' ) && taxonomy_is_product_attribute( $tax ) ) {
				return true;
			}
		}

		// Unit-test / offline: pa_* token convention when Woo helpers absent.
		return str_starts_with( $name, 'pa_' ) || ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( 'pa_' . $name ) );
	}

	/**
	 * Retain-set: existing taxonomy-backed P5/P7 keys on a product host.
	 *
	 * @param Store $store     Store.
	 * @param int   $product_id Product post id.
	 * @return list<string>
	 */
	public static function retain_taxonomy_compat_keys( Store $store, int $product_id ): array {
		if ( $product_id <= 0 ) {
			return array();
		}
		$out = array();
		foreach ( $store->distinct_segment_keys_for_source( Store::SOURCE_POST, $product_id ) as $key ) {
			if ( self::is_taxonomy_compat_product_key( $key ) ) {
				$out[] = $key;
			}
		}

		return $out;
	}

	/**
	 * Predicate for Store::rehost_segments — canonical attribute-label keys only.
	 *
	 * @param string $segment_key Key.
	 */
	public static function rehost_predicate( string $segment_key ): bool {
		return self::is_canonical_key( $segment_key );
	}
}
