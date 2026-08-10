<?php
/**
 * NumberNormalizer SV localization / corruption tests (TI.4 QD9).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\QA;

use AIMultilingual\Translation\QA\NumberNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Legitimate SV forms must not report missing signatures; true corruption must.
 */
final class NumberNormalizerTest extends TestCase {

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function legitimate_sv_cases(): array {
		return array(
			'decimal_comma'        => array( 'Dose is 1.5 ml', 'Dosen är 1,5 ml' ),
			'thousands_space'      => array( 'About 1,000 items', 'Cirka 1 000 artiklar' ),
			'thousands_dot'        => array( 'About 1,000 items', 'Cirka 1.000 artiklar' ),
			'percent'              => array( 'Save 50%', 'Spara 50%' ),
			'currency'             => array( 'Price $10.00', 'Pris 10,00 kr' ),
			'quantity_with_unit'   => array( 'Add 5 kg flour', 'Tillsätt 5 kg mjöl' ),
			'punctuation_adjacent' => array( 'See (3) above', 'Se (3) ovan' ),
			'range_hyphen'         => array( 'Use 10-20 drops', 'Använd 10-20 droppar' ),
			'range_en_dash'        => array( 'Use 10-20 drops', 'Använd 10–20 droppar' ),
		);
	}

	/**
	 * @dataProvider legitimate_sv_cases
	 */
	public function test_legitimate_sv_localization_has_no_missing_signatures( string $source, string $target ): void {
		$this->assertSame(
			array(),
			NumberNormalizer::missing_signatures( $source, $target ),
			sprintf( 'Unexpected missing signatures for %s → %s', $source, $target )
		);
	}

	public function test_true_corruption_reports_missing_signature(): void {
		$missing = NumberNormalizer::missing_signatures( 'Order number 42', 'Order number 99' );

		$this->assertContains( '42', $missing );
		$this->assertNotContains( '99', $missing );
	}
}
