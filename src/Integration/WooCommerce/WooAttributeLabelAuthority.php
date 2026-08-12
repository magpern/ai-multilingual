<?php
/**
 * Woo global attribute-label segment authority facts (TSC.3).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Integration\WooCommerce;

use AIMultilingual\Integration\IntegrationSegmentAuthority;

/**
 * Facts for p:woocommerce:attribute:{id}:label — manage_product_terms; attribute exists.
 */
final class WooAttributeLabelAuthority implements IntegrationSegmentAuthority {

	public const CAPABILITY = 'manage_product_terms';

	/**
	 * {@inheritdoc}
	 *
	 * @param object $row Store row.
	 */
	public function applies( object $row ): bool {
		return AttributeLabelIdentity::is_canonical_key( (string) ( $row->segment_key ?? '' ) );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param object $row Store row.
	 */
	public function exists( object $row ): bool {
		$id = AttributeLabelIdentity::attribute_id_from_canonical( (string) ( $row->segment_key ?? '' ) );
		if ( $id <= 0 ) {
			return false;
		}
		if ( function_exists( 'wc_get_attribute' ) ) {
			$attr = wc_get_attribute( $id );

			return null !== $attr && false !== $attr;
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param object $row Store row.
	 */
	public function is_visitor_public( object $row ): bool {
		return $this->exists( $row );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param int    $user_id User id.
	 * @param object $row     Store row.
	 */
	public function user_can_edit( int $user_id, object $row ): bool {
		if ( ! $this->exists( $row ) ) {
			return false;
		}
		if ( $user_id > 0 ) {
			return user_can( $user_id, self::CAPABILITY );
		}

		return current_user_can( self::CAPABILITY );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param object $row Store row.
	 */
	public function edit_link( object $row ): string {
		$id = AttributeLabelIdentity::attribute_id_from_canonical( (string) ( $row->segment_key ?? '' ) );
		if ( $id <= 0 || ! function_exists( 'admin_url' ) ) {
			return '';
		}

		return (string) admin_url( 'edit.php?post_type=product&page=product_attributes&edit=' . $id );
	}
}
