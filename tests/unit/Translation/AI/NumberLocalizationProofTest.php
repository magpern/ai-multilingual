<?php
/**
 * TI.1 TS7 Swedish number-localization proof suite.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\AI;

use AIMultilingual\Translation\AI\ResponseValidator;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * Evidence that literal number substring matching false-positives on legitimate SV forms.
 *
 * Outcome B: narrow persist — omit `numbers` from persist constraints.
 */
final class NumberLocalizationProofTest extends TestCase {

	/**
	 * Legitimate EN→SV localization cases that must not be blocked on persist.
	 *
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
	public function test_default_analyzer_false_positives_on_sv_localization( string $source, string $target ): void {
		$validator = new ResponseValidator();
		$result    = $validator->validate( $source, $target, Store::FORMAT_PLAIN );
		$analysis  = $validator->analyzer()->analyze( $source, Store::FORMAT_PLAIN );

		if ( in_array( 'numbers', $analysis['constraints'], true ) ) {
			// Document which cases the literal matcher rejects.
			if ( ! $result->valid ) {
				$this->assertSame( ResponseValidator::CODE_NUMBER_MISMATCH, $result->code );
			}
		}

		// Persist constraints must accept the same target (TS7 narrow).
		$persist = $validator->validate(
			$source,
			$target,
			Store::FORMAT_PLAIN,
			$validator->persist_constraints( $source, Store::FORMAT_PLAIN )
		);
		$this->assertTrue(
			$persist->valid,
			sprintf( 'Persist must accept legitimate SV localization: %s → %s', $source, $target )
		);
	}

	public function test_true_number_corruption_still_detectable_on_suggest_path(): void {
		$validator = new ResponseValidator();
		$result    = $validator->validate(
			'Order number 42',
			'Order number 99',
			Store::FORMAT_PLAIN
		);

		$this->assertFalse( $result->valid );
		$this->assertSame( ResponseValidator::CODE_NUMBER_MISMATCH, $result->code );
	}

	public function test_persist_omits_number_block_even_for_corruption(): void {
		// Intentionally: after TS7 narrow, persist does not BLOCK on number loss.
		$validator = new ResponseValidator();
		$result    = $validator->validate(
			'Order number 42',
			'Order number 99',
			Store::FORMAT_PLAIN,
			$validator->persist_constraints( 'Order number 42', Store::FORMAT_PLAIN )
		);

		$this->assertTrue( $result->valid );
		$this->assertTrue( ResponseValidator::PERSIST_OMIT_NUMBER_CONSTRAINTS );
	}
}
