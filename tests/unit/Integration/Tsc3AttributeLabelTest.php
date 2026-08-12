<?php
/**
 * TSC.3 unit tests — attribute identity, extract, overlay, rehost predicate.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\WooCommerce\AttributeLabelIdentity;
use AIMultilingual\Integration\WooCommerce\WooAttributeLabelAuthority;
use AIMultilingual\Integration\WooCommerce\WooCommerceIntegration;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * @covers \AIMultilingual\Integration\WooCommerce\AttributeLabelIdentity
 * @covers \AIMultilingual\Integration\WooCommerce\WooCommerceIntegration
 */
final class Tsc3AttributeLabelTest extends TestCase {

	public function test_canonical_key_uses_attribute_id(): void {
		$key = AttributeLabelIdentity::canonical_key( new PluginIdentity(), 7 );
		$this->assertSame( 'p:woocommerce:attribute:7:label', $key );
		$this->assertTrue( AttributeLabelIdentity::is_canonical_key( $key ) );
		$this->assertSame( 7, AttributeLabelIdentity::attribute_id_from_canonical( $key ) );
	}

	public function test_taxonomy_compat_product_key_detection(): void {
		$this->assertTrue(
			AttributeLabelIdentity::is_taxonomy_compat_product_key(
				'p:woocommerce:product:10:attribute_name:pa_color'
			)
		);
		$this->assertFalse(
			AttributeLabelIdentity::is_taxonomy_compat_product_key(
				'p:woocommerce:product:10:attribute_name:strength'
			)
		);
		$this->assertFalse(
			AttributeLabelIdentity::is_taxonomy_compat_product_key(
				'p:woocommerce:attribute:7:label'
			)
		);
	}

	public function test_product_extract_skips_taxonomy_attributes(): void {
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
					'slug'        => 'pa_color',
					'label'       => 'Color',
					'variation'   => true,
					'is_taxonomy' => true,
				),
				array(
					'slug'        => 'material',
					'label'       => 'Material',
					'variation'   => false,
					'is_taxonomy' => false,
				),
			)
		);
		$units = $integration->extract_for_post( $this->fake_post( 5, 'product' ) );
		$keys  = array_map( static fn( $u ) => $u->segment_key, $units );
		$this->assertSame( array( 'p:woocommerce:product:5:attribute_name:material' ), $keys );
	}

	public function test_shop_extract_emits_global_attribute_labels(): void {
		$integration = new WooCommerceIntegration(
			new PluginIdentity(),
			true,
			true,
			'10.9.4',
			false,
			true,
			100,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			static fn() => array(
				array(
					'attribute_id' => 7,
					'label'        => 'Color',
				),
			)
		);
		$units = $integration->extract_for_post( $this->fake_post( 100, 'page' ) );
		$keys  = array_map( static fn( $u ) => $u->segment_key, $units );
		$this->assertContains( 'p:woocommerce:attribute:7:label', $keys );
	}

	public function test_overlay_local_without_product_leaves_source(): void {
		$integration = new WooCommerceIntegration(
			new PluginIdentity(),
			true,
			true,
			'10.9.4',
			false,
			true
		);
		$integration->register_output_hooks(
			static function () {
				return 'Translated';
			}
		);
		$out = apply_filters( WooCommerceIntegration::HOOK_ATTRIBUTE_LABEL, 'Material', 'material', null );
		$this->assertSame( 'Material', $out );
	}

	public function test_overlay_admin_context_leaves_source(): void {
		if ( ! function_exists( 'is_admin' ) ) {
			/**
			 * Stub is_admin for this test file.
			 */
			eval( 'function is_admin() { return ! empty( $GLOBALS["aiml_force_is_admin"] ); }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		}
		$GLOBALS['aiml_force_is_admin'] = true;
		$integration                    = new WooCommerceIntegration(
			new PluginIdentity(),
			true,
			true,
			'10.9.4',
			false,
			true
		);
		$integration->register_output_hooks(
			static function () {
				return 'Translated';
			}
		);
		$product = new class() {
			public function get_id(): int {
				return 5;
			}
		};
		$out = apply_filters( WooCommerceIntegration::HOOK_ATTRIBUTE_LABEL, 'Material', 'material', $product );
		unset( $GLOBALS['aiml_force_is_admin'] );
		$this->assertSame( 'Material', $out );
	}

	public function test_authority_requires_manage_product_terms(): void {
		$authority = new WooAttributeLabelAuthority();
		$row       = (object) array(
			'segment_key' => 'p:woocommerce:attribute:7:label',
			'source_type' => Store::SOURCE_POST,
			'source_id'   => 100,
		);
		$this->assertTrue( $authority->applies( $row ) );
	}

	public function test_rehost_predicate_only_canonical(): void {
		$this->assertTrue( AttributeLabelIdentity::rehost_predicate( 'p:woocommerce:attribute:3:label' ) );
		$this->assertFalse( AttributeLabelIdentity::rehost_predicate( 'p:woocommerce:product:1:attribute_name:x' ) );
		$this->assertFalse( AttributeLabelIdentity::rehost_predicate( 'p:woocommerce:catalog_orderby:menu_order:label' ) );
	}

	private function fake_post( int $id, string $type ): WP_Post {
		$post              = new WP_Post( new \stdClass() );
		$post->ID          = $id;
		$post->post_type   = $type;
		$post->post_status = 'publish';
		return $post;
	}
}
