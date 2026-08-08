<?php
/**
 * Unit tests for WooCommerce A.7a overlay hooks.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\WooCommerce\WooCommerceIntegration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Integration\WooCommerce\WooCommerceIntegration
 */
final class WooCommerceIntegrationOverlayTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_all_filters( WooCommerceIntegration::HOOK_ATTRIBUTE_LABEL );
		remove_all_filters( WooCommerceIntegration::HOOK_SINGLE_TERM_TITLE );
		remove_all_filters( WooCommerceIntegration::HOOK_PAGE_TITLE );
		remove_all_filters( WooCommerceIntegration::HOOK_TERM_DESCRIPTION );
		remove_all_filters( WooCommerceIntegration::HOOK_CATALOG_ORDERBY );
		remove_all_filters( WooCommerceIntegration::HOOK_CATALOG_ORDEREDBY );
	}

	public function test_overlay_applies_variation_attribute_name_preferentially(): void {
		$product = new class() {
			public function get_id(): int {
				return 3594;
			}
			/**
			 * @return array<string, object>
			 */
			public function get_attributes(): array {
				return array(
					'strength' => new class() {
						public function get_name(): string {
							return 'Strength';
						}
						public function get_variation(): bool {
							return true;
						}
					},
				);
			}
		};

		$integration = $this->make_integration();
		$integration->configure( true, true, '10.9.4', false, true );
		$map = array(
			'p:woocommerce:product:3594:attribute_name:strength'            => 'Styrka-attr',
			'p:woocommerce:product:3594:variation_attribute_name:strength' => 'Styrka-var',
		);
		$integration->register_output_hooks(
			static function ( string $key ) use ( $map ): ?string {
				return $map[ $key ] ?? null;
			}
		);

		$out = apply_filters( WooCommerceIntegration::HOOK_ATTRIBUTE_LABEL, 'Strength', 'strength', $product );
		$this->assertSame( 'Styrka-var', $out );
	}

	public function test_overlay_falls_back_to_attribute_name(): void {
		$product = new class() {
			public function get_id(): int {
				return 10;
			}
			/**
			 * @return array<string, object>
			 */
			public function get_attributes(): array {
				return array(
					'color' => new class() {
						public function get_name(): string {
							return 'Color';
						}
						public function get_variation(): bool {
							return false;
						}
					},
				);
			}
		};

		$integration = $this->make_integration();
		$integration->configure( true, true, '10.9.4', false, true );
		$integration->register_output_hooks(
			static function ( string $key ): ?string {
				return 'p:woocommerce:product:10:attribute_name:color' === $key ? 'Färg' : null;
			}
		);

		$out = apply_filters( WooCommerceIntegration::HOOK_ATTRIBUTE_LABEL, 'Color', 'color', $product );
		$this->assertSame( 'Färg', $out );
	}

	public function test_overlay_skips_when_disabled(): void {
		$product = new class() {
			public function get_id(): int {
				return 1;
			}
			/**
			 * @return array<string, object>
			 */
			public function get_attributes(): array {
				return array(
					'size' => new class() {
						public function get_name(): string {
							return 'Size';
						}
						public function get_variation(): bool {
							return false;
						}
					},
				);
			}
		};

		$integration = $this->make_integration();
		$integration->configure( true, true, '10.9.4', true, true );
		$integration->register_output_hooks(
			static function (): ?string {
				return 'X';
			}
		);
		$out = apply_filters( WooCommerceIntegration::HOOK_ATTRIBUTE_LABEL, 'Size', 'size', $product );
		$this->assertSame( 'Size', $out );
	}

	public function test_overlay_sanitizes_plain_attribute_labels(): void {
		$product = new class() {
			public function get_id(): int {
				return 1;
			}
			/**
			 * @return array<string, object>
			 */
			public function get_attributes(): array {
				return array(
					'size' => new class() {
						public function get_name(): string {
							return 'Size';
						}
						public function get_variation(): bool {
							return false;
						}
					},
				);
			}
		};

		$integration = $this->make_integration();
		$integration->configure( true, true, '10.9.4', false, true );
		$integration->register_output_hooks(
			static function (): ?string {
				return 'Storlek <b>x</b>';
			}
		);
		$out = apply_filters( WooCommerceIntegration::HOOK_ATTRIBUTE_LABEL, 'Size', 'size', $product );
		$this->assertSame( 'Storlek x', $out );
	}

	public function test_overlay_translates_orderby_labels_without_mutating_keys(): void {
		$integration = $this->make_integration();
		$integration->configure( true, true, '10.9.4', false, true );
		$integration->register_output_hooks(
			static function ( string $key ): ?string {
				return 'p:woocommerce:catalog_orderby:popularity:label' === $key ? 'Sortera efter popularitet' : null;
			}
		);

		$in  = array(
			'menu_order' => 'Default sorting',
			'popularity' => 'Sort by popularity',
			'price'      => 'Sort by price: low to high',
		);
		$out = apply_filters( WooCommerceIntegration::HOOK_CATALOG_ORDERBY, $in );
		$this->assertSame( array( 'menu_order', 'popularity', 'price' ), array_keys( $out ) );
		$this->assertSame( 'Default sorting', $out['menu_order'] );
		$this->assertSame( 'Sortera efter popularitet', $out['popularity'] );
		$this->assertSame( 'Sort by price: low to high', $out['price'] );
	}

	public function test_overlay_translates_orderedby_labels_isolated_failure(): void {
		$integration = $this->make_integration();
		$integration->configure( true, true, '10.9.4', false, true );
		$integration->register_output_hooks(
			static function ( string $key ): ?string {
				if ( 'p:woocommerce:catalog_orderedby:rating:label' === $key ) {
					return '';
				}
				if ( 'p:woocommerce:catalog_orderedby:date:label' === $key ) {
					return 'Sorterat efter senaste';
				}
				return null;
			}
		);

		$in  = array(
			'rating' => 'Sorted by average rating',
			'date'   => 'Sorted by latest',
		);
		$out = apply_filters( WooCommerceIntegration::HOOK_CATALOG_ORDEREDBY, $in );
		$this->assertSame( 'Sorted by average rating', $out['rating'] );
		$this->assertSame( 'Sorterat efter senaste', $out['date'] );
	}

	private function make_integration(): WooCommerceIntegration {
		return new WooCommerceIntegration( new PluginIdentity() );
	}
}
