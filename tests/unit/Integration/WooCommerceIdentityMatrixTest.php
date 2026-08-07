<?php
/**
 * A.7a WooCommerce identity matrix unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\Identity\PluginIdentity;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for frozen A.7a PluginIdentity keys.
 *
 * @covers \AIMultilingual\Integration\Identity\PluginIdentity
 */
final class WooCommerceIdentityMatrixTest extends TestCase {

	/**
	 * @return list<string>
	 */
	private function frozen_keys(): array {
		$identity = new PluginIdentity();
		return array(
			$identity->build( 'woocommerce', 'product', '3594', 'attribute_name', 'strength' ),
			$identity->build( 'woocommerce', 'product', '3594', 'variation_attribute_name', 'strength' ),
			$identity->build( 'woocommerce', 'product_cat', '40', 'name' ),
			$identity->build( 'woocommerce', 'product_cat', '40', 'description' ),
			$identity->build( 'woocommerce', 'product_tag', '45', 'name' ),
			$identity->build( 'woocommerce', 'product_tag', '45', 'description' ),
		);
	}

	public function test_frozen_keys_match_matrix_literals(): void {
		$keys = $this->frozen_keys();
		$this->assertSame(
			array(
				'p:woocommerce:product:3594:attribute_name:strength',
				'p:woocommerce:product:3594:variation_attribute_name:strength',
				'p:woocommerce:product_cat:40:name',
				'p:woocommerce:product_cat:40:description',
				'p:woocommerce:product_tag:45:name',
				'p:woocommerce:product_tag:45:description',
			),
			$keys
		);
	}

	public function test_attribute_name_and_variation_name_keys_are_distinct(): void {
		$keys = $this->frozen_keys();
		$this->assertNotSame( $keys[0], $keys[1] );
	}

	public function test_frozen_keys_respect_segment_length_limit(): void {
		foreach ( $this->frozen_keys() as $key ) {
			$this->assertLessThanOrEqual( Contract::MAX_SEGMENT_KEY_LENGTH, strlen( $key ) );
			$this->assertSame( 0, strpos( $key, Contract::SEGMENT_KEY_PREFIX . ':' ) );
		}
	}

	public function test_integration_id_woocommerce_is_valid(): void {
		$this->assertSame( 1, preg_match( Contract::INTEGRATION_ID_PATTERN, 'woocommerce' ) );
	}

	public function test_parse_round_trips_product_attribute_key(): void {
		$identity = new PluginIdentity();
		$key      = $identity->build( 'woocommerce', 'product', '3594', 'attribute_name', 'strength' );
		$parsed   = $identity->parse( $key );
		$this->assertNotNull( $parsed );
		$this->assertSame( 'woocommerce', $parsed['integration_id'] );
		$this->assertSame( 'product', $parsed['owner_type'] );
		$this->assertSame( '3594', $parsed['owner_id'] );
		$this->assertSame( 'attribute_name', $parsed['field'] );
		$this->assertSame( array( 'strength' ), $parsed['nested'] );
	}
}
