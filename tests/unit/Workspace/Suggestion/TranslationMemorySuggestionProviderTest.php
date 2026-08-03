<?php
/**
 * TranslationMemorySuggestionProvider unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Workspace\Suggestion;

use AIMultilingual\Translation\Memory\TMRepository;
use AIMultilingual\Workspace\Suggestion\TranslationMemorySuggestionProvider;
use PHPUnit\Framework\TestCase;

/**
 * TM provider rank-tier mapping (final TMS is covered in integration).
 */
final class TranslationMemorySuggestionProviderTest extends TestCase {

	public function test_exact_hit_maps_to_tier_one(): void {
		$tier = TranslationMemorySuggestionProvider::rank_tier_for_hit(
			array(
				'match_type' => 'exact',
				'origin'     => TMRepository::ORIGIN_HUMAN,
				'quality'    => TMRepository::QUALITY_HUMAN_APPROVED,
			),
			false
		);

		$this->assertSame( TranslationMemorySuggestionProvider::TIER_EXACT_TM, $tier );
	}

	public function test_fuzzy_hit_maps_to_tier_five(): void {
		$tier = TranslationMemorySuggestionProvider::rank_tier_for_hit(
			array(
				'match_type' => 'fuzzy',
				'origin'     => TMRepository::ORIGIN_HUMAN,
				'quality'    => TMRepository::QUALITY_HUMAN_APPROVED,
			),
			true
		);

		$this->assertSame( TranslationMemorySuggestionProvider::TIER_FUZZY_TM, $tier );
	}

	public function test_import_global_maps_to_tier_four(): void {
		$tier = TranslationMemorySuggestionProvider::rank_tier_for_hit(
			array(
				'match_type' => 'exact_global',
				'origin'     => TMRepository::ORIGIN_IMPORT,
				'quality'    => TMRepository::QUALITY_HUMAN_APPROVED,
			),
			false
		);

		$this->assertSame( TranslationMemorySuggestionProvider::TIER_IMPORTED_TM, $tier );
	}

	public function test_reviewed_human_global_maps_to_tier_two(): void {
		$tier = TranslationMemorySuggestionProvider::rank_tier_for_hit(
			array(
				'match_type' => 'exact_global',
				'origin'     => TMRepository::ORIGIN_HUMAN,
				'quality'    => TMRepository::QUALITY_HUMAN_APPROVED,
			),
			false
		);

		$this->assertSame( TranslationMemorySuggestionProvider::TIER_REVIEWED_HUMAN_TM, $tier );
	}

	public function test_provider_id_is_tm(): void {
		$this->assertSame( 'tm', TranslationMemorySuggestionProvider::ID );
	}
}
