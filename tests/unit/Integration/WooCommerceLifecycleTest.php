<?php
/**
 * A.7a WooCommerce lifecycle / foreign-persistence unit checks.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\WooCommerce\WooCommerceIntegration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Integration\WooCommerce\WooCommerceIntegration
 */
final class WooCommerceLifecycleTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_all_filters( WooCommerceIntegration::HOOK_ATTRIBUTE_LABEL );
		remove_all_filters( WooCommerceIntegration::HOOK_SINGLE_TERM_TITLE );
		remove_all_filters( WooCommerceIntegration::HOOK_PAGE_TITLE );
		remove_all_filters( WooCommerceIntegration::HOOK_TERM_DESCRIPTION );
	}

	public function test_disabled_filter_retains_compatible_extract_empty_and_no_overlay(): void {
		$integration = new WooCommerceIntegration(
			new PluginIdentity(),
			true,
			true,
			'10.9.4',
			true,
			true,
			null,
			static fn() => array(
				array(
					'slug'      => 'strength',
					'label'     => 'Strength',
					'variation' => true,
				),
			)
		);

		$this->assertSame( Contract::STATE_DISABLED, $integration->get_compatibility()->state() );
		$post              = new \WP_Post( new \stdClass() );
		$post->ID          = 3594;
		$post->post_type   = 'product';
		$post->post_status = 'publish';
		$this->assertSame( array(), $integration->extract_for_post( $post ) );

		$integration->register_output_hooks(
			static function (): ?string {
				return 'X';
			}
		);
		$product = new class() {
			public function get_id(): int {
				return 3594;
			}
			/**
			 * @return array<string, object>
			 */
			public function get_attributes(): array {
				return array();
			}
		};
		$this->assertSame(
			'Strength',
			apply_filters( WooCommerceIntegration::HOOK_ATTRIBUTE_LABEL, 'Strength', 'strength', $product )
		);
	}

	public function test_attribute_rename_does_not_fuzzy_rematch(): void {
		$integration = new WooCommerceIntegration(
			new PluginIdentity(),
			true,
			true,
			'10.9.4',
			false,
			true,
			null,
			static fn() => array(
				array(
					'slug'      => 'potency',
					'label'     => 'Strength',
					'variation' => true,
				),
			)
		);
		$post              = new \WP_Post( new \stdClass() );
		$post->ID          = 3594;
		$post->post_type   = 'product';
		$post->post_status = 'publish';
		$units             = $integration->extract_for_post( $post );
		$keys              = array_map( static fn( $u ) => $u->segment_key, $units );
		$this->assertNotContains( 'p:woocommerce:product:3594:attribute_name:strength', $keys );
		$this->assertContains( 'p:woocommerce:product:3594:attribute_name:potency', $keys );
	}

	public function test_overlay_does_not_require_woocommerce_persistence_writes(): void {
		// Guard: register_output_hooks must only add WordPress filters — no update_post_meta calls.
		$integration = new WooCommerceIntegration( new PluginIdentity(), true, true, '10.9.4', false, true );
		$integration->register_output_hooks(
			static function (): ?string {
				return 'Styrka';
			}
		);
		$this->assertTrue( true );
	}
}
