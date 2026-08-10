<?php
/**
 * FixtureGlossaryFragmentBuilder unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Quality;

use AIMultilingual\Glossary\GlossaryService;
use AIMultilingual\Quality\CorpusLoader;
use AIMultilingual\Quality\FixtureGlossaryFragmentBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Quality\FixtureGlossaryFragmentBuilder
 */
final class FixtureGlossaryFragmentBuilderTest extends TestCase {

	public function test_empty_when_no_matches(): void {
		$glossary = ( new CorpusLoader() )->load( 'C1.0' )['glossary'];
		$builder  = new FixtureGlossaryFragmentBuilder();
		$this->assertSame( '', $builder->build( 'Hello world', $glossary ) );
	}

	public function test_longest_match_first(): void {
		$glossary = array(
			'terms' => array(
				array( 'id' => 'a', 'source' => 'peptide', 'target' => 'peptid' ),
				array( 'id' => 'b', 'source' => 'peptide complex', 'target' => 'peptidkomplex' ),
			),
		);
		$fragment = ( new FixtureGlossaryFragmentBuilder() )->build( 'peptide complex formula', $glossary );
		$lines    = explode( "\n", $fragment );
		$this->assertSame( 'peptide complex => peptidkomplex', $lines[0] );
	}

	public function test_truncation_marker_at_term_limit(): void {
		$terms = array();
		for ( $i = 1; $i <= FixtureGlossaryFragmentBuilder::FRAGMENT_MAX_TERMS + 2; $i++ ) {
			$terms[] = array(
				'id'     => 't' . $i,
				'source' => 'term' . $i,
				'target' => 'mal' . $i,
			);
		}
		$source   = implode( ' ', array_map( static fn( $t ) => $t['source'], $terms ) );
		$fragment = ( new FixtureGlossaryFragmentBuilder() )->build( $source, array( 'terms' => $terms ) );
		$this->assertStringContainsString( GlossaryService::FRAGMENT_TRUNCATION_MARKER, $fragment );
	}
}
