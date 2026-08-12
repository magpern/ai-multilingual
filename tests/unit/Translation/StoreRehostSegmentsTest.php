<?php
/**
 * Unit tests for Store::rehost_segments (TSC.3).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Translation;

use AIMultilingual\Cache\Cache;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\WooCommerce\AttributeLabelIdentity;
use AIMultilingual\Integration\WooCommerce\WooCommerceIntegration;
use AIMultilingual\Integration\WooCommerce\WooCommerceInvalidation;
use AIMultilingual\Surface\RequestLocalInvalidationCoordinator;
use AIMultilingual\Surface\SurfaceRegistry;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Translation\Store::rehost_segments
 */
final class StoreRehostSegmentsTest extends TestCase {

	public function test_rehost_rejects_invalid_hosts_without_query(): void {
		$store = new Store( new Cache() );
		$stats = $store->rehost_segments( Store::SOURCE_POST, 0, 5, array( AttributeLabelIdentity::class, 'rehost_predicate' ) );
		$this->assertSame(
			array(
				'moved'   => 0,
				'retired' => 0,
				'skipped' => 0,
			),
			$stats
		);

		$stats2 = $store->rehost_segments( Store::SOURCE_POST, 5, 5, array( AttributeLabelIdentity::class, 'rehost_predicate' ) );
		$this->assertSame(
			array(
				'moved'   => 0,
				'retired' => 0,
				'skipped' => 0,
			),
			$stats2
		);
	}

	public function test_email_option_allowlist_helper(): void {
		$store = new Store( new Cache() );
		$woo   = new WooCommerceIntegration( new PluginIdentity() );
		$inv   = new WooCommerceInvalidation(
			$woo,
			$store,
			new RequestLocalInvalidationCoordinator( $store, new SurfaceRegistry() )
		);
		$this->assertTrue( $inv->is_allowlisted_email_settings_option( 'woocommerce_customer_completed_order_settings' ) );
		$this->assertFalse( $inv->is_allowlisted_email_settings_option( 'woocommerce_shop_page_id' ) );
		$this->assertFalse( $inv->is_allowlisted_email_settings_option( 'blogname' ) );
	}
}
