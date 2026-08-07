<?php
/**
 * Unit tests for WooCommerce A.7a extraction.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\WooCommerce\WooCommerceIntegration;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * @covers \AIMultilingual\Integration\WooCommerce\WooCommerceIntegration
 */
final class WooCommerceIntegrationExtractTest extends TestCase {

	public function test_compatibility_matrix(): void {
		$integration = $this->make_integration();
		$integration->configure( true, true, '10.9.4', false, true );
		$this->assertSame( Contract::STATE_COMPATIBLE, $integration->get_compatibility()->state() );

		$integration->configure( false, null, null, null, null );
		$this->assertSame( Contract::STATE_UNAVAILABLE, $integration->get_compatibility()->state() );

		$integration->configure( true, false, null, null, null );
		$this->assertSame( Contract::STATE_UNAVAILABLE, $integration->get_compatibility()->state() );

		$integration->configure( null, true, '9.9.0', null, null );
		$this->assertSame( Contract::STATE_UNSUPPORTED_VERSION, $integration->get_compatibility()->state() );

		$integration->configure( null, null, '10.9.4', true, null );
		$this->assertSame( Contract::STATE_DISABLED, $integration->get_compatibility()->state() );

		$integration->configure( null, null, null, false, false );
		$this->assertSame( Contract::STATE_MISSING_REQUIRED_HOOK, $integration->get_compatibility()->state() );
	}

	public function test_extract_product_emits_attribute_and_variation_name_units(): void {
		$integration = $this->make_integration(
			array(
				array(
					'slug'      => 'strength',
					'label'     => 'Strength',
					'variation' => true,
				),
			)
		);
		$integration->configure( true, true, '10.9.4', false, true );
		$units = $integration->extract_for_post( $this->fake_post( 3594, 'product' ) );
		$keys  = array_map( static fn( $u ) => $u->segment_key, $units );
		$this->assertSame(
			array(
				'p:woocommerce:product:3594:attribute_name:strength',
				'p:woocommerce:product:3594:variation_attribute_name:strength',
			),
			$keys
		);
		$this->assertSame( 'Strength', $units[0]->source_text );
		$this->assertSame( 'Strength', $units[1]->source_text );
		$this->assertNotSame( $units[0]->segment_key, $units[1]->segment_key );
	}

	public function test_extract_product_skips_non_variation_variation_key(): void {
		$integration = $this->make_integration(
			array(
				array(
					'slug'      => 'color',
					'label'     => 'Color',
					'variation' => false,
				),
			)
		);
		$integration->configure( true, true, '10.9.4', false, true );
		$units = $integration->extract_for_post( $this->fake_post( 1, 'product' ) );
		$keys  = array_map( static fn( $u ) => $u->segment_key, $units );
		$this->assertSame( array( 'p:woocommerce:product:1:attribute_name:color' ), $keys );
	}

	public function test_extract_empty_for_unrelated_post(): void {
		$integration = $this->make_integration(
			array(
				array(
					'slug'      => 'strength',
					'label'     => 'Strength',
					'variation' => true,
				),
			),
			array(
				array(
					'taxonomy'    => 'product_cat',
					'term_id'     => 40,
					'name'        => 'Recovery',
					'description' => 'Desc',
				),
			),
			3755
		);
		$integration->configure( true, true, '10.9.4', false, true );
		$this->assertSame( array(), $integration->extract_for_post( $this->fake_post( 99, 'page' ) ) );
	}

	public function test_extract_shop_page_emits_catalog_term_units(): void {
		$integration = $this->make_integration(
			array(),
			array(
				array(
					'taxonomy'    => 'product_cat',
					'term_id'     => 40,
					'name'        => 'Recovery Support',
					'description' => '<p>Hello</p>',
				),
				array(
					'taxonomy'    => 'product_tag',
					'term_id'     => 45,
					'name'        => 'GHRH',
					'description' => '',
				),
			),
			3755
		);
		$integration->configure( true, true, '10.9.4', false, true );
		$units = $integration->extract_for_post( $this->fake_post( 3755, 'page' ) );
		$keys  = array_map( static fn( $u ) => $u->segment_key, $units );
		$this->assertSame(
			array(
				'p:woocommerce:product_cat:40:name',
				'p:woocommerce:product_cat:40:description',
				'p:woocommerce:product_tag:45:name',
			),
			$keys
		);
		$this->assertSame( 'html', $units[1]->text_format );
	}

	public function test_extract_skips_when_incompatible(): void {
		$integration = $this->make_integration(
			array(
				array(
					'slug'      => 'strength',
					'label'     => 'Strength',
					'variation' => true,
				),
			)
		);
		$integration->configure( true, true, '10.9.4', true, true );
		$this->assertSame( array(), $integration->extract_for_post( $this->fake_post( 3594, 'product' ) ) );
	}

	/**
	 * @param list<array{slug:string,label:string,variation:bool}>                    $attrs Attributes.
	 * @param list<array{taxonomy:string,term_id:int,name:string,description:string}> $terms Terms.
	 * @param int|null                                                                $shop  Shop page ID.
	 */
	private function make_integration( array $attrs = array(), array $terms = array(), ?int $shop = null ): WooCommerceIntegration {
		return new WooCommerceIntegration(
			new PluginIdentity(),
			null,
			null,
			null,
			null,
			null,
			$shop,
			static fn() => $attrs,
			static fn() => $terms
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
