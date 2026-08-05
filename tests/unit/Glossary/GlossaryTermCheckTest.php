<?php
/**
 * Glossary terminology QA check tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Glossary;

use AIMultilingual\Glossary\GlossaryService;
use AIMultilingual\Glossary\GlossaryTermMatch;
use AIMultilingual\Workspace\QA\Checks\GlossaryTermCheck;
use AIMultilingual\Workspace\QA\QAEngine;
use AIMultilingual\Workspace\QA\QAIssue;
use PHPUnit\Framework\TestCase;

/**
 * G5: warning-only glossary terminology check.
 */
final class GlossaryTermCheckTest extends TestCase {

	/**
	 * Missing preferred term yields a warning, not an error.
	 */
	public function test_missing_term_is_warning(): void {
		$match = new GlossaryTermMatch(
			1,
			'Biopentra',
			'Biopentra',
			'biopentra',
			GlossaryTermMatch::KIND_EMBEDDED,
			11,
			9
		);

		$glossary = $this->createMock( GlossaryService::class );
		$glossary->method( 'match_terms' )->willReturn( array( $match ) );

		$engine = new QAEngine( array(), false );
		$engine->register( new GlossaryTermCheck( $glossary ) );

		$result = $engine->evaluate(
			'Welcome to Biopentra',
			'Välkommen hit',
			'plain',
			array(
				'source_language_id' => 1,
				'target_language_id' => 2,
			)
		);

		$this->assertSame( 1, $result->summary()['warnings'] );
		$this->assertSame( 0, $result->summary()['errors'] );
		$this->assertSame( GlossaryTermCheck::CODE, $result->issues[0]->code );
		$this->assertSame( QAIssue::SEVERITY_WARNING, $result->issues[0]->severity );
	}

	/**
	 * Preferred term present yields no glossary warning.
	 */
	public function test_present_term_passes(): void {
		$match = new GlossaryTermMatch(
			1,
			'Biopentra',
			'Biopentra',
			'biopentra',
			GlossaryTermMatch::KIND_EMBEDDED,
			11,
			9
		);

		$glossary = $this->createMock( GlossaryService::class );
		$glossary->method( 'match_terms' )->willReturn( array( $match ) );

		$engine = new QAEngine( array(), false );
		$engine->register( new GlossaryTermCheck( $glossary ) );

		$result = $engine->evaluate(
			'Welcome to Biopentra',
			'Välkommen till Biopentra',
			'plain',
			array(
				'source_language_id' => 1,
				'target_language_id' => 2,
			)
		);

		$this->assertSame( 0, $result->summary()['warnings'] );
	}

	/**
	 * Without language context the check is a no-op.
	 */
	public function test_skips_without_language_context(): void {
		$glossary = $this->createMock( GlossaryService::class );
		$glossary->expects( $this->never() )->method( 'match_terms' );

		$engine = new QAEngine( array(), false );
		$engine->register( new GlossaryTermCheck( $glossary ) );

		$result = $engine->evaluate( 'Biopentra', 'X', 'plain' );
		$this->assertSame( array(), $result->issues );
	}
}
