<?php
/**
 * IntegrationRegistry unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Integration\CompatibilityStatus;
use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\IntegrationDiagnostics;
use AIMultilingual\Integration\IntegrationRegistry;
use AIMultilingual\Integration\PluginIntegrationInterface;
use AIMultilingual\Integration\TranslationUnitDescriptor;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * @covers \AIMultilingual\Integration\IntegrationRegistry
 */
final class IntegrationRegistryTest extends TestCase {

	public function test_registers_and_rejects_duplicates(): void {
		$registry = new IntegrationRegistry();
		$registry->register( $this->stub( 'alpha' ) );
		$this->assertSame( 'v1', $registry->api_version() );
		$this->assertCount( 1, $registry->all() );
		$this->expectException( \InvalidArgumentException::class );
		$registry->register( $this->stub( 'alpha' ) );
	}

	public function test_rejects_malformed_id(): void {
		$registry = new IntegrationRegistry();
		$this->expectException( \InvalidArgumentException::class );
		$registry->register( $this->stub( 'BadID' ) );
	}

	public function test_registration_order_is_deterministic(): void {
		$registry = new IntegrationRegistry();
		$registry->register( $this->stub( 'a' ) );
		$registry->register( $this->stub( 'b' ) );
		$ids = array_map( static fn( $i ) => $i->get_id(), $registry->all() );
		$this->assertSame( array( 'a', 'b' ), $ids );
	}

	public function test_empty_registry_is_noop(): void {
		$registry = new IntegrationRegistry();
		$this->assertTrue( $registry->is_empty() );
		$post = new WP_Post();
		$this->assertSame( array(), $registry->extract_for_post( $post ) );
	}

	public function test_incompatible_integration_skips_extraction(): void {
		$diag     = new IntegrationDiagnostics();
		$registry = new IntegrationRegistry( $diag );
		$registry->register( $this->stub( 'gone', false ) );
		$post = new WP_Post();
		$this->assertSame( array(), $registry->extract_for_post( $post ) );
		$snap = $diag->snapshot();
		$this->assertSame( 1, $snap[ IntegrationDiagnostics::COUNTER_INTEGRATION_INCOMPATIBLE ] ?? 0 );
	}

	/**
	 * @param string $id       ID.
	 * @param bool   $compatible Compatible.
	 */
	private function stub( string $id, bool $compatible = true ): PluginIntegrationInterface {
		return new class( $id, $compatible ) implements PluginIntegrationInterface {
			public function __construct( private string $id, private bool $compatible ) {
			}
			public function get_id(): string {
				return $this->id;
			}
			public function get_api_version(): string {
				return Contract::API_VERSION;
			}
			public function get_compatibility(): CompatibilityStatus {
				return new CompatibilityStatus(
					$this->compatible ? Contract::STATE_COMPATIBLE : Contract::STATE_UNAVAILABLE,
					$this->compatible ? 'ok' : 'missing'
				);
			}
			public function extract_for_post( WP_Post $post ): array {
				return array();
			}
			public function register_output_hooks( callable $resolve ): void {
			}
		};
	}
}
