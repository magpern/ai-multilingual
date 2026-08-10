<?php
/**
 * Unit tests for TI.3 TM domain allowlist and eligibility.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\Memory;

use AIMultilingual\Translation\Memory\TMDomainAllowlist;
use AIMultilingual\Translation\Memory\TMEligibilityPolicy;
use AIMultilingual\Translation\Memory\TMGenerationOutcome;
use AIMultilingual\Translation\Memory\TMRepository;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * Pure eligibility policy tests (no DB).
 */
final class TMEligibilityPolicyTest extends TestCase {

	public function test_domain_allowlist_public_content(): void {
		$this->assertTrue( TMDomainAllowlist::is_eligible( 'post' ) );
		$this->assertTrue( TMDomainAllowlist::is_eligible( 'page' ) );
		$this->assertTrue( TMDomainAllowlist::is_eligible( 'product' ) );
	}

	public function test_domain_allowlist_denies_orders_and_private(): void {
		$this->assertFalse( TMDomainAllowlist::is_eligible( 'shop_order' ) );
		$this->assertFalse( TMDomainAllowlist::is_eligible( 'customer' ) );
		$this->assertFalse( TMDomainAllowlist::is_eligible( '' ) );
		$this->assertFalse( TMDomainAllowlist::is_eligible( 'unknown_cpt' ) );
	}

	public function test_rejects_non_human_approved_quality(): void {
		$policy = new TMEligibilityPolicy();
		$source = 'This is a long enough product description for hashing.';
		$hash   = Store::source_hash( $source, Store::FORMAT_PLAIN );
		$result = $policy->evaluate_candidate(
			array(
				'tm_id'            => 1,
				'source_hash'      => $hash,
				'target_text'      => 'SV target',
				'quality'          => 'machine',
				'norm_version'     => Store::NORM_VERSION,
				'text_format'      => Store::FORMAT_PLAIN,
				'glossary_version' => 0,
				'match_type'       => 'exact',
			),
			$source,
			Store::FORMAT_PLAIN,
			1,
			2
		);

		$this->assertFalse( $result['ok'] );
		$this->assertSame( TMGenerationOutcome::INELIGIBLE, $result['code'] );
	}

	public function test_accepts_human_approved_exact_hash(): void {
		$policy = new TMEligibilityPolicy();
		$source = 'This is a long enough product description for hashing.';
		$hash   = Store::source_hash( $source, Store::FORMAT_PLAIN );
		$result = $policy->evaluate_candidate(
			array(
				'tm_id'            => 9,
				'source_hash'      => $hash,
				'target_text'      => 'SV target',
				'quality'          => TMRepository::QUALITY_HUMAN_APPROVED,
				'norm_version'     => Store::NORM_VERSION,
				'text_format'      => Store::FORMAT_PLAIN,
				'glossary_version' => 0,
				'match_type'       => 'exact',
			),
			$source,
			Store::FORMAT_PLAIN,
			1,
			2
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 9, $result['diagnostics']['tm_id'] );
	}

	public function test_rejects_hash_mismatch(): void {
		$policy = new TMEligibilityPolicy();
		$result = $policy->evaluate_candidate(
			array(
				'tm_id'            => 1,
				'source_hash'      => str_repeat( 'a', 40 ),
				'target_text'      => 'SV',
				'quality'          => TMRepository::QUALITY_HUMAN_APPROVED,
				'norm_version'     => Store::NORM_VERSION,
				'text_format'      => Store::FORMAT_PLAIN,
				'glossary_version' => 0,
			),
			'Different source text here for sure.',
			Store::FORMAT_PLAIN,
			1,
			2
		);

		$this->assertFalse( $result['ok'] );
	}
}
