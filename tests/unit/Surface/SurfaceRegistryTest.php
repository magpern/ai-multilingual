<?php
/**
 * SurfaceRegistry unit tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Surface;

use AIMultilingual\Surface\PostSurfaceAdapter;
use AIMultilingual\Surface\RequestLocalInvalidationCoordinator;
use AIMultilingual\Surface\SurfaceCapability;
use AIMultilingual\Surface\SurfaceCapabilityNames;
use AIMultilingual\Surface\SurfaceRegistry;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Surface\SurfaceRegistry
 */
final class SurfaceRegistryTest extends TestCase {

	public function test_register_for_require_and_supports(): void {
		$registry = new SurfaceRegistry();
		$adapter  = new PostSurfaceAdapter();

		$this->assertFalse( $registry->is_registered( Store::SOURCE_POST ) );
		$this->assertNull( $registry->for( Store::SOURCE_POST ) );

		$registry->register( $adapter );

		$this->assertTrue( $registry->is_registered( Store::SOURCE_POST ) );
		$this->assertSame( $adapter, $registry->for( Store::SOURCE_POST ) );
		$this->assertSame( $adapter, $registry->require( Store::SOURCE_POST ) );
		$this->assertTrue( $registry->supports( Store::SOURCE_POST, SurfaceCapabilityNames::JOBS ) );
		$this->assertFalse( $registry->supports( Store::SOURCE_POST, 'not_a_capability' ) );
		$this->assertSame( array( Store::SOURCE_POST ), $registry->registered_types() );
	}

	public function test_require_throws_for_unregistered_source_type(): void {
		$registry = new SurfaceRegistry();

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Unregistered source_type.' );
		$registry->require( 'term' );
	}

	public function test_register_overwrites_same_source_type(): void {
		$registry = new SurfaceRegistry();
		$first    = new PostSurfaceAdapter();
		$second   = $this->stub_capability( Store::SOURCE_POST );

		$registry->register( $first );
		$registry->register( $second );

		$this->assertSame( $second, $registry->for( Store::SOURCE_POST ) );
		$this->assertCount( 1, $registry->registered_types() );
	}

	/**
	 * @param string $source_type Source type.
	 */
	private function stub_capability( string $source_type ): SurfaceCapability {
		return new class( $source_type ) implements SurfaceCapability {
			public function __construct( private string $type ) {
			}

			public function source_type(): string {
				return $this->type;
			}

			public function exists( int $source_id ): bool {
				return false;
			}

			public function source_subtype( int $source_id ): string {
				return '';
			}

			public function user_can_edit_source( int $user_id, int $source_id ): bool {
				return false;
			}

			public function is_visitor_public( int $source_id ): bool {
				return false;
			}

			public function supports( string $capability ): bool {
				return false;
			}

			public function feature_implemented( string $feature ): bool {
				return false;
			}

			public function feature_activated( string $feature ): bool {
				return false;
			}

			public function register_invalidation_events( RequestLocalInvalidationCoordinator $coordinator ): void {
			}
		};
	}
}
