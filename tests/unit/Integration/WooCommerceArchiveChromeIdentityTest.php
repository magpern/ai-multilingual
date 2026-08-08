<?php
/**
 * A.7b archive chrome identity contract tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\Identity\PluginIdentity;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Integration\Identity\PluginIdentity
 */
final class WooCommerceArchiveChromeIdentityTest extends TestCase {

	public function test_orderby_and_orderedby_keys_match_frozen_contract(): void {
		$identity = new PluginIdentity();
		$orderby  = $identity->build( 'woocommerce', 'catalog_orderby', 'popularity', 'label' );
		$ordered  = $identity->build( 'woocommerce', 'catalog_orderedby', 'popularity', 'label' );

		$this->assertSame( 'p:woocommerce:catalog_orderby:popularity:label', $orderby );
		$this->assertSame( 'p:woocommerce:catalog_orderedby:popularity:label', $ordered );
		$this->assertNotSame( $orderby, $ordered );
		$this->assertLessThanOrEqual( Contract::MAX_SEGMENT_KEY_LENGTH, strlen( $orderby ) );
		$this->assertLessThanOrEqual( Contract::MAX_SEGMENT_KEY_LENGTH, strlen( $ordered ) );
	}

	public function test_allowlisted_orderby_keys_serialize(): void {
		$identity = new PluginIdentity();
		foreach ( array( 'menu_order', 'popularity', 'rating', 'date', 'price', 'price-desc', 'relevance' ) as $key ) {
			$segment = $identity->build( 'woocommerce', 'catalog_orderby', $key, 'label' );
			$this->assertStringStartsWith( 'p:woocommerce:catalog_orderby:', $segment );
			$this->assertStringEndsWith( ':label', $segment );
		}
	}
}
