<?php
/**
 * Glossary normalizer and matcher unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Glossary;

use AIMultilingual\Glossary\GlossaryMatcher;
use AIMultilingual\Glossary\GlossaryNormalizer;
use AIMultilingual\Glossary\GlossaryTermMatch;
use Normalizer;
use PHPUnit\Framework\TestCase;

/**
 * Unicode normalization and whole-word matching.
 */
final class GlossaryNormalizerMatcherTest extends TestCase {

	private GlossaryNormalizer $normalizer;

	private GlossaryMatcher $matcher;

	protected function setUp(): void {
		parent::setUp();
		$this->normalizer = new GlossaryNormalizer();
		$this->matcher    = new GlossaryMatcher( $this->normalizer );
	}

	public function test_normalize_preserves_swedish_diacritics_and_folds_case(): void {
		if ( ! class_exists( Normalizer::class ) ) {
			$this->markTestSkipped( 'ext-intl required' );
		}
		$this->assertSame( 'åäö', $this->normalizer->normalize_source( '  ÅÄÖ  ' ) );
	}

	public function test_normalize_collapses_whitespace(): void {
		if ( ! class_exists( Normalizer::class ) ) {
			$this->markTestSkipped( 'ext-intl required' );
		}
		$this->assertSame( 'hello world', $this->normalizer->normalize_source( "Hello   \n World" ) );
	}

	public function test_exact_segment_match(): void {
		$term    = (object) array(
			'glossary_id'            => 1,
			'source_term'            => 'Peptide',
			'source_term_normalized' => 'peptide',
			'target_term'            => 'Peptid',
			'context'                => '',
		);
		$matches = $this->matcher->match( 'Peptide', array( $term ) );
		$this->assertCount( 1, $matches );
		$this->assertTrue( $matches[0]->is_exact_segment() );
		$this->assertSame( 'Peptid', $matches[0]->target_term );
	}

	public function test_embedded_match_not_inside_larger_word(): void {
		$term = (object) array(
			'glossary_id'            => 2,
			'source_term'            => 'age',
			'source_term_normalized' => 'age',
			'target_term'            => 'ålder',
			'context'                => '',
		);
		$this->assertSame( array(), $this->matcher->match( 'The agent arrived', array( $term ) ) );
		$hits = $this->matcher->match( 'The age of reason', array( $term ) );
		$this->assertCount( 1, $hits );
		$this->assertSame( GlossaryTermMatch::KIND_EMBEDDED, $hits[0]->match_kind );
	}

	public function test_longest_term_wins_on_overlap(): void {
		$short = (object) array(
			'glossary_id'            => 1,
			'source_term'            => 'anti',
			'source_term_normalized' => 'anti',
			'target_term'            => 'anti-sv',
			'context'                => '',
		);
		$long  = (object) array(
			'glossary_id'            => 2,
			'source_term'            => 'anti-aging',
			'source_term_normalized' => 'anti-aging',
			'target_term'            => 'anti-aging-sv',
			'context'                => '',
		);
		$hits  = $this->matcher->match( 'Try anti-aging serum', array( $short, $long ) );
		$ids   = array_map( static fn( GlossaryTermMatch $m ): int => $m->glossary_id, $hits );
		$this->assertContains( 2, $ids );
		$this->assertNotContains( 1, $ids );
	}

	public function test_hyphenated_term_is_literal(): void {
		$term = (object) array(
			'glossary_id'            => 3,
			'source_term'            => 'anti-aging',
			'source_term_normalized' => 'anti-aging',
			'target_term'            => 'x',
			'context'                => '',
		);
		$this->assertSame( array(), $this->matcher->match( 'anti aging', array( $term ) ) );
	}
}
