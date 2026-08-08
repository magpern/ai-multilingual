<?php
/**
 * Unit tests for WooCommerce A.7c customer journey surfaces.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\WooCommerce\WooCommerceIntegration;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * @covers \AIMultilingual\Integration\WooCommerce\WooCommerceIntegration
 */
final class WooCommerceCustomerJourneyTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_all_filters( WooCommerceIntegration::HOOK_CHECKOUT_FIELDS );
		remove_all_filters( WooCommerceIntegration::HOOK_ORDER_BUTTON_TEXT );
		remove_all_filters( WooCommerceIntegration::HOOK_ACCOUNT_MENU_ITEMS );
		remove_all_filters( WooCommerceIntegration::HOOK_THANKYOU_RECEIVED );
		remove_all_filters( WooCommerceIntegration::HOOK_ORDER_ITEM_TOTALS );
		remove_all_filters( 'woocommerce_endpoint_orders_title' );
	}

	public function test_extract_checkout_emits_cj3_and_cj6_units(): void {
		$integration = $this->make_integration();
		$integration->configure( true, true, '10.9.4', false, true );
		$units = $integration->extract_for_post( $this->fake_post( 4506, 'page' ) );
		$keys  = array_map( static fn( $u ) => $u->segment_key, $units );
		$this->assertContains( 'p:woocommerce:checkout_field:billing_first_name:label', $keys );
		$this->assertContains( 'p:woocommerce:checkout:order_button:label', $keys );
		$this->assertContains( 'p:woocommerce:checkout:thankyou_received:label', $keys );
		$this->assertContains( 'p:woocommerce:order_totals:order_total:label', $keys );
		$this->assertSame( 'WooCommerce customer journey', $units[0]->parent_context );
	}

	public function test_extract_myaccount_emits_cj4_units(): void {
		$integration = $this->make_integration();
		$integration->configure( true, true, '10.9.4', false, true );
		$units = $integration->extract_for_post( $this->fake_post( 84, 'page' ) );
		$keys  = array_map( static fn( $u ) => $u->segment_key, $units );
		$this->assertContains( 'p:woocommerce:account_menu:orders:label', $keys );
		$this->assertContains( 'p:woocommerce:endpoint:orders:title', $keys );
	}

	public function test_overlay_checkout_field_and_order_button(): void {
		$integration = $this->make_integration();
		$integration->configure( true, true, '10.9.4', false, true );
		$map = array(
			'p:woocommerce:checkout_field:billing_first_name:label' => 'Förnamn',
			'p:woocommerce:checkout:order_button:label' => 'Lägg beställning',
		);
		$integration->register_output_hooks(
			static function ( string $key ) use ( $map ): ?string {
				return $map[ $key ] ?? null;
			}
		);

		$fields = apply_filters(
			WooCommerceIntegration::HOOK_CHECKOUT_FIELDS,
			array(
				'billing' => array(
					'billing_first_name' => array( 'label' => 'First name' ),
					'billing_last_name'  => array( 'label' => 'Last name' ),
				),
			)
		);
		$this->assertSame( 'Förnamn', $fields['billing']['billing_first_name']['label'] );
		$this->assertSame( 'Last name', $fields['billing']['billing_last_name']['label'] );
		$this->assertSame(
			'Lägg beställning',
			apply_filters( WooCommerceIntegration::HOOK_ORDER_BUTTON_TEXT, 'Place order' )
		);
	}

	public function test_overlay_account_menu_and_order_totals_labels_only(): void {
		$integration = $this->make_integration();
		$integration->configure( true, true, '10.9.4', false, true );
		$map = array(
			'p:woocommerce:account_menu:orders:label'      => 'Beställningar',
			'p:woocommerce:order_totals:order_total:label' => 'Summa',
		);
		$integration->register_output_hooks(
			static function ( string $key ) use ( $map ): ?string {
				return $map[ $key ] ?? null;
			}
		);

		$menu = apply_filters(
			WooCommerceIntegration::HOOK_ACCOUNT_MENU_ITEMS,
			array(
				'orders'    => 'Orders',
				'dashboard' => 'Dashboard',
			)
		);
		$this->assertSame( 'Beställningar', $menu['orders'] );
		$this->assertSame( 'Dashboard', $menu['dashboard'] );

		$totals = apply_filters(
			WooCommerceIntegration::HOOK_ORDER_ITEM_TOTALS,
			array(
				'order_total' => array(
					'label' => 'Total:',
					'value' => '100 kr',
				),
			)
		);
		$this->assertSame( 'Summa', $totals['order_total']['label'] );
		$this->assertSame( '100 kr', $totals['order_total']['value'] );
	}

	public function test_no_cart_chrome_hooks_admitted(): void {
		$src = file_get_contents( dirname( __DIR__, 3 ) . '/src/Integration/WooCommerce/WooCommerceIntegration.php' );
		$this->assertIsString( $src );
		$this->assertStringNotContainsString( 'proceed-to-checkout', $src );
		$this->assertStringNotContainsString( 'biopentra-storefront', $src );
	}

	private function make_integration(): WooCommerceIntegration {
		return new WooCommerceIntegration(
			new PluginIdentity(),
			null,
			null,
			null,
			null,
			null,
			3755,
			static fn() => array(),
			static fn() => array(),
			null,
			null,
			4506,
			84,
			static fn() => array( 'billing_first_name' => 'First name' ),
			static fn() => array(
				'orders'    => 'Orders',
				'dashboard' => 'Dashboard',
			),
			static fn() => array( 'order_total' => 'Total' )
		);
	}

	private function fake_post( int $id, string $type ): WP_Post {
		$post              = new WP_Post( new \stdClass() );
		$post->ID          = $id;
		$post->post_type   = $type;
		$post->post_status = 'publish';
		return $post;
	}
}
