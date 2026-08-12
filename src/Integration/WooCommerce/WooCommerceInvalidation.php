<?php
/**
 * WooCommerce TSC.3 invalidation + shop-host rehost hooks.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Integration\WooCommerce;

use AIMultilingual\Surface\RequestLocalInvalidationCoordinator;
use AIMultilingual\Translation\Store;

/**
 * Mark-dirty only + bounded attribute-label rehost. No provider calls.
 */
final class WooCommerceInvalidation {

	public const OPTION_SHOP_PAGE = 'woocommerce_shop_page_id';

	public const COMPAT_HOST_OPTION = 'aiml_woo_attribute_label_host_compat';

	/**
	 * Builds the WooCommerce invalidation registrar.
	 *
	 * @param WooCommerceIntegration              $integration Woo integration.
	 * @param Store                               $store       Store.
	 * @param RequestLocalInvalidationCoordinator $coordinator Coordinator.
	 */
	public function __construct(
		private WooCommerceIntegration $integration,
		private Store $store,
		private RequestLocalInvalidationCoordinator $coordinator,
	) {
	}

	/**
	 * Registers attribute CRUD, shop page reassignment, and email option observers.
	 */
	public function register(): void {
		if ( ! $this->integration->get_compatibility()->allows_operation() ) {
			return;
		}

		add_action( 'woocommerce_attribute_added', array( $this, 'on_attribute_changed' ), 20, 0 );
		add_action( 'woocommerce_attribute_updated', array( $this, 'on_attribute_changed' ), 20, 0 );
		add_action( 'woocommerce_attribute_deleted', array( $this, 'on_attribute_changed' ), 20, 0 );

		add_action( 'update_option_' . self::OPTION_SHOP_PAGE, array( $this, 'on_shop_page_updating' ), 10, 2 );
		add_action( 'updated_option', array( $this, 'on_updated_option' ), 20, 3 );
		add_action( 'added_option', array( $this, 'on_added_option' ), 20, 2 );
	}

	/**
	 * Attribute definition changed — dirty shop technical host only.
	 */
	public function on_attribute_changed(): void {
		$shop = $this->integration->resolved_shop_page_id();
		if ( $shop > 0 ) {
			$this->coordinator->mark_dirty( Store::SOURCE_POST, $shop );
		}
	}

	/**
	 * Shop page option about to change — rehost attribute-label rows.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $value     New value.
	 */
	public function on_shop_page_updating( $old_value, $value ): void {
		$old_id = (int) $old_value;
		$new_id = (int) $value;
		if ( $old_id <= 0 || $new_id <= 0 || $old_id === $new_id ) {
			return;
		}

		$this->store->rehost_segments(
			Store::SOURCE_POST,
			$old_id,
			$new_id,
			array( AttributeLabelIdentity::class, 'rehost_predicate' )
		);

		update_option(
			self::COMPAT_HOST_OPTION,
			array(
				'old_id' => $old_id,
				'new_id' => $new_id,
			),
			false
		);

		$this->coordinator->mark_dirty( Store::SOURCE_POST, $new_id );
	}

	/**
	 * Allowlisted email settings option changed.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $value     New value.
	 */
	public function on_updated_option( string $option, $old_value, $value ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$this->maybe_dirty_email_option( $option );
	}

	/**
	 * Allowlisted email settings option added.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 */
	public function on_added_option( string $option, $value ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$this->maybe_dirty_email_option( $option );
	}

	/**
	 * Dirty checkout host when an allowlisted email settings option mutates.
	 *
	 * @param string $option Option name.
	 */
	private function maybe_dirty_email_option( string $option ): void {
		if ( ! $this->is_allowlisted_email_settings_option( $option ) ) {
			return;
		}
		$checkout = $this->integration->resolved_checkout_page_id();
		if ( $checkout > 0 ) {
			$this->coordinator->mark_dirty( Store::SOURCE_POST, $checkout );
		}
	}

	/**
	 * Whether the option is a frozen EMAIL_ID_ALLOWLIST settings key.
	 *
	 * @param string $option Option name.
	 */
	public function is_allowlisted_email_settings_option( string $option ): bool {
		foreach ( WooCommerceIntegration::EMAIL_ID_ALLOWLIST as $email_id ) {
			if ( 'woocommerce_' . $email_id . '_settings' === $option ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Previous shop host id for temporary attribute-label read compat, or 0.
	 */
	public static function previous_shop_host_id(): int {
		$raw = get_option( self::COMPAT_HOST_OPTION, null );
		if ( ! is_array( $raw ) ) {
			return 0;
		}
		$old     = (int) ( $raw['old_id'] ?? 0 );
		$new     = (int) ( $raw['new_id'] ?? 0 );
		$current = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		if ( $old > 0 && $new > 0 && $current === $new && $old !== $new ) {
			return $old;
		}

		return 0;
	}
}
