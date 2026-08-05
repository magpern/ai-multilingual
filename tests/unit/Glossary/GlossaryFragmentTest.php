<?php
/**
 * Bounded AI glossary fragment tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Glossary;

use AIMultilingual\Glossary\GlossaryMatcher;
use AIMultilingual\Glossary\GlossaryNormalizer;
use AIMultilingual\Glossary\GlossaryRepository;
use AIMultilingual\Glossary\GlossaryService;
use AIMultilingual\Translation\AI\Providers\OpenAIProvider;
use AIMultilingual\Translation\AI\ProviderSegment;
use AIMultilingual\Translation\AI\TranslationBatch;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * G4: fragment order, bounds, truncation, and prompt consumption.
 */
final class GlossaryFragmentTest extends TestCase {

	/**
	 * Empty match set yields empty fragment.
	 */
	public function test_empty_fragment_when_no_matches(): void {
		$service = $this->service_with_terms( array() );
		$this->assertSame( '', $service->build_fragment( 'hello', 1, 2 ) );
	}

	/**
	 * Longer matches appear before shorter ones; offsets break ties.
	 */
	public function test_fragment_order_longest_then_offset(): void {
		$service = $this->service_with_terms(
			array(
				$this->term( 1, 'cream', 'kräm' ),
				$this->term( 2, 'peptide complex', 'peptidkomplex' ),
			)
		);

		$fragment = $service->build_fragment( 'peptide complex cream', 1, 2 );
		$lines    = explode( "\n", $fragment );
		$this->assertCount( 2, $lines );
		$this->assertSame( 'peptide complex => peptidkomplex', $lines[0] );
		$this->assertSame( 'cream => kräm', $lines[1] );
	}

	/**
	 * Term count bound inserts truncation marker.
	 */
	public function test_fragment_truncates_at_term_limit(): void {
		$terms = array();
		for ( $i = 1; $i <= GlossaryService::FRAGMENT_MAX_TERMS + 5; $i++ ) {
			$terms[] = $this->term( $i, 'term' . $i, 'mal' . $i );
		}
		$service = $this->service_with_terms( $terms );
		$source  = implode( ' ', array_map( static fn( $t ) => $t->source_term, $terms ) );

		$fragment = $service->build_fragment( $source, 1, 2 );
		$lines    = explode( "\n", $fragment );

		$this->assertSame( GlossaryService::FRAGMENT_TRUNCATION_MARKER, end( $lines ) );
		$this->assertLessThanOrEqual( GlossaryService::FRAGMENT_MAX_TERMS + 1, count( $lines ) );
		$this->assertStringContainsString( 'term', $fragment );
	}

	/**
	 * OpenAI prompt appends non-empty glossary_fragment only.
	 */
	public function test_openai_prompt_appends_fragment(): void {
		$provider = new OpenAIProvider( 'sk-test' );
		$method   = new ReflectionMethod( OpenAIProvider::class, 'build_user_prompt' );
		$method->setAccessible( true );

		$batch = new TranslationBatch(
			'en',
			'sv',
			'translate',
			'1',
			"Biopentra => Biopentra\n# glossary_truncated",
			array( new ProviderSegment( 'k1', 'Welcome to Biopentra', 'plain' ) )
		);

		$with = (string) $method->invoke( $provider, $batch, 'Welcome to Biopentra', '' );
		$this->assertStringContainsString( 'Glossary terminology (use consistently):', $with );
		$this->assertStringContainsString( 'Biopentra => Biopentra', $with );

		$empty   = new TranslationBatch(
			'en',
			'sv',
			'translate',
			'1',
			'',
			array( new ProviderSegment( 'k1', 'Hello', 'plain' ) )
		);
		$without = (string) $method->invoke( $provider, $empty, 'Hello', '' );
		$this->assertStringNotContainsString( 'Glossary terminology', $without );
	}

	/**
	 * Build a service backed by a stub repository.
	 *
	 * @param array<object> $terms Active glossary rows.
	 */
	private function service_with_terms( array $terms ): GlossaryService {
		$repo = $this->createMock( GlossaryRepository::class );
		$repo->method( 'list_active_for_pair' )->willReturn( $terms );

		return new GlossaryService(
			$repo,
			new GlossaryNormalizer(),
			new GlossaryMatcher( new GlossaryNormalizer() )
		);
	}

	/**
	 * @param int    $id     Glossary id.
	 * @param string $source Source term.
	 * @param string $target Target term.
	 */
	private function term( int $id, string $source, string $target ): object {
		return (object) array(
			'glossary_id'            => $id,
			'source_term'            => $source,
			'source_term_normalized' => mb_strtolower( $source, 'UTF-8' ),
			'target_term'            => $target,
			'context'                => '',
		);
	}
}
