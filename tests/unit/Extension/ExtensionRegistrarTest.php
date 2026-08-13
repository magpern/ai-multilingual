<?php
/**
 * Unit tests for ExtensionRegistrar (TSC.6).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Extension;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Extension\ExtensionDiagnostics;
use AIMultilingual\Extension\ExtensionManifest;
use AIMultilingual\Extension\ExtensionMetaDefinition;
use AIMultilingual\Extension\ExtensionRegistrar;
use AIMultilingual\Extension\ExtensionRegistry;
use AIMultilingual\Extension\Block\ExtensionBlockAdapter;
use AIMultilingual\Surface\Meta\RegisteredMetaRegistry;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Extension\ExtensionRegistrar
 */
final class ExtensionRegistrarTest extends TestCase {

	private ExtensionRegistrar $registrar;

	private RegisteredMetaRegistry $meta_registry;

	protected function setUp(): void {
		parent::setUp();
		$this->meta_registry = new RegisteredMetaRegistry();
		$this->registrar     = new ExtensionRegistrar(
			$this->meta_registry,
			new AdapterRegistry(),
			new ExtensionRegistry(),
			new ExtensionDiagnostics()
		);
	}

	public function test_valid_extension_and_meta_registers_at_seal(): void {
		$handle = $this->registrar->register_extension(
			new ExtensionManifest( 'demo_ext', '1.0.0', array( 'demo' ) )
		);
		$handle->register_meta(
			new ExtensionMetaDefinition(
				namespace: 'demo',
				source_type: Store::SOURCE_POST,
				meta_key: '_demo_subtitle',
				label: 'Demo subtitle',
			)
		);
		$this->registrar->seal();

		$this->assertTrue( $this->registrar->is_sealed() );
		$this->assertTrue( $this->meta_registry->has( Store::SOURCE_POST, '_demo_subtitle' ) );
		$def = $this->meta_registry->get( Store::SOURCE_POST, '_demo_subtitle' );
		$this->assertNotNull( $def );
		$this->assertFalse( $def->provider_allowed );
		$this->assertSame( 'm:demo:_demo_subtitle', $def->native_segment_key() );
	}

	public function test_duplicate_extension_id_rejected(): void {
		$this->registrar->register_extension(
			new ExtensionManifest( 'dup_ext', '1.0.0', array( 'a' ) )
		);
		$this->expectException( \InvalidArgumentException::class );
		$this->registrar->register_extension(
			new ExtensionManifest( 'dup_ext', '1.0.0', array( 'b' ) )
		);
	}

	public function test_namespace_theft_rejected(): void {
		$handle = $this->registrar->register_extension(
			new ExtensionManifest( 'owner', '1.0.0', array( 'owned' ) )
		);
		$this->expectException( \InvalidArgumentException::class );
		$handle->register_meta(
			new ExtensionMetaDefinition(
				namespace: 'other',
				source_type: Store::SOURCE_POST,
				meta_key: '_x',
				label: 'X',
			)
		);
	}

	public function test_core_block_collision_rejected(): void {
		$handle = $this->registrar->register_extension(
			new ExtensionManifest( 'blocks', '1.0.0', array( 'b' ) )
		);
		$this->expectException( \InvalidArgumentException::class );
		$handle->register_block_adapter(
			new class() implements ExtensionBlockAdapter {
				public function get_block_names(): array {
					return array( 'core/paragraph' );
				}
				public function get_supported_fields(): array {
					return array( 'content' );
				}
				public function is_translatable_instance( array $block ): bool {
					return true;
				}
				public function extract_field( array $block, string $field_id ): ?string {
					return 'x';
				}
				public function apply_field( array $block, string $field_id, string $translated_text ): array {
					return $block;
				}
				public function get_text_format( string $field_id ): string {
					return 'plain';
				}
			}
		);
	}

	public function test_late_registration_rejected_after_seal(): void {
		$this->registrar->seal();
		$this->expectException( \LogicException::class );
		$this->registrar->register_extension(
			new ExtensionManifest( 'late', '1.0.0', array( 'late' ) )
		);
	}

	public function test_inactive_activation_still_registers_definition(): void {
		$handle = $this->registrar->register_extension(
			new ExtensionManifest( 'inactive', '1.0.0', array( 'inactive' ) )
		);
		$handle->register_meta(
			new ExtensionMetaDefinition(
				namespace: 'inactive',
				source_type: Store::SOURCE_POST,
				meta_key: '_inactive_meta',
				label: 'Inactive',
				activation: static fn (): bool => false,
			)
		);
		$this->registrar->seal();
		$def = $this->meta_registry->get( Store::SOURCE_POST, '_inactive_meta' );
		$this->assertNotNull( $def );
		$this->assertFalse( $def->is_active() );
	}

	public function test_activation_throwable_becomes_inactive(): void {
		$handle = $this->registrar->register_extension(
			new ExtensionManifest( 'throws', '1.0.0', array( 'throws' ) )
		);
		$handle->register_meta(
			new ExtensionMetaDefinition(
				namespace: 'throws',
				source_type: Store::SOURCE_POST,
				meta_key: '_throws_meta',
				label: 'Throws',
				activation: static function (): bool {
					throw new \RuntimeException( 'boom' );
				},
			)
		);
		$this->registrar->seal();
		$def = $this->meta_registry->get( Store::SOURCE_POST, '_throws_meta' );
		$this->assertNotNull( $def );
		$this->assertFalse( $def->is_active() );
	}
}
