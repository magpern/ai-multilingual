<?php
/**
 * TranslationSuggestionService unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Workspace;

use AIMultilingual\Workspace\Suggestion\NormalizedSuggestion;
use AIMultilingual\Workspace\Suggestion\SuggestionProvider;
use AIMultilingual\Workspace\TranslationSuggestionService;
use PHPUnit\Framework\TestCase;

/**
 * Ranking and merge behaviour for suggestion orchestration.
 */
final class TranslationSuggestionServiceTest extends TestCase {

	public function test_rank_orders_by_tier_then_confidence_then_text(): void {
		$provider = new class() implements SuggestionProvider {
			public function get_id(): string {
				return 'tm';
			}

			public function is_available( array $segment_dto, array $context ): bool {
				return true;
			}

			public function get_unavailable_reason(): ?string {
				return null;
			}

			public function get_suggestions( array $segment_dto, array $context ): array {
				return array(
					new NormalizedSuggestion( 'tm', 'Zebra', 90.0, 5 ),
					new NormalizedSuggestion( 'tm', 'Alpha', 80.0, 1 ),
					new NormalizedSuggestion( 'tm', 'Beta', 95.0, 5 ),
					new NormalizedSuggestion( 'tm', 'Alpha dup', 99.0, 1 ),
				);
			}
		};

		$service = new TranslationSuggestionService( array( $provider ) );
		$ranked  = $service->suggestions_for_segment( array( 'segment_key' => 'a' ), array() );

		$this->assertSame( 'Alpha dup', $ranked[0]['target_text'] );
		$this->assertSame( 'Alpha', $ranked[1]['target_text'] );
		$this->assertSame( 'Beta', $ranked[2]['target_text'] );
		$this->assertSame( 'Zebra', $ranked[3]['target_text'] );
	}

	public function test_duplicate_target_text_keeps_higher_tier(): void {
		$provider = new class() implements SuggestionProvider {
			public function get_id(): string {
				return 'tm';
			}

			public function is_available( array $segment_dto, array $context ): bool {
				return true;
			}

			public function get_unavailable_reason(): ?string {
				return null;
			}

			public function get_suggestions( array $segment_dto, array $context ): array {
				return array(
					new NormalizedSuggestion( 'tm', 'Same', 50.0, 5 ),
					new NormalizedSuggestion( 'tm', 'Same', 99.0, 1 ),
				);
			}
		};

		$service = new TranslationSuggestionService( array( $provider ) );
		$ranked  = $service->suggestions_for_segment( array( 'segment_key' => 'a' ), array() );

		$this->assertCount( 1, $ranked );
		$this->assertSame( 1, $ranked[0]['rank_tier'] );
		$this->assertSame( 99.0, $ranked[0]['confidence'] );
	}

	public function test_unavailable_provider_is_skipped(): void {
		$provider = new class() implements SuggestionProvider {
			public function get_id(): string {
				return 'tm';
			}

			public function is_available( array $segment_dto, array $context ): bool {
				return false;
			}

			public function get_unavailable_reason(): ?string {
				return 'language_mismatch';
			}

			public function get_suggestions( array $segment_dto, array $context ): array {
				return array( new NormalizedSuggestion( 'tm', 'Nope', 100.0, 1 ) );
			}
		};

		$service = new TranslationSuggestionService( array( $provider ) );
		$this->assertSame( array(), $service->suggestions_for_segment( array(), array() ) );
	}
}
