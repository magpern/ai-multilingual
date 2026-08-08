<?php
/**
 * A.7b archive chrome ownership boundary guards.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\WooCommerce\WooCommerceIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Ensures A.7b only admits B1/B2 and does not steal deferred surfaces.
 *
 * @covers \AIMultilingual\Integration\WooCommerce\WooCommerceIntegration
 */
final class WooCommerceArchiveChromeOwnershipTest extends TestCase {

	public function test_source_contains_only_admitted_archive_hooks(): void {
		$path = dirname( __DIR__, 3 ) . '/src/Integration/WooCommerce/WooCommerceIntegration.php';
		$src  = file_get_contents( $path );
		$this->assertIsString( $src );

		$this->assertStringContainsString( "HOOK_CATALOG_ORDERBY = 'woocommerce_catalog_orderby'", $src );
		$this->assertStringContainsString( "HOOK_CATALOG_ORDEREDBY = 'woocommerce_catalog_orderedby'", $src );

		// Deferred B3–B8 must not be hooked by the Woo integration.
		$this->assertStringNotContainsString( 'woocommerce_result_count', $src );
		$this->assertStringNotContainsString( 'woocommerce_no_products_found', $src );
		$this->assertStringNotContainsString( 'paginate_links', $src );
		$this->assertStringNotContainsString( 'elementor', strtolower( $src ) );
		$this->assertStringNotContainsString( 'biopentra-storefront', $src );
		$this->assertStringNotContainsString( 'biopentra-loop-card', $src );
		$this->assertStringNotContainsString( 'blocksy', strtolower( $src ) );
	}

	public function test_a7a_catalog_identities_remain_distinct_from_archive_chrome(): void {
		$identity = new PluginIdentity();
		$term     = $identity->build( 'woocommerce', 'product_cat', '40', 'name' );
		$orderby  = $identity->build( 'woocommerce', 'catalog_orderby', 'popularity', 'label' );
		$this->assertNotSame( $term, $orderby );
		$this->assertStringStartsWith( 'p:woocommerce:product_cat:', $term );
		$this->assertStringStartsWith( 'p:woocommerce:catalog_orderby:', $orderby );
	}

	public function test_orderby_allowlist_is_exact_frozen_set(): void {
		$this->assertSame(
			array(
				'menu_order',
				'popularity',
				'rating',
				'date',
				'price',
				'price-desc',
				'relevance',
			),
			WooCommerceIntegration::ORDERBY_ALLOWLIST
		);
	}

	public function test_non_allowlisted_orderby_keys_pass_through_untouched(): void {
		remove_all_filters( WooCommerceIntegration::HOOK_CATALOG_ORDERBY );
		$integration = new WooCommerceIntegration( new PluginIdentity(), true, true, '10.9.4', false, true );
		$integration->register_output_hooks(
			static function (): ?string {
				return 'SHOULD_NOT_APPLY';
			}
		);
		$in  = array( 'custom_sort' => 'Custom sort' );
		$out = apply_filters( WooCommerceIntegration::HOOK_CATALOG_ORDERBY, $in );
		$this->assertSame( $in, $out );
	}
}
