<?php
/**
 * Strategy F adapter registry.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\Adapter\ButtonAdapter;
use AIMultilingual\Block\Adapter\HeadingAdapter;
use AIMultilingual\Block\Adapter\ParagraphAdapter;
use AIMultilingual\Block\AdapterRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Adapter registry lookup and ownership.
 */
final class AdapterRegistryTest extends TestCase {

	public function test_registers_proof_block_adapters(): void {
		$registry = new AdapterRegistry();

		$this->assertSame(
			array( 'core/paragraph', 'core/heading', 'core/button' ),
			$registry->block_names()
		);
	}

	public function test_lookup_returns_adapter_for_supported_blocks(): void {
		$registry = new AdapterRegistry();

		$this->assertInstanceOf( ParagraphAdapter::class, $registry->get( 'core/paragraph' ) );
		$this->assertInstanceOf( HeadingAdapter::class, $registry->get( 'core/heading' ) );
		$this->assertInstanceOf( ButtonAdapter::class, $registry->get( 'core/button' ) );
	}

	public function test_lookup_returns_null_for_unsupported_blocks(): void {
		$registry = new AdapterRegistry();

		$this->assertNull( $registry->get( 'core/group' ) );
		$this->assertNull( $registry->get( 'core/latest-posts' ) );
	}

	public function test_duplicate_registration_is_rejected(): void {
		$registry = new AdapterRegistry();

		$this->expectException( \InvalidArgumentException::class );
		$registry->register( new ParagraphAdapter() );
	}
}
