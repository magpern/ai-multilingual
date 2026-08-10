<?php
/**
 * DeterministicScorer unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Quality;

use AIMultilingual\Quality\CorpusLoader;
use AIMultilingual\Quality\DeterministicScorer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Quality\DeterministicScorer
 */
final class DeterministicScorerTest extends TestCase {

	private DeterministicScorer $scorer;

	private array $glossary;

	protected function setUp(): void {
		parent::setUp();
		$this->scorer  = new DeterministicScorer();
		$this->glossary = ( new CorpusLoader() )->load( 'C1.0' )['glossary'];
	}

	public function test_empty_translation_is_critical(): void {
		$case = array(
			'source_text' => 'Hello world',
			'text_format' => 'plain',
			'case_class'  => 'free',
		);
		$result = $this->scorer->score_case( $case, '' );
		$this->assertFalse( $result['pass'] );
		$this->assertSame( 1, $result['critical_count'] );
		$this->assertSame( 'empty_translation', $result['findings'][0]['code'] );
	}

	public function test_placeholder_loss_is_critical(): void {
		$case = array(
			'source_text'         => 'Order {order_number} ready',
			'text_format'         => 'plain',
			'case_class'          => 'structural',
			'expected_invariants' => array( 'placeholders' => array( '{order_number}' ) ),
		);
		$result = $this->scorer->score_case( $case, 'Order ready' );
		$this->assertFalse( $result['pass'] );
		$this->assertSame( 'placeholder_loss', $result['findings'][0]['code'] );
	}

	public function test_html_tag_loss_is_critical(): void {
		$case = array(
			'source_text' => '<p>Hello</p>',
			'text_format' => 'html',
			'case_class'  => 'structural',
		);
		$result = $this->scorer->score_case( $case, 'Hello' );
		$this->assertFalse( $result['pass'] );
		$this->assertSame( 'html_tag_loss', $result['findings'][0]['code'] );
	}

	public function test_number_corruption_is_error(): void {
		$case = array(
			'source_text'         => 'Dose 250 mcg',
			'text_format'         => 'plain',
			'case_class'          => 'structural',
			'expected_invariants' => array( 'numbers' => array( '250' ) ),
		);
		$result = $this->scorer->score_case( $case, 'Dose mcg' );
		$this->assertTrue( $result['pass'] );
		$this->assertSame( 1, $result['error_count'] );
		$this->assertSame( 'number_corruption', $result['findings'][0]['code'] );
	}

	public function test_sku_corruption_is_critical(): void {
		$case = array(
			'source_text'         => 'SKU: PEP-BPC157-10MG',
			'text_format'         => 'plain',
			'case_class'          => 'protected',
			'expected_invariants' => array( 'skus' => array( 'PEP-BPC157-10MG' ) ),
		);
		$result = $this->scorer->score_case( $case, 'SKU: PEP-BPC157' );
		$this->assertFalse( $result['pass'] );
		$codes = array_column( $result['findings'], 'code' );
		$this->assertContains( 'sku_corruption', $codes );
	}

	public function test_glossary_compliance_error(): void {
		$case = array(
			'source_text'         => 'This peptide is lyophilized.',
			'text_format'         => 'plain',
			'case_class'          => 'terminology',
			'expected_invariants' => array( 'glossary_term_ids' => array( 'peptide', 'lyophilized' ) ),
		);
		$result = $this->scorer->score_case( $case, 'This peptide is freeze-dried.', $this->glossary );
		$this->assertTrue( $result['pass'] );
		$this->assertGreaterThanOrEqual( 1, $result['error_count'] );
		$this->assertSame( 'glossary_compliance', $result['findings'][0]['code'] );
	}

	public function test_unicode_damage_is_error(): void {
		$case = array(
			'source_text' => 'Café',
			'text_format' => 'plain',
			'case_class'  => 'free',
		);
		$result = $this->scorer->score_case( $case, "Caf\u{FFFD}" );
		$this->assertSame( 'unicode_damage', $result['findings'][0]['code'] );
	}

	public function test_length_ratio_warning(): void {
		$case = array(
			'source_text' => 'This is a reasonably long product description for testing.',
			'text_format' => 'plain',
			'case_class'  => 'free',
		);
		$result = $this->scorer->score_case( $case, 'Hej' );
		$codes  = array_column( $result['findings'], 'code' );
		$this->assertContains( 'length_ratio', $codes );
	}

	public function test_clean_translation_passes(): void {
		$case = array(
			'source_text'         => 'Your order {order_number} has been received.',
			'text_format'         => 'plain',
			'case_class'          => 'structural',
			'expected_invariants' => array( 'placeholders' => array( '{order_number}' ) ),
		);
		$result = $this->scorer->score_case( $case, 'Din order {order_number} har mottagits.' );
		$this->assertTrue( $result['pass'] );
		$this->assertSame( 0, $result['critical_count'] );
	}

	public function test_multiple_findings(): void {
		$case = array(
			'source_text'         => 'SKU: PEP-BPC157-10MG order {order_number}',
			'text_format'         => 'plain',
			'case_class'          => 'protected',
			'expected_invariants' => array(
				'skus'         => array( 'PEP-BPC157-10MG' ),
				'placeholders' => array( '{order_number}' ),
			),
		);
		$result = $this->scorer->score_case( $case, 'SKU missing' );
		$this->assertFalse( $result['pass'] );
		$this->assertGreaterThanOrEqual( 2, count( $result['findings'] ) );
	}
}
