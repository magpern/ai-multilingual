<?php
/**
 * FrozenEvidenceGuard unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Quality;

use AIMultilingual\Quality\EvidencePack;
use AIMultilingual\Quality\FrozenEvidenceGuard;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Quality\FrozenEvidenceGuard
 */
final class FrozenEvidenceGuardTest extends TestCase {

	private string $tmp;

	protected function setUp(): void {
		parent::setUp();
		$this->tmp = sys_get_temp_dir() . '/aiml-guard-' . uniqid( '', true );
		mkdir( $this->tmp, 0755, true );
	}

	protected function tearDown(): void {
		$this->remove_dir( $this->tmp );
		parent::tearDown();
	}

	public function test_passes_when_no_fingerprints(): void {
		file_put_contents( $this->tmp . '/manifest.json', "{}\n" );
		$result = ( new FrozenEvidenceGuard() )->verify( new EvidencePack( $this->tmp ) );
		$this->assertTrue( $result['ok'] );
	}

	public function test_detects_mutation(): void {
		file_put_contents( $this->tmp . '/manifest.json', "{\"a\":1}\n" );
		file_put_contents( $this->tmp . '/generations.jsonl', "{}\n" );

		$guard = new FrozenEvidenceGuard();
		$guard->write_fingerprints( new EvidencePack( $this->tmp ) );

		file_put_contents( $this->tmp . '/manifest.json', "{\"a\":2}\n" );
		$result = $guard->verify( new EvidencePack( $this->tmp ) );

		$this->assertFalse( $result['ok'] );
		$this->assertNotEmpty( $result['violations'] );
		$this->assertSame( 'manifest.json', $result['violations'][0]['file'] );
	}

	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) ?: array() as $item ) {
			if ( in_array( $item, array( '.', '..' ), true ) ) {
				continue;
			}
			$path = $dir . '/' . $item;
			is_dir( $path ) ? $this->remove_dir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}
}
