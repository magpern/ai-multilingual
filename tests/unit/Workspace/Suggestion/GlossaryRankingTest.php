<?php
/**
 * Glossary suggestion ranking tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Workspace\Suggestion;

use AIMultilingual\Workspace\Suggestion\AISuggestionProvider;
use AIMultilingual\Workspace\Suggestion\GlossarySuggestionProvider;
use AIMultilingual\Workspace\Suggestion\NormalizedSuggestion;
use AIMultilingual\Workspace\Suggestion\TranslationMemorySuggestionProvider;
use AIMultilingual\Workspace\TranslationSuggestionService;
use PHPUnit\Framework\TestCase;

/**
 * ADR-0014 ranking amendment: glossary=5, fuzzy=6, AI=7.
 */
final class GlossaryRankingTest extends TestCase {

	public function test_tier_constants_follow_adr_0014(): void {
		$this->assertSame( 5, GlossarySuggestionProvider::TIER_GLOSSARY_EXACT );
		$this->assertSame( 6, TranslationMemorySuggestionProvider::TIER_FUZZY_TM );
		$this->assertSame( 7, AISuggestionProvider::TIER_AI );
		$this->assertSame( 1, TranslationMemorySuggestionProvider::TIER_EXACT_TM );
		$this->assertSame( 4, TranslationMemorySuggestionProvider::TIER_IMPORTED_TM );
	}

	public function test_deterministic_ranking_order(): void {
		$service = new TranslationSuggestionService( array() );

		$candidates = array(
			new NormalizedSuggestion( 'ai', 'zeta', 90.0, AISuggestionProvider::TIER_AI, array() ),
			new NormalizedSuggestion( 'glossary', 'alpha', 95.0, GlossarySuggestionProvider::TIER_GLOSSARY_EXACT, array() ),
			new NormalizedSuggestion( 'tm', 'beta', 80.0, TranslationMemorySuggestionProvider::TIER_FUZZY_TM, array() ),
			new NormalizedSuggestion( 'tm', 'gamma', 99.0, TranslationMemorySuggestionProvider::TIER_EXACT_TM, array() ),
		);

		$ref    = new \ReflectionClass( $service );
		$method = $ref->getMethod( 'rank' );
		$method->setAccessible( true );

		$first  = $method->invoke( $service, $candidates );
		$second = $method->invoke( $service, array_reverse( $candidates ) );

		$order = array_map(
			static fn( NormalizedSuggestion $s ): string => $s->provider_id . ':' . $s->rank_tier . ':' . $s->target_text,
			$first
		);

		$this->assertSame(
			array(
				'tm:1:gamma',
				'glossary:5:alpha',
				'tm:6:beta',
				'ai:7:zeta',
			),
			$order
		);
		$this->assertSame(
			$order,
			array_map(
				static fn( NormalizedSuggestion $s ): string => $s->provider_id . ':' . $s->rank_tier . ':' . $s->target_text,
				$second
			)
		);
	}
}
