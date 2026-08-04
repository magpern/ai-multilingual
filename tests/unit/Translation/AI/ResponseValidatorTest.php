<?php
/**
 * ResponseValidator and SegmentConstraintAnalyzer unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\AI;

use AIMultilingual\Translation\AI\ResponseValidator;
use AIMultilingual\Translation\AI\SegmentConstraintAnalyzer;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * F11 WP4 — structural provider-pipeline validation (not QA).
 */
final class ResponseValidatorTest extends TestCase {

	public function test_accepts_structurally_valid_response(): void {
		$validator = new ResponseValidator();
		$result    = $validator->validate(
			'Hello {name}, you have 3 items',
			'Hej {name}, du har 3 artiklar',
			Store::FORMAT_PLAIN
		);

		$this->assertTrue( $result->valid );
	}

	public function test_rejects_missing_placeholder(): void {
		$validator = new ResponseValidator();
		$result    = $validator->validate(
			'Hello {name}',
			'Hej världen',
			Store::FORMAT_PLAIN
		);

		$this->assertFalse( $result->valid );
		$this->assertSame( ResponseValidator::CODE_PLACEHOLDER_MISMATCH, $result->code );
	}

	public function test_rejects_empty_target_for_non_empty_source(): void {
		$validator = new ResponseValidator();
		$result    = $validator->validate( 'Hello', '', Store::FORMAT_PLAIN );

		$this->assertFalse( $result->valid );
		$this->assertSame( ResponseValidator::CODE_EMPTY_TARGET, $result->code );
	}

	public function test_rejects_missing_html_tags(): void {
		$validator = new ResponseValidator();
		$result    = $validator->validate(
			'<p>Hello <strong>world</strong></p>',
			'<p>Hej värld</p>',
			Store::FORMAT_HTML
		);

		$this->assertFalse( $result->valid );
		$this->assertSame( ResponseValidator::CODE_HTML_MISMATCH, $result->code );
	}

	public function test_analyzer_extracts_placeholders_and_tags(): void {
		$analyzer = new SegmentConstraintAnalyzer();
		$analysis = $analyzer->analyze(
			'<p>Buy %s for {count} SEK</p>',
			Store::FORMAT_HTML
		);

		$this->assertContains( '%s', $analysis['placeholders'] );
		$this->assertContains( '{count}', $analysis['placeholders'] );
		$this->assertContains( 'p', $analysis['html_tags'] );
		$this->assertContains( 'placeholders', $analysis['constraints'] );
		$this->assertContains( 'html', $analysis['constraints'] );
	}

	public function test_validator_documents_separation_from_qa(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 4 ) . '/src/Translation/AI/ResponseValidator.php'
		);

		$this->assertStringContainsString( 'not QAEngine', $source );
		$this->assertStringContainsString( 'Provider-pipeline', $source );
	}
}
