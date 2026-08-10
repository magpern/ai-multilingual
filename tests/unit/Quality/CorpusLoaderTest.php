<?php
/**
 * CorpusLoader unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Quality;

use AIMultilingual\Quality\CorpusLoader;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Quality\CorpusLoader
 */
final class CorpusLoaderTest extends TestCase {

	public function test_loads_c1_0_manifest_and_cases(): void {
		$loader = new CorpusLoader();
		$corpus = $loader->load( 'C1.0' );

		$this->assertSame( 'C1.0', $corpus['manifest']['corpus_version'] );
		$this->assertSame( 60, count( $corpus['cases'] ) );
		$this->assertArrayHasKey( 'glossary_fixture_version', $corpus['glossary'] );
		$this->assertArrayHasKey( 'html_01', $corpus['cases'] );
	}

	public function test_throws_on_missing_version(): void {
		$loader = new CorpusLoader();
		$this->expectException( \RuntimeException::class );
		$loader->load( 'C9.9' );
	}
}
