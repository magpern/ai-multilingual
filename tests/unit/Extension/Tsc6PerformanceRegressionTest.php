<?php
/**
 * TSC.6 extension registration performance characterization.
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
use AIMultilingual\Surface\Meta\RegisteredMetaRegistry;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Extension\ExtensionRegistrar
 */
final class Tsc6PerformanceRegressionTest extends TestCase {

	public function test_twenty_five_extensions_one_hundred_definitions(): void {
		$meta_registry = new RegisteredMetaRegistry();
		$registrar     = new ExtensionRegistrar(
			$meta_registry,
			new AdapterRegistry(),
			new ExtensionRegistry(),
			new ExtensionDiagnostics()
		);

		$start = microtime( true );
		for ( $i = 0; $i < 25; ++$i ) {
			$ns     = 'ext' . $i;
			$handle = $registrar->register_extension(
				new ExtensionManifest( 'extension_' . $i, '1.0.0', array( $ns ) )
			);
			for ( $j = 0; $j < 4; ++$j ) {
				$handle->register_meta(
					new ExtensionMetaDefinition(
						namespace: $ns,
						source_type: Store::SOURCE_POST,
						meta_key: '_meta_' . $i . '_' . $j,
						label: 'Field ' . $j,
					)
				);
			}
		}
		$registrar->seal();
		$elapsed = microtime( true ) - $start;

		$this->assertSame( 100, $meta_registry->count() );
		$this->assertLessThan( 2.0, $elapsed, 'Registration of 100 definitions should remain bounded.' );
	}
}
