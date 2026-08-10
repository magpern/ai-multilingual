<?php
/**
 * CorpusValidator unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Quality;

use AIMultilingual\Quality\CorpusValidator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Quality\CorpusValidator
 */
final class CorpusValidatorTest extends TestCase {

	public function test_c1_0_passes_validation(): void {
		$result = ( new CorpusValidator() )->validate( 'C1.0' );
		$this->assertTrue( $result['ok'], implode( '; ', $result['errors'] ) );
		$this->assertSame( 60, array_sum( $result['category_counts'] ) );
	}

	public function test_detects_secret_patterns_in_custom_corpus(): void {
		$tmp = sys_get_temp_dir() . '/aiml-quality-' . uniqid( '', true );
		mkdir( $tmp . '/corpus/BAD', 0755, true );
		mkdir( $tmp . '/corpus/BAD/cases', 0755, true );
		file_put_contents(
			$tmp . '/corpus/BAD/manifest.json',
			wp_json_encode(
				array(
					'corpus_version'      => 'BAD',
					'methodology_version' => 'M1.0',
					'source_locale'       => 'en_US',
					'target_locale'       => 'sv_SE',
					'case_count'          => 1,
					'case_ids'            => array( 'bad_01' ),
				)
			)
		);
		file_put_contents(
			$tmp . '/corpus/BAD/glossary.json',
			wp_json_encode(
				array(
					'glossary_fixture_version' => 'G1.0',
					'terms'                    => array(),
				)
			)
		);
		file_put_contents(
			$tmp . '/corpus/BAD/cases/bad_01.json',
			wp_json_encode(
				array(
					'id'                  => 'bad_01',
					'category'            => 'protected',
					'case_class'          => 'protected',
					'text_format'         => 'plain',
					'field_semantics'     => 'body',
					'source_text'         => 'key sk-abcdefghijklmnopqrstuvwxyz1234567890',
					'expected_invariants' => array(),
					'difficulty'          => 'hard',
				)
			)
		);

		$result = ( new CorpusValidator( new \AIMultilingual\Quality\CorpusLoader( $tmp ) ) )->validate( 'BAD' );
		$this->assertFalse( $result['ok'] );
		$this->assertNotEmpty( $result['errors'] );

		$this->remove_dir( $tmp );
	}

	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( false === $items ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( in_array( $item, array( '.', '..' ), true ) ) {
				continue;
			}
			$path = $dir . '/' . $item;
			is_dir( $path ) ? $this->remove_dir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}
}
