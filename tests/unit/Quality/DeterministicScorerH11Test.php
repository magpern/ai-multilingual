<?php
/**
 * DeterministicScorerH11 unit tests (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Quality;

use AIMultilingual\Quality\CorpusLoader;
use AIMultilingual\Quality\DeterministicScorerH11;
use AIMultilingual\Translation\QA\MeasurementH11Policy;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Quality\DeterministicScorerH11
 */
final class DeterministicScorerH11Test extends TestCase {

	private DeterministicScorerH11 $scorer;

	private array $glossary;

	private const MARKER = 'Glossary instructions (terminology guidance only — do not copy into the translation output):';

	protected function setUp(): void {
		parent::setUp();
		$this->scorer   = new DeterministicScorerH11();
		$this->glossary = ( new CorpusLoader() )->load( 'C1.3' )['glossary'];
	}

	public function test_historical_markers_not_applicable_does_not_fail_pass(): void {
		// gut_01-style Swedish glossary prose without captured markers (Outcome C).
		$case   = array(
			'source_text' => 'How we package research materials',
			'text_format' => 'plain',
			'case_class'  => 'free',
		);
		$target = "Hur vi paketerar forskningsmaterial\nOrdlista över terminologi (använd konsekvent):\nresearch => forskning";
		$result = $this->scorer->score_case(
			$case,
			$target,
			null,
			array( 'markers_applicable' => false )
		);

		$codes = array_column( $result['findings'], 'code' );
		$this->assertContains( MeasurementH11Policy::CODE_LEAKAGE_NOT_APPLICABLE, $codes );
		$this->assertNotContains( 'qd3_scaffolding_leakage', $codes );
		$this->assertGreaterThanOrEqual( 1, $result['not_applicable_count'] );
		$this->assertSame( 0, $result['critical_count'] );
		$this->assertTrue( $result['pass'] );
	}

	public function test_not_applicable_is_not_pass_failure(): void {
		$case   = array(
			'source_text' => 'Hello world product',
			'text_format' => 'plain',
			'case_class'  => 'free',
		);
		$result = $this->scorer->score_case(
			$case,
			'Hej värld produkt',
			null,
			array( 'markers_applicable' => false )
		);

		$this->assertGreaterThanOrEqual( 1, $result['not_applicable_count'] );
		$this->assertSame( 0, $result['critical_count'] );
		$this->assertTrue( $result['pass'] );
	}

	public function test_markers_with_leak_is_critical_fail(): void {
		$case   = array(
			'source_text' => 'How we package research materials',
			'text_format' => 'plain',
			'case_class'  => 'free',
		);
		$target = 'Hur vi paketerar forskningsmaterial ' . self::MARKER;
		$result = $this->scorer->score_case(
			$case,
			$target,
			null,
			array(
				'markers_applicable'  => true,
				'scaffolding_markers' => array( self::MARKER ),
			)
		);

		$codes = array_column( $result['findings'], 'code' );
		$this->assertContains( 'qd3_scaffolding_leakage', $codes );
		$this->assertGreaterThanOrEqual( 1, $result['critical_count'] );
		$this->assertFalse( $result['pass'] );
	}

	public function test_sv_number_legit_passes(): void {
		$case   = array(
			'source_text' => 'Dose is 1.5 ml',
			'text_format' => 'plain',
			'case_class'  => 'structural',
		);
		$result = $this->scorer->score_case( $case, 'Dosen är 1,5 ml' );

		$codes = array_column( $result['findings'], 'code' );
		$this->assertNotContains( 'qd9_number_corruption', $codes );
		$this->assertSame( 0, $result['critical_count'] );
		$this->assertTrue( $result['pass'] );
	}

	public function test_c13_leak_scaffold_case_via_corpus(): void {
		$corpus = ( new CorpusLoader() )->load( 'C1.3' );
		$case   = $corpus['cases']['leak_scaffold_01'];
		$result = $this->scorer->score_case(
			$case,
			'Hur vi paketerar ' . self::MARKER,
			$corpus['glossary'],
			array(
				'markers_applicable'  => true,
				'scaffolding_markers' => array( self::MARKER ),
				'source_locale'       => 'en_US',
				'target_locale'       => 'sv_SE',
			)
		);
		$this->assertFalse( $result['pass'] );
		$this->assertContains( 'qd3_scaffolding_leakage', array_column( $result['findings'], 'code' ) );
	}

	public function test_glossary_miss_is_error_not_critical(): void {
		$corpus = ( new CorpusLoader() )->load( 'C1.3' );
		$case   = $corpus['cases']['gloss_miss_01'];
		$result = $this->scorer->score_case(
			$case,
			'Our research peptide is freeze-dried.',
			$this->glossary
		);
		$this->assertTrue( $result['pass'] );
		$this->assertGreaterThanOrEqual( 1, $result['error_count'] );
		$this->assertContains( 'qd16_glossary_term_missing', array_column( $result['findings'], 'code' ) );
	}
}
